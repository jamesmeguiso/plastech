<?php
header('Content-Type: application/json');
$statusFile = "status.json";

$active = isset($_GET['active']) ? intval($_GET['active']) : 0;
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

$data = ["bottles" => 0, "active" => false, "seconds" => 0, "metal_rejected" => 0, "client_ip" => ""];
if (file_exists($statusFile)) {
    $current = json_decode(file_get_contents($statusFile), true);
    if (is_array($current)) {
        $data = $current;
    }
}

$data['active'] = ($active === 1);
if ($active === 1 && !empty($client_ip)) {
    $data['client_ip'] = $client_ip;
}

// Atomic save
$tempFile = "status_temp.json";
file_put_contents($tempFile, json_encode($data));
rename($tempFile, $statusFile);

echo json_encode(["success" => true, "active" => $data['active'], "client_ip" => $data['client_ip']]);
?>
