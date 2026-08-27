<?php
header('Content-Type: application/json');

$ip = $_GET['ip'] ?? '';
$auth = $_GET['auth'] ?? '0';

if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode(["status" => "error", "message" => "Invalid IP"]);
    exit;
}

if ($auth === '1') {
    // UNBLOCK: Ensure the IP is allowed to forward traffic through the gateway
    shell_exec("sudo iptables -C FORWARD -s $ip -j ACCEPT 2>/dev/null || sudo iptables -A FORWARD -s $ip -j ACCEPT");
    shell_exec("sudo iptables -C FORWARD -d $ip -j ACCEPT 2>/dev/null || sudo iptables -A FORWARD -d $ip -j ACCEPT");
} else {
    // BLOCK: Remove the allow rules so traffic stops flowing
    shell_exec("sudo iptables -D FORWARD -s $ip -j ACCEPT 2>/dev/null");
    shell_exec("sudo iptables -D FORWARD -d $ip -j ACCEPT 2>/dev/null");
}

echo json_encode(["status" => "success", "ip" => $ip, "auth" => $auth]);
?>
