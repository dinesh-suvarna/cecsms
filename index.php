<?php
require_once __DIR__ . "/admin/auth.php";

$page_title = "Computer Equipment & Consumables Stock Management System";

ob_start();
require_once __DIR__ . "/admin/admin_dashboard.php";
$content = ob_get_clean();

require_once __DIR__ . "/admin/adminlayout.php";
?>