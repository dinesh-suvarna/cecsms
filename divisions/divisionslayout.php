<?php
if (!isset($page_title)) $page_title = "Division Dashboard";

// --- CACHE CONTROL ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$current_page = basename($_SERVER['PHP_SELF']);
$notif_division_id = $_SESSION['division_id'] ?? 0;

/**
 * REWRITTEN QUERY:
 * 1. Fetches Repair/Return status updates from asset_logs
 * 2. Fetches Dispatches from SuperAdmin where items don't have a Division Asset ID yet
 */
$notif_query = "
    (SELECT 
        al.id AS ref_id, 
        al.action_type, 
        al.created_at, 
        im.item_name, 
        'log' AS notif_source
     FROM asset_logs al
     INNER JOIN stock_details sd ON al.asset_id = sd.id
     INNER JOIN items_master im ON sd.stock_item_id = im.id
     LEFT JOIN division_assets da ON sd.id = da.stock_detail_id
     LEFT JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
     LEFT JOIN dispatch_master dm ON dd.dispatch_id = dm.id
     WHERE (dm.division_id = $notif_division_id OR al.performed_by IN (
         SELECT id FROM users WHERE division_id = $notif_division_id
     ))
     AND al.action_type IN ('assigned', 'return_requested', 'repair_requested')
     AND al.is_read = 0) 
    UNION ALL

    (SELECT 
        dm.id AS ref_id, 
        'NEW_DISPATCH' AS action_type, 
        dm.created_at AS created_at, 
        'Inventory Stock' AS item_name, 
        'dispatch' AS notif_source
     FROM dispatch_master dm
     INNER JOIN dispatch_details dd ON dm.id = dd.dispatch_id
     LEFT JOIN division_assets da ON dd.id = da.dispatch_detail_id
     WHERE dm.division_id = $notif_division_id 
     AND dm.status = 'active' 
     AND da.id IS NULL
     GROUP BY dm.id)
     ORDER BY created_at DESC";

$notifications = $conn->query($notif_query);
$notif_count = $notifications->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?> | StockFlow Division</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --sb-width: 280px;
            --primary-accent: #10b981;
            --bg-body: #f8fafc;
            --sidebar-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            overflow-x: hidden;
        }

        #sidebar {
            width: var(--sb-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            transition: transform 0.3s ease-in-out;
            z-index: 1030; 
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 1.35rem;
            color: var(--primary-accent);
            text-decoration: none;
        }

        #sidebar .nav-link {
            margin: 0.2rem 1rem;
            padding: 0.8rem 1.1rem;
            color: var(--text-muted);
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
        }

        #sidebar .nav-link.active {
            background: var(--primary-accent);
            color: #ffffff !important;
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.25);
        }

        .main-wrapper {
            margin-left: var(--sb-width);
            min-height: 100vh;
            padding: 1.5rem;
            transition: margin 0.3s ease-in-out;
            position: relative;
            z-index: 1;
        }

        .top-navbar {
            position: relative;
            z-index: 1050; 
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 0.75rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
            backdrop-filter: blur(8px);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 12px;
            transition: background 0.2s;
        }

        .user-profile:hover { background: rgba(0, 0, 0, 0.04); }
        .avatar { flex-shrink: 0; overflow: hidden; }

        .modal { z-index: 1065 !important; }
        .modal-backdrop { z-index: 1060 !important; }
        .dropdown-menu { z-index: 2000 !important; }
        .bg-emerald-soft { background-color: rgba(16, 185, 129, 0.1); }

        .animate-fade-in {
            animation: fadeIn 0.4s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 992px) {
            #sidebar { transform: translateX(-100%); z-index: 2000; }
            .main-wrapper { margin-left: 0; }
            #sidebar.show { transform: translateX(0); }
        }
    </style>
</head>
<body>

<nav id="sidebar">
    <a href="division_dashboard.php" class="sidebar-brand">
        <div class="bg-success text-white rounded-3 px-2 py-1 shadow-sm">
            <i class="bi bi-building"></i>
        </div>
        <span>Stock<span class="text-dark">Flow</span></span>
    </a>

    <div class="overflow-y-auto flex-grow-1">
        <div class="nav-group-label p-3 small fw-bold text-uppercase opacity-50">Overview</div>
        <div class="nav flex-column">
            <a href="division_dashboard.php" class="nav-link <?= ($current_page == 'division_dashboard.php') ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
        </div>

        <div class="nav-group-label p-3 small fw-bold text-uppercase opacity-50">Asset Management</div>
        <div class="nav flex-column">
            <a href="assign_asset.php" class="nav-link <?= ($current_page == 'assign_asset.php') ? 'active' : '' ?>">
                <i class="bi bi-tag"></i> Assign Asset ID
            </a>
            <a href="assigned_assets.php" class="nav-link <?= ($current_page == 'assigned_assets.php') ? 'active' : '' ?>">
                <i class="bi bi-check-circle"></i> View My Assets
            </a>
        </div>

        <div class="nav-group-label p-3 small fw-bold text-uppercase opacity-50">Maintenance</div>
        <div class="nav flex-column">
            <a href="asset_logs.php" class="nav-link <?= ($current_page == 'asset_logs.php') ? 'active' : '' ?>">
                <i class="bi bi-arrow-return-left"></i> Asset Audit Logs 
            </a>
        </div>
    </div>

    <div class="p-3 border-top mt-auto">
        <a href="../logout.php" class="btn btn-outline-danger w-100 rounded-pill btn-sm fw-bold">
            <i class="bi bi-power me-2"></i> Logout
        </a>
    </div>
</nav>

<main class="main-wrapper">
    <header class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light d-lg-none border-0 shadow-sm rounded-3" id="menuToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            
            <a href="/cecsms/index.php" class="nav-home-icon d-flex align-items-center justify-content-center text-decoration-none border rounded-3" style="width:38px; height:38px;" title="Main Admin Panel">
                <i class="bi bi-house-door text-muted"></i>
            </a>

            <div>
                <h5 class="mb-0 fw-bold text-dark lh-1 mb-1"><?= htmlspecialchars($page_title) ?></h5>
                <p class="text-muted mb-0 d-none d-md-block" style="font-size: 11px; letter-spacing: 0.02rem;">
                    Division asset tracking and status management.
                </p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="dropdown">
                <button class="btn btn-light border rounded-3 position-relative" style="width: 38px; height: 38px;" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell text-muted"></i>
                    <span id="notif-badge-container">
                        <?php if($notif_count > 0): ?>
                            <span id="notif-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                <?= $notif_count ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 p-0 overflow-hidden" style="width: 300px;">
                    <li class="p-3 border-bottom bg-light">
                        <h6 class="mb-0 fw-bold small">Notifications</h6>
                        <?php if($notif_count > 0): ?>
                            <a href="mark_all_read.php" class="text-success fw-bold text-decoration-none" style="font-size: 10px;">
                                Mark all as read
                            </a>
                        <?php endif; ?>
                    </li>
                    
                    
                    <div id="notif-list" style="max-height: 350px; overflow-y: auto;">
                        <?php if($notif_count > 0): ?>
                            <?php while($n = $notifications->fetch_assoc()): 
                                $type = $n['action_type'];
                                $ref_id = $n['ref_id'];
                                $source = $n['notif_source'];
                                $is_dispatch = ($type === 'NEW_DISPATCH');
                                
                                // Define $is_rejected based on the action_type string
                                $is_rejected = (strpos($type, 'rejected') !== false || strpos($type, 'REJECTED') !== false);

                                if ($is_dispatch) {
                                    $icon = 'bi-box-seam-fill text-primary';
                                    $bg = 'rgba(13, 110, 253, 0.05)';
                                    // Dispatches clear automatically once an Asset ID is assigned, 
                                    // so we can link directly to the assignment page.
                                    $link = 'assign_asset.php'; 
                                    $title = "New Dispatch Received";
                                    $message = "Items have arrived. Please <strong>Assign Asset IDs</strong>.";
                                } else {
                                    $icon = $is_rejected ? 'bi-x-circle-fill text-danger' : 'bi-check-circle-fill text-success';
                                    $bg = $is_rejected ? 'rgba(239, 68, 68, 0.05)' : 'rgba(16, 185, 129, 0.05)';
                                    
                                    // ROUTE THROUGH TRACKER:
                                    // Point to mark_notif_read.php so the DB updates is_read = 1 before redirecting
                                    $link = "mark_notif_read.php?id=" . urlencode($ref_id);
                                    
                                    $title = str_replace('_', ' ', $type);
                                    $message = "Your request for <strong>" . htmlspecialchars($n['item_name']) . "</strong> has been " . ($is_rejected ? 'rejected' : 'approved') . ".";
                                }
                            ?>
                            <li>
                                <a class="dropdown-item p-3 border-bottom d-flex gap-3 align-items-start" href="<?= $link ?>" style="background: <?= $bg ?>; white-space: normal;">
                                    <i class="bi <?= $icon ?> fs-5 mt-1"></i>
                                    <div>
                                        <p class="mb-1 small fw-bold text-dark"><?= strtoupper($title) ?></p>
                                        <p class="mb-1 extra-small text-muted" style="font-size: 11px;"><?= $message ?></p>
                                        <span class="text-muted italic" style="font-size: 10px;"><?= date('M d, H:i', strtotime($n['created_at'])) ?></span>
                                    </div>
                                </a>
                            </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="p-4 text-center text-muted small">
                                <i class="bi bi-bell-slash d-block fs-2 opacity-25 mb-2"></i>
                                No new updates.
                            </li>
                        <?php endif; ?>
                    </div>

                    <li>
                        <a class="dropdown-item text-center py-2 fw-bold text-success small border-top" href="asset_logs.php">
                            View All Activity
                        </a>
                    </li>
                </ul>
            </div>

            <div class="dropdown">
                <div class="user-profile shadow-sm border" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="text-end d-none d-md-block">
                        <p class="small fw-bold mb-0 text-dark"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></p>
                        <span class="badge bg-emerald-soft text-success" style="font-size: 9px;">
                            <?= htmlspecialchars($_SESSION['role'] ?? 'Division') ?>
                        </span>
                    </div>
                    <div class="avatar bg-light border rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-person-fill text-success fs-5"></i>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 p-2" style="min-width: 180px;">
                    <li>
                        <a class="dropdown-item py-2 text-danger fw-bold rounded-3" href="../logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <div class="animate-fade-in">
        <?php if (isset($main_content)) echo $main_content; ?>
        <?php if (!isset($main_content) && isset($content)) echo $content; ?>
    </div>
</main>

<?php if (isset($modal_html)) echo $modal_html; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // SIDEBAR TOGGLE
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if(menuToggle) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });
    }

    // AUTO-SCROLL SIDEBAR TO ACTIVE
    document.addEventListener("DOMContentLoaded", function() {
        const sidebarContainer = document.querySelector('.overflow-y-auto');
        const activeLink = document.querySelector('#sidebar .nav-link.active');
        if (activeLink && sidebarContainer) {
            setTimeout(() => {
                const scrollPos = activeLink.offsetTop - (sidebarContainer.clientHeight / 2) + (activeLink.clientHeight / 2);
                sidebarContainer.scrollTo({ top: scrollPos, behavior: 'smooth' });
            }, 100);
        }
    });

    // CACHE RELOAD FIX
    window.onpageshow = function(event) {
        if (event.persisted) { window.location.reload(); }
    };

    // LIVE NOTIFICATION POLLING
    function fetchNotifications() {
        fetch('get_notifications.php')
            .then(response => response.json())
            .then(data => {
                // Update Badge Container
                const badgeContainer = document.getElementById('notif-badge-container');
                if (data.count > 0) {
                    badgeContainer.innerHTML = `<span id="notif-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">${data.count}</span>`;
                } else {
                    badgeContainer.innerHTML = '';
                }

                // Update List
                document.getElementById('notif-list').innerHTML = data.html;
            })
            .catch(error => console.error('Live Notif Error:', error));
    }

    // Poll every 15 seconds
    setInterval(fetchNotifications, 15000);
</script>

<?php if (isset($_SESSION['swal_msg'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: '<?= $_SESSION['swal_type'] ?? "success" ?>',
            title: '<?= ($_SESSION['swal_type'] == "success") ? "Done!" : "Notice" ?>',
            text: '<?= $_SESSION['swal_msg'] ?>',
            timer: 3000,
            showConfirmButton: false,
            timerProgressBar: true
        });
    });
</script>
<?php 
    unset($_SESSION['swal_msg']);
    unset($_SESSION['swal_type']);
endif; 
?>
</body>
</html>