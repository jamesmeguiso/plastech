<?php
// admin.php - Plas-Tech Admin Control Panel
error_reporting(E_ALL);
ini_set('display_errors', 0);

$status_file = 'status.json';
$auth_error = '';

if (!file_exists($status_file)) {
    file_put_contents($status_file, json_encode([]));
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $u = trim($_POST['admin_user'] ?? '');
    $p = trim($_POST['admin_pass'] ?? '');
    if ($u === 'group5' && $p === 'snire') {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit();
    } else {
        $auth_error = 'Invalid Username or Password!';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    session_destroy();
    header("Location: admin.php");
    exit();
}

$is_logged = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if ($is_logged) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_all'])) {
        if (file_exists($status_file)) {
            $data = json_decode(file_get_contents($status_file), true);
            if (is_array($data)) {
                foreach ($data as $ip_address => $val) {
                    if (filter_var($ip_address, FILTER_VALIDATE_IP)) {
                        @shell_exec("sudo iptables -D FORWARD -i end0 -s " . escapeshellarg($ip_address) . " -j ACCEPT 2>/dev/null");
                        @shell_exec("sudo conntrack -D -s " . escapeshellarg($ip_address) . " 2>/dev/null");
                    }
                }
            }
        }
        file_put_contents($status_file, json_encode([]));
        header("Location: admin.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_ip'])) {
        $target_ip = $_POST['target_ip'] ?? '';
        if (filter_var($target_ip, FILTER_VALIDATE_IP) && file_exists($status_file)) {
            $data = json_decode(file_get_contents($status_file), true);
            if (is_array($data) && isset($data[$target_ip])) {
                unset($data[$target_ip]);
                @shell_exec("sudo iptables -D FORWARD -i end0 -s " . escapeshellarg($target_ip) . " -j ACCEPT 2>/dev/null");
                @shell_exec("sudo conntrack -D -s " . escapeshellarg($target_ip) . " 2>/dev/null");
                file_put_contents($status_file, json_encode($data));
            }
        }
        header("Location: admin.php");
        exit();
    }
}

$sessions_data = [];
if (file_exists($status_file)) {
    $raw = json_decode(file_get_contents($status_file), true);
    if (is_array($raw)) {
        $sessions_data = $raw;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlasTech - Admin Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #fff; padding: 20px; margin: 0; }
        .container { max-width: 700px; margin: 30px auto; background: #1e293b; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.4); }
        h1 { color: #38bdf8; margin-top: 0; font-size: 22px; text-align: center; }
        p { text-align: center; color: #94a3b8; font-size: 13px; }
        .form-input { width: 100%; padding: 10px; margin: 8px 0 14px 0; border: 1px solid #334155; background: #0f172a; color: #fff; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        .vende-btn { width: 100%; padding: 10px; font-size: 14px; font-weight: bold; color: white; border: none; border-radius: 6px; cursor: pointer; transition: 0.2s; }
        .btn-dark { background-color: #334155; } .btn-dark:hover { background-color: #475569; }
        .btn-red { background: #ef4444; color: white; } .btn-red:hover { background: #dc2626; }
        .btn-sm { padding: 5px 10px; font-size: 12px; width: auto; display: inline-block; }
        .error-msg { background: #7f1d1d; color: #fca5a5; padding: 8px; border-radius: 6px; font-size: 13px; margin-bottom: 12px; text-align: center; border: 1px solid #991b1b; }
        .table-container { overflow-x: auto; margin: 20px 0; }
        .ip-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        .ip-table th, .ip-table td { padding: 10px; border-bottom: 1px solid #334155; }
        .ip-table th { background-color: #0f172a; color: #38bdf8; }
        .badge-active { background: #065f46; color: #34d399; padding: 3px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-idle { background: #1e293b; color: #94a3b8; padding: 3px 6px; border-radius: 4px; font-size: 11px; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; padding-bottom: 12px; margin-bottom: 15px; }
        .back-link { font-size: 13px; color: #38bdf8; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <?php if (!$is_logged): ?>
        <h1>🔐 Admin Portal Login</h1>
        <p>Enter administrator credentials to manage client IPs.</p>
        <?php if (!empty($auth_error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($auth_error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="admin_user" class="form-input" placeholder="Username" required>
            <input type="password" name="admin_pass" class="form-input" placeholder="Password" required>
            <button type="submit" name="login" class="vende-btn btn-dark">Login to Dashboard</button>
        </form>
        <div style="text-align: center; margin-top: 15px;">
            <a href="index.php" class="back-link">&larr; Back to Customer Portal</a>
        </div>
    <?php else: ?>
        <div class="top-bar">
            <h1 style="margin: 0; text-align: left;">⚙️ Admin Control Panel</h1>
            <a href="admin.php?logout=1" class="back-link" style="color: #f87171;">Logout Admin</a>
        </div>
        <p style="text-align: left;">Manage active sessions, client IP states, and perform resets.</p>

        <div class="table-container">
            <table class="ip-table">
                <thead>
                    <tr>
                        <th>Client IP Address</th>
                        <th>Bottles</th>
                        <th>Time Left</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $has_ips = false;
                    foreach ($sessions_data as $ip => $info):
                        if (!filter_var($ip, FILTER_VALIDATE_IP)) continue;
                        $has_ips = true;
                        $bottles = $info['bottles'] ?? 0;
                        $seconds = $info['seconds'] ?? 0;
                        $is_active = !empty($info['active']);
                        $mins = floor($seconds / 60);
                        $secs = $seconds % 60;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($ip); ?></strong></td>
                        <td><?php echo (int)$bottles; ?></td>
                        <td><?php echo "{$mins}m {$secs}s"; ?></td>
                        <td>
                            <?php if ($is_active): ?>
                                <span class="badge-active">Inserting</span>
                            <?php else: ?>
                                <span class="badge-idle">Idle</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Reset session for IP: <?php echo htmlspecialchars($ip); ?>?');">
                                <input type="hidden" name="target_ip" value="<?php echo htmlspecialchars($ip); ?>">
                                <button type="submit" name="reset_ip" class="vende-btn btn-red btn-sm">Reset IP</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$has_ips): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #64748b; padding: 20px;">No client IP sessions recorded yet.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($has_ips): ?>
        <form method="POST" onsubmit="return confirm('Reset ALL connected client IP sessions?');" style="margin-top: 15px;">
            <button type="submit" name="reset_all" class="vende-btn btn-red">Reset All Connected IPs</button>
        </form>
        <?php endif; ?>

        <div style="margin-top: 20px; border-top: 1px solid #334155; padding-top: 12px; text-align: left;">
            <a href="index.php" class="back-link">&larr; Return to Customer Portal</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
