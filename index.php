<?php
$statusFile = "status.json";
$bottleCount = 0;
if (file_exists($statusFile)) {
    $data = json_decode(file_get_contents($statusFile), true);
    if (isset($data['bottles'])) {
        $bottleCount = $data['bottles'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plas-Tech - Eco Vendo Portal</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0; padding: 10px;
            display: flex; justify-content: center; align-items: center;
            min-height: 100vh;
        }
        .vendo-card {
            background: #ffffff; width: 100%; max-width: 420px;
            border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            overflow: hidden; border: 2px solid #28a745; margin: auto;
        }
        .header-banner {
            background: #ffc107; color: #333; text-align: center;
            padding: 12px; font-weight: bold; font-size: 16px; border-bottom: 2px solid #e0a800;
        }
        .content-body { padding: 15px 20px; text-align: center; }
        .connection-status {
            background-color: #f8d7da; color: #721c24; padding: 10px;
            border-radius: 6px; font-weight: bold; font-size: 14px;
            margin-bottom: 12px; border: 1px solid #f5c6cb; transition: 0.3s;
        }
        .connection-status.active {
            background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;
        }
        .network-info { font-size: 12px; color: #666; margin-bottom: 12px; border-bottom: 1px dashed #ddd; padding-bottom: 8px; }
        .time-container { background: #eef2f7; border-radius: 8px; padding: 10px; margin-bottom: 15px; border: 1px solid #d1d9e6; }
        .time-label { font-size: 11px; color: #555; font-weight: bold; letter-spacing: 0.5px; }
        .time-display { font-size: 24px; font-weight: bold; color: #0056b3; margin-top: 4px; }
        .vende-btn {
            width: 100%; padding: 12px; font-size: 15px; font-weight: bold;
            color: white; border: none; border-radius: 6px; cursor: pointer;
            margin-bottom: 10px; box-shadow: 0 3px 6px rgba(0,0,0,0.1); transition: 0.2s;
        }
        .btn-green { background-color: #28a745; } .btn-green:hover { background-color: #218838; }
        .btn-red { background-color: #dc3545; } .btn-red:hover { background-color: #c82333; }
        .btn-blue { background-color: #007bff; } .btn-blue:hover { background-color: #0056b3; }
        .btn-dark { background-color: #343a40; } .btn-dark:hover { background-color: #23272b; }
        .tcc-footer {
            background: #f1f3f5; color: #495057; text-align: center;
            padding: 10px; font-size: 11px; border-top: 1px solid #dee2e6; font-weight: 600;
        }
        .admin-link {
            display: inline-block; margin-top: 4px; background: #e9ecef;
            color: #495057; padding: 2px 8px; border-radius: 4px; font-size: 10px; text-decoration: none; border: 1px solid #ced4da;
        }
        .admin-link:hover { background: #dee2e6; color: #212529; }
        .modal-overlay {
            display: none; position: fixed; z-index: 999; left: 0; top: 0;
            width: 100%; height: 100%; background-color: rgba(0,0,0,0.6);
            justify-content: center; align-items: center; padding: 15px;
        }
        .modal-box { background: white; padding: 20px; border-radius: 10px; text-align: center; width: 100%; max-width: 320px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .modal-timer { font-size: 32px; font-weight: bold; color: #dc3545; margin: 8px 0; }
        .live-count-badge { background: #e2f0d9; color: #28a745; font-size: 15px; font-weight: bold; padding: 8px; border-radius: 5px; margin: 8px 0; }
        .rates-table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 13px; text-align: left; }
        .rates-table th, .rates-table td { padding: 8px; border-bottom: 1px solid #dee2e6; }
        .rates-table th { background-color: #f8f9fa; color: #333; }
        .form-input { width: 100%; padding: 10px; margin: 6px 0 12px 0; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; }
        .admin-stat-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 10px; margin-bottom: 10px; text-align: left; font-size: 12px; color: #333; }
    </style>
</head>
<body>

    <div class="vendo-card">
        <div class="header-banner">PLAS-TECH ECO-VENDO PORTAL</div>
        <div class="content-body">
            <div id="statusBanner" class="connection-status">🔴 Internet Locked (Insert Bottles)</div>
            <div class="network-info">System Online | Total Inserted: <strong id="mainBottleCount"><?php echo $bottleCount; ?></strong></div>
            <div class="time-container">
                <div class="time-label">REMAINING FREE INTERNET TIME:</div>
                <div class="time-display" id="mainTimeDisplay">00 MIN. 00 SEC.</div>
            </div>
            <button class="vende-btn btn-green" onclick="openInsertModal()">Insert Bottles Now</button>
            <button class="vende-btn btn-blue" onclick="openRatesModal()">View Rates</button>
            <button class="vende-btn btn-red" onclick="alert('System Hardware: IR Sensor & Cooling Fan Operational.')">Machine Status</button>
        </div>
        <div class="tcc-footer">
            Talisay City College &copy; 2026<br>Plas-Tech Research Project<br>
            <a href="javascript:void(0);" class="admin-link" onclick="openAdminLogin()">Admin Portal</a>
        </div>
    </div>

    <!-- Active Window Popup for Bottle Insertion -->
    <div id="insertModal" class="modal-overlay">
        <div class="modal-box">
            <h3 style="margin-top: 0; color: #333;">Drop Bottles In Slot</h3>
            <p style="font-size: 12px; color: #666;">Infrared sensor is active and scanning.</p>
            <div class="live-count-badge">Session Count: <span id="modalBottleCount">0</span> Bottles</div>
            <div style="font-size: 11px; color: #888; font-weight: bold;">SLOT OPEN TIMEOUT</div>
            <div class="modal-timer" id="modalTimerDisplay">60 SEC</div>
            <button class="vende-btn btn-red" style="margin-top: 10px; padding: 10px; margin-bottom: 0;" onclick="closeInsertModal()">Done / Finish</button>
        </div>
    </div>

    <!-- Rates Popup Modal Window -->
    <div id="ratesModal" class="modal-overlay">
        <div class="modal-box">
            <h3 style="margin-top: 0; color: #007bff;">♻️ System Rates & Conversion</h3>
            <table class="rates-table">
                <thead><tr><th>Bottles</th><th>Time Reward</th></tr></thead>
                <tbody>
                    <tr><td>1 Bottle</td><td>10 Minutes</td></tr>
                    <tr><td>2 Bottles</td><td>20 Minutes</td></tr>
                    <tr><td>3 Bottles</td><td>30 Minutes</td></tr>
                    <tr><td>5 Bottles</td><td>1 Hour</td></tr>
                    <tr><td>10 Bottles</td><td>3 Hours</td></tr>
                </tbody>
            </table>
            <button class="vende-btn btn-blue" style="margin-top: 10px; padding: 10px; margin-bottom: 0;" onclick="closeRatesModal()">Close</button>
        </div>
    </div>

    <!-- Admin Login Modal -->
    <div id="adminLoginModal" class="modal-overlay">
        <div class="modal-box">
            <h3 style="margin-top: 0; color: #343a40;">🔐 Administrator Login</h3>
            <input type="text" id="adminUser" class="form-input" placeholder="Username">
            <input type="password" id="adminPass" class="form-input" placeholder="Password">
            <button class="vende-btn btn-dark" style="margin-top: 5px; padding: 10px;" onclick="verifyAdminLogin()">Login</button>
            <button class="vende-btn btn-red" style="padding: 10px; margin-bottom: 0;" onclick="closeAdminLogin()">Cancel</button>
        </div>
    </div>

    <!-- Admin Dashboard Modal -->
    <div id="adminDashboardModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 360px;">
            <h3 style="margin-top: 0; color: #343a40;">⚙️ Admin Control Panel</h3>
            <div class="admin-stat-box">
                <div><strong>Active Users Online:</strong> <span id="adminActiveUsers" style="color: #007bff;">1</span></div>
                <div><strong>System Temperature:</strong> <span id="adminTemp" style="color: #28a745;">Loading...</span></div>
                <div><strong>Total Lifetime Bottles:</strong> <span id="adminTotalBottles">0</span></div>
            </div>
            <button class="vende-btn btn-red" style="padding: 10px; font-size: 13px; margin-bottom: 8px;" onclick="resetAllUsersTime()">⚡ Reset All Users Time</button>
            <button class="vende-btn btn-dark" style="padding: 8px; font-size: 12px; margin-bottom: 0;" onclick="closeAdminDashboard()">Logout / Close</button>
        </div>
    </div>

    <script>
    let modalInterval;
    let pollInterval;
    let mainTickerInterval;
    let sessionSeconds = 0;
    let sessionEarnedBottles = 0;
    let timeLeft = 60;
    let lastKnownBottles = 0;

    function updateBackendState(isActive) {
        fetch('update_active.php?active=' + (isActive ? '1' : '0')).catch(e => {});
    }

    function updateInternetAccess(isAuthorized) {
        let clientIp = "<?php echo $_SERVER['REMOTE_ADDR'] ?? '192.168.4.15'; ?>";
        fetch('authorize_net.php?ip=' + clientIp + '&auth=' + (isAuthorized ? '1' : '0')).catch(e => {});
        let banner = document.getElementById('statusBanner');
        if (isAuthorized) {
            banner.className = "connection-status active";
            banner.innerHTML = "🟢 Internet Unlocked & Connected";
        } else {
            banner.className = "connection-status";
            banner.innerHTML = "🔴 Internet Locked (Insert Bottles)";
        }
    }

    function openInsertModal() {
        document.getElementById('insertModal').style.display = 'flex';
        timeLeft = 60; 
        document.getElementById('modalTimerDisplay').textContent = timeLeft + " SEC";
        updateBackendState(true);

        // Fetch baseline bottle count when modal opens
        fetch('status.json?' + new Date().getTime())
            .then(res => res.json())
            .then(data => { lastKnownBottles = data.bottles || 0; }).catch(e => {});

        clearInterval(modalInterval);
        modalInterval = setInterval(() => {
            timeLeft--;
            document.getElementById('modalTimerDisplay').textContent = timeLeft + " SEC";
            if (timeLeft <= 0) {
                clearInterval(modalInterval);
                closeInsertModal();
            }
        }, 1000);

        clearInterval(pollInterval);
        pollInterval = setInterval(syncDatabaseStats, 1000);
    }

    function closeInsertModal() {
        clearInterval(modalInterval);
        clearInterval(pollInterval);
        updateBackendState(false);
        document.getElementById('insertModal').style.display = 'none';
        syncDatabaseStats();
    }

    function openRatesModal() { document.getElementById('ratesModal').style.display = 'flex'; }
    function closeRatesModal() { document.getElementById('ratesModal').style.display = 'none'; }
    function openAdminLogin() { document.getElementById('adminLoginModal').style.display = 'flex'; }
    function closeAdminLogin() { document.getElementById('adminLoginModal').style.display = 'none'; }

    function verifyAdminLogin() {
        let u = document.getElementById('adminUser').value.trim();
        let p = document.getElementById('adminPass').value.trim();
        if (u === 'group5' && p === 'snire') {
            closeAdminLogin();
            openAdminDashboard();
        } else { alert('Invalid credentials!'); }
    }

    function openAdminDashboard() {
        document.getElementById('adminDashboardModal').style.display = 'flex';
        fetch('status.json?' + new Date().getTime())
            .then(res => res.json())
            .then(data => {
                document.getElementById('adminTotalBottles').textContent = data.bottles || 0;
                document.getElementById('adminActiveUsers').textContent = data.active ? "1 (Active)" : "0 (Idle)";
            }).catch(e => {});
        document.getElementById('adminTemp').textContent = "42.5 °C (Normal)";
    }

    function closeAdminDashboard() { document.getElementById('adminDashboardModal').style.display = 'none'; }

    function resetAllUsersTime() {
        if (confirm('Are you sure you want to wipe all session times?')) {
            sessionSeconds = 0;
            sessionEarnedBottles = 0;
            updateInternetAccess(false);
            fetch('reset_time.php')
                .then(res => res.json())
                .then(data => {
                    alert('System reset successfully.');
                    closeAdminDashboard();
                    syncDatabaseStats();
                }).catch(err => { alert('Error resetting time.'); });
        }
    }

    function syncDatabaseStats() {
        fetch('status.json?' + new Date().getTime())
            .then(res => res.json())
            .then(data => {
                if (data && data.bottles !== undefined) {
                    document.getElementById('modalBottleCount').textContent = data.bottles;
                    document.getElementById('mainBottleCount').textContent = data.bottles;

                    // Instantly reset timer back to 60 seconds right after a valid bottle count updates
                    if (document.getElementById('insertModal').style.display === 'flex') {
                        if (data.bottles > lastKnownBottles) {
                            timeLeft = 60; // Reset timer instantly
                            lastKnownBottles = data.bottles; // Update baseline
                        }
                    }

                    if (data.seconds !== undefined && data.bottles > sessionEarnedBottles) {
                        let addedBottles = data.bottles - sessionEarnedBottles;
                        sessionSeconds += addedBottles * 600; 
                        sessionEarnedBottles = data.bottles;
                    }
                }
            }).catch(err => {});
    }

    function startMainClock() {
        clearInterval(mainTickerInterval);
        mainTickerInterval = setInterval(() => {
            if (sessionSeconds > 0) {
                sessionSeconds--;
                updateInternetAccess(true);
            } else {
                sessionSeconds = 0;
                updateInternetAccess(false);
            }
            let mins = Math.floor(sessionSeconds / 60);
            let secs = sessionSeconds % 60;
            document.getElementById('mainTimeDisplay').textContent = mins + " MIN. " + secs + " SEC.";
        }, 1000);
    }

    window.onload = function() {
        document.getElementById('insertModal').style.display = 'none';
        document.getElementById('ratesModal').style.display = 'none';
        document.getElementById('adminLoginModal').style.display = 'none';
        document.getElementById('adminDashboardModal').style.display = 'none';
        updateBackendState(false);
        syncDatabaseStats();
        startMainClock();
    };
    </script>
</body>
</html>
