<?php
if (!isset($page_title)) $page_title = "Division Dashboard";

// --- CACHE CONTROL ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$current_page = basename($_SERVER['PHP_SELF']);
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

        /* --- SIDEBAR --- */
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

        /* --- MAIN CONTENT --- */
        .main-wrapper {
            margin-left: var(--sb-width);
            min-height: 100vh;
            padding: 1.5rem;
            transition: margin 0.3s ease-in-out;
            position: relative;
            z-index: 1; /* Keep content below modals */
        }

        /* 1. Fix the layering issue: Header must be higher than content */
        .top-navbar {
            position: relative;
            z-index: 1050; /* Higher than main-wrapper's z-index */
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

        /* 2. Force dropdowns to the absolute top of the stack */
        .dropdown-menu {
            z-index: 3000 !important; 
        }

        /* 3. Profile layout alignment fix */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 12px;
            transition: background 0.2s;
        }

        .user-profile:hover {
            background: rgba(0, 0, 0, 0.04);
        }
        /* Ensure the avatar stays perfectly round */
        .avatar {
            flex-shrink: 0;
            overflow: hidden;
        }

        /* Optional: Make the badge look a bit sharper */
        .user-profile .badge {
            padding: 3px 6px;
            font-weight: 600;
        }
        /* --- THE "CATCH" FROM MASTERLAYOUT: MODAL FIXES --- */
        .modal {
            z-index: 1065 !important;
        }
        .modal-backdrop {
            z-index: 1060 !important;
        }
        .dropdown-menu {
            z-index: 2000 !important;
        }
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
                <i class="bi bi-speedometer2"></i> Dashboard
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
            <a href="returned_assets.php" class="nav-link <?= ($current_page == 'returned_assets.php') ? 'active' : '' ?>">
                <i class="bi bi-arrow-return-left"></i> Returned Assets
            </a>
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
            
            <a href="../admin/admin_dashboard.php" class="nav-home-icon d-flex align-items-center justify-content-center text-decoration-none border rounded-3" style="width:38px; height:38px;" title="Main Admin Panel">
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
            <div class="d-none d-sm-flex align-items-center gap-2 text-muted small border-end pe-3">
                <i class="bi bi-calendar-event"></i>
                <?= date('D, M j, Y') ?>
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
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if(menuToggle) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });
    }

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

    window.onpageshow = function(event) {
        if (event.persisted) { window.location.reload(); }
    };
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
    // CRITICAL: Clear the session so the alert doesn't repeat on refresh
    unset($_SESSION['swal_msg']);
    unset($_SESSION['swal_type']);
endif; 
?>
</body>
</html>