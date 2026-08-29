<?php
header('Content-Type: application/json');
$statusFile = "status.json";
$lockFile = "status.lock";

if (!isset($_COOKIE['ptid']) || !preg_match('/^[a-f0-9]{32}$/', $_COOKIE['ptid'])) {
    $device_token = bin2hex(random_bytes(16));
    setcookie('ptid', $device_token, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => '/', 'httponly' => true, 'samesite' => 'Lax'
    ]);
} else {
    $device_token = $_COOKIE['ptid'];
}

$active = isset($_GET['active']) ? intval($_GET['active']) : 0;
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (empty($client_ip)) {
    echo json_encode(["success" => false, "message" => "No IP"]);
    exit;
}

$lockHandle = fopen($lockFile, 'c');
if ($lockHandle === false) {
    echo json_encode(["success" => false, "message" => "Lock error"]);
    exit;
}
flock($lockHandle, LOCK_EX);

$data = [];
if (file_exists($statusFile)) {
    $current = json_decode(file_get_contents($statusFile), true);
    if (is_array($current)) $data = $current;
}

if (!isset($data[$device_token])) {
    $data[$device_token] = [
        "bottles" => 0,
        "session_bottles" => 0,
        "base_seconds" => 0,
        "seconds" => 0,
        "active" => false,
        "metal_rejected" => 0,
        "current_ip" => $client_ip
    ];
}

// Migrate any old-format entries that don't have the new fields yet
if (!isset($data[$device_token]['session_bottles'])) $data[$device_token]['session_bottles'] = 0;
if (!isset($data[$device_token]['base_seconds'])) $data[$device_token]['base_seconds'] = $data[$device_token]['seconds'] ?? 0;

// IP migration — revoke old IP if it changed since last request
$old_ip = $data[$device_token]['current_ip'] ?? null;
if ($old_ip && $old_ip !== $client_ip) {
    @shell_exec("sudo iptables -D FORWARD -i end0 -s " . escapeshellarg($old_ip) . " -j ACCEPT 2>/dev/null");
    @shell_exec("sudo conntrack -D -s " . escapeshellarg($old_ip) . " 2>/dev/null");
}
$data[$device_token]['current_ip'] = $client_ip;

$was_active = $data[$device_token]['active'] ?? false;

if ($active === 1) {
    foreach ($data as $tok => $session) {
        if ($tok !== $device_token && isset($session['active']) && $session['active'] === true) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            echo json_encode(["success" => false, "message" => "Another device is currently inserting bottles."]);
            exit;
        }
    }
    // Starting a FRESH insert session (not already active): snapshot however much time
    // is currently remaining, and reset this session's own bottle counter to 0.
    // Any bottles inserted from here on ADD reward on top of the snapshot — they never
    // overwrite or shrink whatever time was already running.
    if (!$was_active) {
        $data[$device_token]['session_bottles'] = 0;
        $data[$device_token]['base_seconds'] = $data[$device_token]['seconds'] ?? 0;
    }
}

$data[$device_token]['active'] = ($active === 1);

$tempFile = "status_temp.json";
file_put_contents($tempFile, json_encode($data));
rename($tempFile, $statusFile);
chmod($statusFile, 0777);

$response = [
    "success" => true,
    "bottles" => $data[$device_token]['bottles'],
    "session_bottles" => $data[$device_token]['session_bottles'],
    "seconds" => $data[$device_token]['seconds'],
    "active"  => $data[$device_token]['active']
];

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
echo json_encode($response);
?>
