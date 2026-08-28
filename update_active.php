<?php
header('Content-Type: application/json');
$statusFile = "status.json";

$active = isset($_GET['active']) ? intval($_GET['active']) : 0;
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

if (empty($client_ip)) {
    echo json_encode(["success" => false, "message" => "No IP"]);
    exit;
}

$data = [];
if (file_exists($statusFile)) {
    $current = json_decode(file_get_contents($statusFile), true);
    if (is_array($current)) {
        $data = $current;
    }
}

if (!isset($data[$client_ip])) {
    $data[$client_ip] = [
        "bottles" => 0,
        "seconds" => 0,
        "active" => false,
        "metal_rejected" => 0
    ];
}

if ($active === 1) {
    foreach ($data as $ip => $session) {
        if ($ip !== $client_ip && isset($session['active']) && $session['active'] === true) {
            echo json_encode(["success" => false, "message" => "Another device is currently inserting bottles."]);
            exit;
        }
    }
}

$data[$client_ip]['active'] = ($active === 1);

$tempFile = "status_temp.json";
file_put_contents($tempFile, json_encode($data));
rename($tempFile, $statusFile);
chmod($statusFile, 0777);

echo json_encode([
    "success" => true, 
    "client_ip" => $client_ip,
    "bottles" => $data[$client_ip]['bottles'],
    "seconds" => $data[$client_ip]['seconds'],
    "active" => $data[$client_ip]['active']
]);
?>
