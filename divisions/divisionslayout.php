<?php
if (!isset($page_title)) $page_title = "Stock Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> - Stock Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Security -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">

    <!-- Bootstrap & Icons -->
     <!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
    overflow-x: hidden;
    background-color: #f4f6f9;
    font-family: 'Segoe UI', sans-serif;
}

.wrapper {
    display: flex;
}

/* SIDEBAR */
#sidebar {
    min-width: 260px;
    max-width: 260px;
    min-height: 100vh;
    background: linear-gradient(180deg, #0f2027, #203a43, #2c5364);
    color: #fff;
    transition: all 0.3s ease;
}

#sidebar .nav-link {
    color: #cbd5e1;
    padding: 12px 15px;
    margin-bottom: 5px;
    border-radius: 8px;
    transition: all 0.3s;
    font-size: 15px;
}

#sidebar .nav-link:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
    padding-left: 20px;
}

#sidebar .nav-link.active {
    background: linear-gradient(90deg, #10b981, #059669);
    color: #fff;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}

/* MOBILE */
@media (max-width: 768px) {
    #sidebar {
        margin-left: -260px;
        position: fixed;
        z-index: 1000;
    }
}

/* CONTENT */
.top-navbar {
    background: #ffffff;
    border-radius: 12px;
    padding: 10px 20px;
}

/*stock item*/
.stock-card {
    transition: 0.25s ease;
}

.stock-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

.icon-wrapper {
    width: 55px;
    height: 55px;
    background: #f8f9fa;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: #198754;
}

.quantity-badge {
    background: #e8f5ee;
    color: #198754;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 30px;
}

</style>
</head>

<body>

<div class="wrapper">

    <!-- SIDEBAR -->
    <nav id="sidebar">
        <div class="p-4">
            <h4 class="text-white mb-4">📦 Stock Panel</h4>

            <ul class="nav nav-pills flex-column">

                

                

                <li>
                    <a href="../divisions/assign_asset.php" 
                       class="nav-link <?= basename($_SERVER['PHP_SELF'])=='../divisions/assign_asset.php'?'active':'' ?>">
                        📋 asset assign
                    </a>
                </li>

            </ul>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <div class="flex-fill p-4">

        <!-- TOP NAVBAR -->
        <nav class="navbar navbar-expand-lg bg-white shadow-sm px-4 py-3 border-bottom">
            <div class="container-fluid">

                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center gap-3">

    <!-- Home Icon -->
    <a href="../admin/admin_dashboard.php"
       class="text-dark fs-5 text-decoration-none"
       title="Go to Dashboard">
        <i class="bi bi-house-door"></i>
    </a>

    <!-- Small Vertical Divider -->
    <div style="width:1px; height:22px; background-color:#dee2e6;"></div>

    <!-- Page Title -->
    <span class="navbar-brand mb-0 h5 fw-semibold text-dark d-flex align-items-center">
    
        <?php if(isset($page_icon)): ?>
            <i class="bi <?= $page_icon ?> me-2"></i>
        <?php endif; ?>

        <?= htmlspecialchars($page_title ?? '') ?>
    </span>

</div>
                </div>

                <div class="d-flex align-items-center gap-3">

                    <span class="badge bg-light text-dark border px-3 py-2 fw-normal">
                        <i class="bi bi-calendar3"></i>
                        <?= date("d M Y"); ?>
                    </span>

                    <a href="../logout.php" 
                       class="btn btn-outline-danger btn-sm px-3 rounded-pill">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>

                </div>

            </div>
        </nav>
       

        <!-- Page Content -->
        <div class="mt-4">
            <?php if(isset($content)) echo $content; ?>
        </div>

    </div>
</div>

</body>
</html>