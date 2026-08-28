<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$ip = $_GET['ip'] ?? '';
$auth = $_GET['auth'] ?? '0';

if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode(["status" => "error", "message" => "Invalid IP"]);
    exit;
}

// Replace wlan0 with your actual wireless AP interface name if different
$interface = "end0"; 

if ($auth === '1') {
    shell_exec("sudo iptables -C FORWARD -i $interface -s $ip -j ACCEPT 2>/dev/null || sudo iptables -I FORWARD 1 -i $interface -s $ip -j ACCEPT");
} else {
    shell_exec("sudo iptables -D FORWARD -i $interface -s $ip -j ACCEPT 2>/dev/null");
}

echo json_encode(["status" => "success", "ip" => $ip, "auth" => $auth]);
?>
