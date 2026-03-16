<?php
if (!isset($page_title)) $page_title = "Stock Dashboard";
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> | Stock Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 270px;
            --primary-accent: #10b981; /* Emerald Green for Stock */
            --bg-body: #f8fafc;
            --sidebar-bg: #ffffff;
            --sidebar-text: #64748b;
            --topbar-bg: #ffffff;
            --border-color: #e2e8f0;
        }

        body {
            background: var(--bg-body);
            font-family: 'Inter', sans-serif;
            color: #1e293b;
        }

        /* SIDEBAR */
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            transition: 0.3s;
            overflow-y: auto;
        }

        .sidebar-logo {
            padding: 1.5rem;
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--primary-accent);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-label {
            padding: 1.2rem 1.5rem .5rem;
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: .05rem;
            font-weight: 700;
            color: var(--sidebar-text);
            opacity: .6;
        }

        #sidebar .nav-link {
            color: var(--sidebar-text);
            padding: .7rem 1.5rem;
            margin: .2rem 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: .9rem;
            font-weight: 500;
            transition: .2s;
        }

        #sidebar .nav-link:hover {
            background: rgba(16, 185, 129, .08);
            color: var(--primary-accent);
        }

        #sidebar .nav-link.active {
            background: var(--primary-accent);
            color: #fff !important;
            box-shadow: 0 4px 12px rgba(16, 185, 129, .2);
        }

        /* MAIN CONTENT AREA */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem;
            min-height: 100vh;
            transition: 0.3s;
        }

        .topbar {
            background: var(--topbar-bg);
            border-radius: 12px;
            padding: .75rem 1.25rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0,0,0,.02);
        }

        @media (max-width: 992px) {
            .main-content { margin-left: 0; }
            #sidebar { margin-left: calc(-1 * var(--sidebar-width)); }
            #sidebar.active { margin-left: 0; }
        }
    </style>
</head>
<body>

<nav id="sidebar">
    <div class="sidebar-logo">
        <div class="bg-success text-white rounded-3 px-2 py-1">
            <i class="bi bi-box-seam"></i>
        </div>
        <span>Computer<span class="text-dark">Stock</span></span>
    </div>

    <div class="section-label">Overview</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a href="master_dashboard.php" class="nav-link <?= ($current_page=='master_dashboard.php')?'active':'' ?>">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
        </li>
    </ul>

    <div class="section-label">Stock Inventory</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a href="add_items_master.php" class="nav-link <?= ($current_page=='add_items_master.php')?'active':'' ?>">
                <i class="bi bi-plus-circle"></i> Add Stock Item
            </a>
        </li>
        <li class="nav-item">
            <a href="manage_items_master.php" class="nav-link <?= ($current_page=='manage_items_master.php')?'active':'' ?>">
                <i class="bi bi-clipboard-data"></i> View Stock Item
            </a>
        </li>
        <li class="nav-item">
            <a href="stock_specifications.php" class="nav-link <?= ($current_page=='stock_specifications.php')?'active':'' ?>">
                <i class="bi bi-gear-wide"></i> Add Specifications
            </a>
        </li>
        <li class="nav-item">
            <a href="view_stock_specifications.php" class="nav-link <?= ($current_page=='view_stock_specifications.php')?'active':'' ?>">
                <i class="bi bi-eye"></i> View Specifications
            </a>
        </li>
    </ul>

    <div class="section-label">Master Data</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a href="add_institute.php" class="nav-link <?= ($current_page=='add_institute.php')?'active':'' ?>">
                <i class="bi bi-building"></i> Add Institution
            </a>
        </li>
        <li class="nav-item">
            <a href="edit_delete_institute.php" class="nav-link <?= ($current_page=='edit_delete_institute.php')?'active':'' ?>">
                <i class="bi bi-houses"></i> View Institutions
            </a>
        </li>

        <li class="nav-item">
            <a href="division_add.php" class="nav-link <?= ($current_page=='division_add.php')?'active':'' ?>">
                <i class="bi bi-diagram-3"></i> Add Division
            </a>
        </li>
        <li class="nav-item">
            <a href="division_list.php" class="nav-link <?= ($current_page=='division_list.php')?'active':'' ?>">
                <i class="bi bi-card-list"></i> View Divisions
            </a>
        </li>

        <li class="nav-item">
            <a href="unit_add.php" class="nav-link <?= ($current_page=='unit_add.php')?'active':'' ?>">
                <i class="bi bi-unity"></i> Add Unit
            </a>
        </li>
        <li class="nav-item">
            <a href="unit_list.php" class="nav-link <?= ($current_page=='unit_list.php')?'active':'' ?>">
                <i class="bi bi-file-text"></i> View Units
            </a>
        </li>
    </ul>

    <div class="mt-4 mb-5 px-3">
        <a href="../logout.php" class="btn btn-outline-danger w-100 btn-sm rounded-pill shadow-sm">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>
    </div>
</nav>



<div class="d-flex align-items-center gap-2">

<button class="btn btn-light d-lg-none" id="sidebarToggle">
<i class="bi bi-list"></i>
</button>

<div>
<h6 class="mb-0 fw-bold"><?= htmlspecialchars($page_title) ?></h6>
<small class="text-muted d-none d-sm-block" style="font-size:11px;">
System Administration
</small>
</div>

</div>

<div class="d-flex align-items-center gap-3">

<button class="btn btn-link text-muted p-0" id="themeToggler">
<i class="bi bi-moon-stars-fill fs-5" id="themeIcon"></i>
</button>

<div class="vr mx-1 opacity-25"></div>

<div class="dropdown">

<div class="d-flex align-items-center gap-2"
data-bs-toggle="dropdown"
style="cursor:pointer">

<div class="text-end d-none d-md-block">

<div class="fw-bold small mb-0">
<?= htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
</div>

<span class="badge bg-primary text-white"
style="font-size:10px;">
<?= $role ?>
</span>

</div>

<div class="bg-light rounded-circle p-2 border shadow-sm">
<i class="bi bi-person"></i>
</div>

</div>

<ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-3">

<li>
<a class="dropdown-item py-2"
href="/cecsms/admin/logout.php">
<i class="bi bi-box-arrow-right me-2"></i>
Logout
</a>
</li>

</ul>

</div>

</div>




</body>
</html>