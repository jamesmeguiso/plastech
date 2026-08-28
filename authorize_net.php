<?php
header('Content-Type: application/json');

$ip = $_GET['ip'] ?? '';
$auth = $_GET['auth'] ?? '0';

if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode(["status" => "error", "message" => "Invalid IP"]);
    exit;
}

if ($auth === '1') {
    // UNBLOCK: Insert allow rule at the very top (position 1) of the FORWARD chain
    shell_exec("sudo iptables -C FORWARD -s $ip -j ACCEPT 2>/dev/null || sudo iptables -I FORWARD 1 -s $ip -j ACCEPT");
} else {
    // BLOCK: Remove the allow rules
    shell_exec("sudo iptables -D FORWARD -s $ip -j ACCEPT 2>/dev/null");
}

echo json_encode(["status" => "success", "ip" => $ip, "auth" => $auth]);
?>
