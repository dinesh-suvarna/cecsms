<?php
include "../config/db.php";
include "../includes/session.php";

$page_title = "Login History";
$page_icon  = "bi-shield-lock";

if ($_SESSION['role'] !== 'SuperAdmin') {
    header("Location: ../index.php");
    exit();
}

function getDeviceIcon($name) {
    $name = strtolower($name);
    if (strpos($name, 'windows') !== false) return 'bi-microsoft';
    if (strpos($name, 'android') !== false) return 'bi-android2';
    if (strpos($name, 'ios') !== false || strpos($name, 'macos') !== false) return 'bi-apple';
    if (strpos($name, 'linux') !== false) return 'bi-ubuntu';
    
    if (strpos($name, 'chrome') !== false) return 'bi-browser-chrome';
    if (strpos($name, 'firefox') !== false) return 'bi-browser-firefox';
    if (strpos($name, 'edge') !== false) return 'bi-browser-edge';
    if (strpos($name, 'safari') !== false) return 'bi-browser-safari';
    if (strpos($name, 'brave') !== false) return 'bi-shield-check'; 
    
    return 'bi-laptop'; 
}

$search = $_GET['search'] ?? '';
$where = !empty($search) ? "WHERE u.username LIKE '%$search%' OR ll.ip_address LIKE '%$search%' OR ll.city LIKE '%$search%'" : "";

$query = "SELECT ll.*, u.username, u.role, u.last_activity FROM login_logs ll 
          JOIN users u ON ll.user_id = u.id $where 
          ORDER BY ll.login_time DESC LIMIT 100";
$result = $conn->query($query);

// Keep track of users we've already marked as "pulsing" so old logs don't blink
$active_pulses = [];

ob_start();
?>

<div class="container mt-4">
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
            <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Login History</h5>
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-primary btn-sm">Search</button>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase small">
                        <tr>
                            <th class="ps-4">User</th>
                            <th>Location & IP</th>
                            <th>Device / Browser</th>
                            <th>Login Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <?php 
                                // 1. Determine if the user is currently online (last activity < 5 mins)
                                $last_active_unix = $row['last_activity'] ? strtotime($row['last_activity']) : 0;
                                $isOnline = (time() - $last_active_unix) < 300; 

                                // 2. logic to only pulse the newest log for an active user
                                $showPulse = false;
                                if ($isOnline && !in_array($row['user_id'], $active_pulses)) {
                                    $showPulse = true;
                                    $active_pulses[] = $row['user_id']; // Mark user as "pulsed" for this page load
                                }
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3" style="width: 12px; height: 12px; position: relative;">
                                            <?php if($isOnline): ?>
                                                <span class="position-absolute bg-success rounded-circle" style="width: 10px; height: 10px; z-index: 2; border: 2px solid white;"></span>
                                                <?php if($showPulse): ?>
                                                    <span class="position-absolute bg-success rounded-circle animate-ping" style="width: 10px; height: 10px; opacity: 0.75; animation: pulse 2s infinite;"></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="bg-danger rounded-circle d-block" style="width: 10px; height: 10px; border: 2px solid white;"></span>
                                            <?php endif; ?>
                                        </div>

                                        <div>
                                            <div class="fw-bold">@<?= htmlspecialchars($row['username']) ?></div>
                                            <span class="badge bg-light text-dark border small fw-normal" style="font-size: 0.65rem;"><?= $row['role'] ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if(!empty($row['country_code'])): ?>
                                            <img src="https://flagcdn.com/16x12/<?= strtolower($row['country_code']) ?>.png" class="me-2 rounded-1 border shadow-sm" title="<?= $row['country'] ?>">
                                        <?php else: ?>
                                            <i class="bi bi-geo-alt me-2 text-muted"></i>
                                        <?php endif; ?>
                                        <div>
                                            <div class="small fw-bold text-dark"><?= $row['ip_address'] ?></div>
                                            <div class="extra-small text-muted" style="font-size:0.7rem;"><?= $row['city'] ?>, <?= $row['country'] ?></div>
                                        </div>
                                    </div>
                                </td>
                               <td>
                                    <div class="small text-dark d-flex align-items-center">
                                        <i class="bi <?= getDeviceIcon($row['os']) ?> me-2 text-primary"></i>
                                        <?= htmlspecialchars($row['os']) ?>
                                    </div>
                                    <div class="extra-small text-muted d-flex align-items-center" style="font-size: 0.75rem;">
                                        <i class="bi <?= getDeviceIcon($row['browser']) ?> me-2"></i>
                                        <?= htmlspecialchars($row['browser']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small text-dark"><?= date("d M Y", strtotime($row['login_time'])) ?></div>
                                    <div class="text-muted" style="font-size:0.75rem;"><?= date("h:i A", strtotime($row['login_time'])) ?></div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">No login history found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes pulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
    70% { transform: scale(1.5); box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
}
.animate-ping {
    z-index: 1;
}
</style>

<?php 
$content = ob_get_clean(); 
include "adminlayout.php"; 
?>