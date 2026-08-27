<?php
$statusFile = "status.json";
if (file_exists($statusFile)) {
    $data = json_decode(file_get_contents($statusFile), true);
    $data['bottles'] = 0;
    $data['seconds'] = 0;
    $data['active'] = 0;
    file_put_contents($statusFile, json_encode($data));
}
echo json_encode(["status" => "success"]);
?>
