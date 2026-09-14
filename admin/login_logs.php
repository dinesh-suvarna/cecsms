<?php
require_once __DIR__ . "/../config/db.php";
include "../includes/session.php";

$page_title = "Login History";
$page_icon  = "bi-shield-lock";

/* =========================================================
   ACCESS CONTROL
========================================================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SuperAdmin') {
    header("Location: ../index.php");
    exit();
}

/* =========================================================
   DEVICE / BROWSER ICON
========================================================= */
function getDeviceIcon($name) {
    $name = strtolower($name ?? '');

    /* Operating Systems */
    if (strpos($name, 'windows') !== false) return 'bi-microsoft';
    if (strpos($name, 'android') !== false) return 'bi-android2';
    if (strpos($name, 'ios') !== false || strpos($name, 'macos') !== false) {
        return 'bi-apple';
    }
    if (strpos($name, 'linux') !== false) return 'bi-ubuntu';

    /* Browsers */
    if (strpos($name, 'chrome') !== false) return 'bi-browser-chrome';
    if (strpos($name, 'firefox') !== false) return 'bi-browser-firefox';
    if (strpos($name, 'edge') !== false) return 'bi-browser-edge';
    if (strpos($name, 'safari') !== false) return 'bi-browser-safari';
    if (strpos($name, 'brave') !== false) return 'bi-shield-check';
    if (strpos($name, 'opera') !== false) return 'bi-browser-opera';

    return 'bi-laptop';
}

/* =========================================================
   CLEANUP OLD ACTIVE SESSION RECORDS
   Active sessions older than 1 day are removed.
========================================================= */
$conn->query("
    DELETE FROM active_sessions
    WHERE last_activity < DATE_SUB(NOW(), INTERVAL 1 DAY)
");

/* =========================================================
   FETCH CURRENT / RECENT ACTIVE SESSIONS
========================================================= */
$active_sessions = [];

$active_query = "
    SELECT
        a.id AS session_record_id,
        a.user_id,
        a.session_id,
        a.ip_address,
        a.user_agent,
        a.browser,
        a.os,
        a.last_activity,
        a.created_at,
        u.username,
        u.role
    FROM active_sessions a
    INNER JOIN users u ON a.user_id = u.id
    WHERE a.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY a.last_activity DESC
";

$active_stmt = $conn->prepare($active_query);
if ($active_stmt) {
    $active_stmt->execute();
    $active_result = $active_stmt->get_result();
    while ($active_row = $active_result->fetch_assoc()) {
        $active_sessions[] = $active_row;
    }
    $active_stmt->close();
}

/* =========================================================
   ACTIVE / IDLE COUNTS
========================================================= */
$active_count = 0;
$idle_count   = 0;

foreach ($active_sessions as $session) {
    $last_activity_timestamp = strtotime($session['last_activity']);
    $seconds_ago = max(0, time() - $last_activity_timestamp);

    if ($seconds_ago <= 120) {
        $active_count++;
    } else {
        $idle_count++;
    }
}

/* =========================================================
   LOGIN HISTORY - PAGINATION & FILTERING
========================================================= */
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;

/* =========================================================
   SEARCH CONDITIONS
========================================================= */
$where_clause = "";
$params = [];
$types  = "";

if (!empty($search)) {
    $where_clause = "
        WHERE
            u.username LIKE ?
            OR ll.ip_address LIKE ?
            OR ll.city LIKE ?
    ";
    $search_param = "%" . $search . "%";
    $params = [$search_param, $search_param, $search_param];
    $types = "sss";
}

/* =========================================================
   TOTAL LOGIN HISTORY COUNT
========================================================= */
$count_query = "
    SELECT COUNT(*) AS total
    FROM login_logs ll
    INNER JOIN users u ON ll.user_id = u.id
    $where_clause
";

$count_stmt = $conn->prepare($count_query);
if ($count_stmt) {
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_rows = (int)$count_result->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_rows = 0;
}

/* =========================================================
   PAGINATION
========================================================= */
$total_pages = max(1, (int)ceil($total_rows / $limit));

/* =========================================================
   FETCH LOGIN HISTORY
========================================================= */
$query = "
    SELECT
        ll.*,
        u.username,
        u.role
    FROM login_logs ll
    INNER JOIN users u ON ll.user_id = u.id
    $where_clause
    ORDER BY ll.login_time DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $bind_params = array_merge($params, [$limit, $offset]);
        $stmt->bind_param($types . "ii", ...$bind_params);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
}

/* =========================================================
   PAGE CONTENT
========================================================= */
ob_start();
?>

<!-- =========================================================
     ACTIVE SESSION MONITOR
========================================================= -->
<div class="container-fluid px-4 mt-4">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        
        <!-- HEADER -->
        <div class="card-header bg-white border-bottom py-3 px-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1 fw-bold text-dark">
                        <i class="bi bi-broadcast-pin me-2 text-success"></i>
                        Currently Active Sessions
                    </h5>
                    <p class="text-muted small mb-0">
                        Monitor users with recent ERP activity and connected devices
                    </p>
                </div>

                <!-- STATUS SUMMARY -->
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">
                        <i class="bi bi-circle-fill me-1" style="font-size:7px;"></i>
                        <?= $active_count ?> Active
                    </span>

                    <?php if ($idle_count > 0): ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-2">
                            <i class="bi bi-circle-fill me-1" style="font-size:7px;"></i>
                            <?= $idle_count ?> Idle
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ACTIVE SESSION TABLE -->
        <div class="card-body p-0">
            <?php if (!empty($active_sessions)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase text-secondary fw-bold" style="font-size:0.7rem; letter-spacing:0.05em;">
                            <tr>
                                <th class="ps-4 py-3">User Account</th>
                                <th class="py-3">Location / IP</th>
                                <th class="py-3">Device / Browser</th>
                                <th class="py-3">Session Started</th>
                                <th class="py-3">Last Activity</th>
                                <th class="pe-4 py-3 text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($active_sessions as $session): ?>
                            <?php
                            $last_activity_timestamp = strtotime($session['last_activity']);
                            $created_timestamp = strtotime($session['created_at']);
                            $seconds_ago = max(0, time() - $last_activity_timestamp);

                            if ($seconds_ago <= 120) {
                                $status = "Active";
                                $status_color = "success";
                            } else {
                                $status = "Idle";
                                $status_color = "warning";
                            }
                            ?>
                            <tr>
                                <!-- USER -->
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <!-- STATUS DOT -->
                                        <div class="position-relative me-3" style="width:10px; height:10px;">
                                            <span class="position-absolute bg-<?= $status_color ?> rounded-circle" style="width:10px; height:10px; border:2px solid white; z-index:2;"></span>
                                            <?php if ($status === "Active"): ?>
                                                <span class="position-absolute bg-success rounded-circle active-pulse" style="width:10px; height:10px; opacity:.65;"></span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- USER DETAILS -->
                                        <div>
                                            <div class="fw-bold text-dark">
                                                @<?= htmlspecialchars($session['username']) ?>
                                            </div>
                                            <span class="badge bg-light text-secondary border fw-medium px-2" style="font-size:.65rem;">
                                                <?= htmlspecialchars($session['role']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- IP -->
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-globe2 me-2 text-primary"></i>
                                        <div>
                                            <div class="small fw-bold text-dark font-monospace">
                                                <?= htmlspecialchars($session['ip_address'] ?: 'Unknown') ?>
                                            </div>
                                            <div class="text-muted" style="font-size:.7rem;">
                                                Current Session
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- DEVICE / BROWSER -->
                                <td>
                                    <div class="small text-dark d-flex align-items-center mb-1">
                                        <i class="bi <?= getDeviceIcon($session['os']) ?> me-2 text-primary"></i>
                                        <span class="text-truncate" style="max-width:160px;" title="<?= htmlspecialchars($session['os']) ?>">
                                            <?= htmlspecialchars($session['os'] ?: 'Unknown OS') ?>
                                        </span>
                                    </div>
                                    <div class="text-muted d-flex align-items-center" style="font-size:.75rem;">
                                        <i class="bi <?= getDeviceIcon($session['browser']) ?> me-2 opacity-75"></i>
                                        <span class="text-truncate" style="max-width:160px;" title="<?= htmlspecialchars($session['browser']) ?>">
                                            <?= htmlspecialchars($session['browser'] ?: 'Unknown Browser') ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- SESSION START -->
                                <td>
                                    <div class="small fw-semibold text-dark">
                                        <?= date("d M Y", $created_timestamp) ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.75rem;">
                                        <?= date("h:i A", $created_timestamp) ?>
                                    </div>
                                </td>

                                <!-- LAST ACTIVITY -->
                                <td>
                                    <div class="small fw-semibold text-dark">
                                        <?= date("d M Y", $last_activity_timestamp) ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.75rem;">
                                        <?= date("h:i A", $last_activity_timestamp) ?>
                                        <span class="ms-1">
                                            ·
                                            <?php
                                            if ($seconds_ago < 60) {
                                                echo "just now";
                                            } elseif ($seconds_ago < 3600) {
                                                echo floor($seconds_ago / 60) . " min ago";
                                            } else {
                                                echo floor($seconds_ago / 3600) . " hr ago";
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- STATUS -->
                                <td class="pe-4 text-end">
                                    <span class="badge bg-<?= $status_color ?>-subtle text-<?= $status_color ?> border border-<?= $status_color ?>-subtle rounded-pill px-3 py-2">
                                        <i class="bi bi-circle-fill me-1" style="font-size:6px;"></i>
                                        <?= $status ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <!-- NO ACTIVE SESSIONS -->
                <div class="text-center py-5">
                    <i class="bi bi-person-x display-6 text-secondary opacity-25"></i>
                    <p class="mb-1 mt-2 fw-semibold text-secondary">
                        No recently active sessions
                    </p>
                    <small class="text-muted">
                        No ERP activity has been detected within the last 5 minutes.
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- =========================================================
     LOGIN HISTORY
========================================================= -->
<div class="container-fluid px-4 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        
        <!-- HEADER -->
        <div class="card-header bg-white py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom">
            <div>
                <h5 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-clock-history me-2 text-primary"></i>
                    Login History & Audits
                </h5>
                <p class="text-muted small mb-0">
                    Permanent record of user login activity, geographic footprints, and access environments.
                </p>
            </div>

            <!-- SEARCH -->
            <form method="GET" class="d-flex gap-2">
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control px-3 rounded-start-pill" placeholder="Search user, IP, city..." value="<?= htmlspecialchars($search) ?>">
                    <?php if (!empty($search)): ?>
                        <a href="?" class="btn btn-outline-secondary border-start-0 text-muted" title="Clear search">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                    <button class="btn btn-primary px-3 rounded-end-pill" type="submit">
                        <i class="bi bi-search me-1"></i>
                        Search
                    </button>
                </div>
            </form>
        </div>

        <!-- HISTORY TABLE -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase text-secondary fw-bold" style="font-size:0.7rem; letter-spacing:0.05em;">
                        <tr>
                            <th class="ps-4 py-3">User Account</th>
                            <th class="py-3">Location & IP Address</th>
                            <th class="py-3">Device / Operating System</th>
                            <th class="py-3">Login Time</th>
                            <th class="pe-4 py-3 text-end">Logout Time</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <!-- USER -->
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <!-- HISTORICAL DOT -->
                                        <div class="me-3" style="width:10px; height:10px;">
                                            <span class="bg-secondary rounded-circle d-block opacity-50" style="width:10px; height:10px; border:2px solid white;"></span>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">
                                                @<?= htmlspecialchars($row['username']) ?>
                                            </div>
                                            <span class="badge bg-light text-secondary border fw-medium px-2 py-0" style="font-size:.65rem;">
                                                <?= htmlspecialchars($row['role']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- LOCATION / IP -->
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($row['country_code'])): ?>
                                            <img src="https://flagcdn.com/16x12/<?= strtolower(htmlspecialchars($row['country_code'])) ?>.png" class="me-2 rounded-1 border shadow-sm" title="<?= htmlspecialchars($row['country']) ?>" alt="">
                                        <?php else: ?>
                                            <i class="bi bi-geo-alt me-2 text-muted"></i>
                                        <?php endif; ?>
                                        <div>
                                            <div class="small fw-bold text-dark font-monospace">
                                                <?= htmlspecialchars($row['ip_address']) ?>
                                            </div>
                                            <div class="text-muted" style="font-size:.7rem;">
                                                <?= htmlspecialchars($row['city'] ?: 'Unknown City') ?>, 
                                                <?= htmlspecialchars($row['country'] ?: 'Unknown Country') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- DEVICE -->
                                <td>
                                    <div class="small text-dark d-flex align-items-center mb-1">
                                        <i class="bi <?= getDeviceIcon($row['os']) ?> me-2 text-primary"></i>
                                        <span class="text-truncate" style="max-width:180px;" title="<?= htmlspecialchars($row['os']) ?>">
                                            <?= htmlspecialchars($row['os']) ?>
                                        </span>
                                    </div>
                                    <div class="text-muted d-flex align-items-center" style="font-size:.75rem;">
                                        <i class="bi <?= getDeviceIcon($row['browser']) ?> me-2 opacity-75"></i>
                                        <span class="text-truncate" style="max-width:180px;" title="<?= htmlspecialchars($row['browser']) ?>">
                                            <?= htmlspecialchars($row['browser']) ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- LOGIN TIME -->
                                <td>
                                    <div class="small fw-semibold text-dark">
                                        <?= date("d M Y", strtotime($row['login_time'])) ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.75rem;">
                                        <?= date("h:i A", strtotime($row['login_time'])) ?>
                                    </div>
                                </td>

                                <!-- LOGOUT TIME -->
                                <td class="pe-4 text-end">
                                    <?php if (!empty($row['logout_time'])): ?>
                                        <div class="small fw-semibold text-dark">
                                            <?= date("d M Y", strtotime($row['logout_time'])) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:.75rem;">
                                            <?= date("h:i A", strtotime($row['logout_time'])) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-2">
                                            <i class="bi bi-dash-circle me-1" style="font-size:.7rem;"></i>
                                            Not Logged Out
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <div class="py-4">
                                    <i class="bi bi-shield-exclamation display-6 text-opacity-25 d-block mb-2 text-secondary"></i>
                                    <p class="mb-0 fw-medium">
                                        No login matching records found.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- =================================================
             PAGINATION
        ================================================== -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white py-3 px-4 d-flex justify-content-between align-items-center border-top">
                <div class="text-muted small">
                    Showing page 
                    <span class="fw-bold text-dark"><?= $page ?></span> 
                    of 
                    <span class="fw-bold text-dark"><?= $total_pages ?></span> 
                    (
                    <span class="fw-bold text-dark"><?= $total_rows ?></span> 
                    total entries)
                </div>

                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <!-- PREVIOUS -->
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link rounded-start-pill px-3" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                Previous
                            </a>
                        </li>

                        <!-- PAGE NUMBERS -->
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <!-- NEXT -->
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link rounded-end-pill px-3" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                Next
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- =========================================================
     STYLING
========================================================= -->
<style>
@keyframes activePulse {
    0% {
        transform: scale(.95);
        box-shadow: 0 0 0 0 rgba(25, 135, 84, .65);
    }
    70% {
        transform: scale(1.6);
        box-shadow: 0 0 0 9px rgba(25, 135, 84, 0);
    }
    100% {
        transform: scale(.95);
        box-shadow: 0 0 0 0 rgba(25, 135, 84, 0);
    }
}

.active-pulse {
    animation: activePulse 2s infinite;
    z-index: 1;
}

.table > :not(caption) > * > * {
    padding: .85rem .75rem;
}

.card {
    transition: box-shadow .2s ease, transform .2s ease;
}

.badge {
    font-weight: 500;
}

@media (max-width: 1100px) {
    .table-responsive {
        overflow-x: auto;
    }
}

@media (max-width: 768px) {
    .container-fluid.px-4 {
        padding-left: .75rem !important;
        padding-right: .75rem !important;
    }
}
</style>

<?php
/* =========================================================
   OUTPUT THROUGH ADMIN LAYOUT
========================================================= */
$content = ob_get_clean();
include "adminlayout.php";
?>