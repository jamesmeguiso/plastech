<?php
$status_file = 'status.json';

// Handle Reset Button Action
if (isset($_POST['reset_time'])) {
    if (file_exists($status_file)) {
        $data = json_decode(file_get_contents($status_file), true);
        $data['total_inserted'] = 0;
        $data['remaining_time'] = 0;
        file_put_contents($status_file, json_encode($data));
    }
    header("Location: admin.php");
    exit();
}

// Read current status
$total_inserted = 0;
$remaining_time = 0;
if (file_exists($status_file)) {
    $data = json_decode(file_get_contents($status_file), true);
    $total_inserted = $data['total_inserted'] ?? 0;
    $remaining_time = $data['remaining_time'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlasTech - Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background: #0f172a; color: #fff; padding: 40px; }
        .container { max-width: 600px; margin: auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.4); }
        h1 { color: #38bdf8; margin-top: 0; }
        .metrics { display: flex; gap: 15px; margin: 20px 0; }
        .box { background: #0f172a; flex: 1; padding: 20px; border-radius: 8px; text-align: center; border: 1px solid #334155; }
        .box h3 { margin: 0; color: #94a3b8; font-size: 14px; }
        .box p { font-size: 20px; font-weight: bold; color: #10b981; margin: 10px 0 0 0; }
        .btn-reset { background: #ef4444; color: white; border: none; padding: 12px 20px; font-size: 16px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%; margin-top: 15px; }
        .btn-reset:hover { background: #dc2626; }
        .back-link { display: inline-block; margin-top: 20px; font-size: 14px; color: #38bdf8; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <h1>PlasTech Admin Panel</h1>
    <p>System Management & Control Dashboard</p>

    <div class="metrics">
        <div class="box">
            <h3>Total Bottles Inserted</h3>
            <p><?php echo $total_inserted; ?></p>
        </div>
        <div class="box">
            <h3>Remaining Time (Sec)</h3>
            <p><?php echo $remaining_time; ?></p>
        </div>
    </div>

    <form method="POST">
        <button type="submit" name="reset_time" class="btn-reset" onclick="return confirm('Are you sure you want to reset all user time and bottle counts?');">Reset All Time & Counters</button>
    </form>

    <a href="index.php" class="back-link">&larr; Back to Customer Portal</a>
</div>
</body>
</html>
