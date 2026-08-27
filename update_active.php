<?php
$statusFile = "status.json";
$isActive = isset($_GET['active']) && $_GET['active'] === '1' ? 1 : 0;

if (file_exists($statusFile)) {
    $data = json_decode(file_get_contents($statusFile), true);
    $data['active'] = $isActive;
    file_put_contents($statusFile, json_encode($data));
}
echo json_encode(["status" => "success", "active" => $isActive]);
?>
