<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/session.php";


$role = $_SESSION['role'] ?? '';

/* TOTAL STOCK ITEMS */
$total_stock = 0;
$q = mysqli_query($conn,"SELECT COUNT(*) total FROM stock_details");
if($q){
$row = mysqli_fetch_assoc($q);
$total_stock = $row['total'];
}

/* TOTAL DISPATCHED ASSETS */
$total_dispatched = 0;
$q = mysqli_query($conn,"SELECT COUNT(*) total FROM dispatch_details");
if($q){
$row = mysqli_fetch_assoc($q);
$total_dispatched = $row['total'];
}

/* ACTIVE ASSETS IN UNITS */
$total_active_assets = 0;
$q = mysqli_query($conn,"SELECT COUNT(*) total FROM unit_assets WHERE status='active'");
if($q){
$row = mysqli_fetch_assoc($q);
$total_active_assets = $row['total'];
}

/* ASSETS RETURNED */
$total_returned = 0;
$q = mysqli_query($conn,"SELECT COUNT(*) total FROM unit_assets WHERE status='returned'");
if($q){
$row = mysqli_fetch_assoc($q);
$total_returned = $row['total'];
}

/* E-WASTE ITEMS */
$total_ewaste = 0;
$q = mysqli_query($conn,"SELECT COUNT(*) total FROM unit_assets WHERE status='ewaste'");
if($q){
$row = mysqli_fetch_assoc($q);
$total_ewaste = $row['total'];
}

/* ACTIVE USERS */
$total_users = 0;
$q = mysqli_query($conn,"SELECT COUNT(*) total FROM users WHERE status='Active'");
if($q){
$row = mysqli_fetch_assoc($q);
$total_users = $row['total'];
}

/* DISPATCH TODAY */
$dispatch_today = 0;
$q = mysqli_query($conn,"
SELECT COUNT(*) total 
FROM dispatch_master 
WHERE dispatch_date = CURDATE()
");
if($q){
$row = mysqli_fetch_assoc($q);
$dispatch_today = $row['total'];
}
?>

<style>

.dashboard-wrapper{
padding:20px 0;
}

/* CARD */

.dashboard-card{
border-radius:18px;
background:#ffffff;
transition:all .35s ease;
height:100%;
border:1px solid #e5e7eb !important;
position:relative;
overflow:hidden;
}

[data-bs-theme="dark"] .dashboard-card{
background:#1e293b;
border-color:#334155 !important;
}

.dashboard-card::before{
content:"";
position:absolute;
top:0;
left:0;
right:0;
height:4px;
background:linear-gradient(90deg,var(--hover-color),transparent);
opacity:.8;
}

/* HOVER */

.dashboard-card:hover{
transform:translateY(-6px);
box-shadow:0 18px 35px rgba(0,0,0,0.08);
border-color:var(--hover-color) !important;
}

/* ICON */

.dashboard-icon-container{
width:65px;
height:65px;
border-radius:16px;
display:flex;
align-items:center;
justify-content:center;
margin:0 auto 1.3rem auto;
font-size:1.7rem;
background:var(--soft-bg);
color:var(--hover-color);
box-shadow:0 6px 14px rgba(0,0,0,0.05);
transition:.3s;
}

.dashboard-card:hover .dashboard-icon-container{
transform:scale(1.08);
}

/* BUTTON */

.dashboard-btn{
border-radius:12px;
padding:11px;
font-weight:700;
width:100%;
text-transform:uppercase;
font-size:0.72rem;
letter-spacing:1px;
border:none;
background:var(--hover-color);
color:white !important;
transition:.25s;
}

.dashboard-btn:hover{
filter:brightness(1.1);
transform:translateY(-1px);
}

/* THEMES */

.theme-info{
--hover-color:#0dcaf0;
--soft-bg:rgba(13,202,240,.15);
}

.theme-primary{
--hover-color:#6366f1;
--soft-bg:rgba(99,102,241,.15);
}

.theme-warning{
--hover-color:#f59e0b;
--soft-bg:rgba(245,158,11,.2);
}

.theme-danger{
--hover-color:#ef4444;
--soft-bg:rgba(239,68,68,.15);
}

.theme-success{
--hover-color:#22c55e;
--soft-bg:rgba(34,197,94,.15);
}

.theme-dark{
--hover-color:#334155;
--soft-bg:rgba(51,65,85,.15);
}

/* TEXT */

.card-title{
font-weight:700;
margin-top:10px;
font-size:1rem;
}

.card-text{
font-size:.85rem;
margin-bottom:1.6rem;
color:#6b7280;
}

[data-bs-theme="dark"] .card-text{
color:#94a3b8;
}

/* STATS BAR */

.stats-bar{
margin-bottom:25px;
}

.stat-card{
background:white;
border-radius:14px;
padding:18px;
border:1px solid #e5e7eb;
display:flex;
align-items:center;
justify-content:space-between;
}

[data-bs-theme="dark"] .stat-card{
background:#1e293b;
border-color:#334155;
}

.stat-icon{
width:42px;
height:42px;
border-radius:10px;
display:flex;
align-items:center;
justify-content:center;
font-size:1.2rem;
background:#eef2ff;
color:#6366f1;
}

.stat-number{
font-size:1.3rem;
font-weight:700;
}

.stat-label{
font-size:.75rem;
color:#64748b;
}

</style>


<div class="dashboard-wrapper">

<!-- STATS -->
<div class="row g-3 stats-bar">

<div class="col-md-3">
<div class="stat-card">
<div>
<div class="stat-number"><?= $total_stock ?></div>
<div class="stat-label">Total Stock Items</div>
</div>
<div class="stat-icon"><i class="bi bi-box"></i></div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card">
<div>
<div class="stat-number"><?= $total_active_assets ?></div>
<div class="stat-label">Active Assets</div>
</div>
<div class="stat-icon"><i class="bi bi-pc-display"></i></div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card">
<div>
<div class="stat-number"><?= $dispatch_today ?></div>
<div class="stat-label">Dispatch Today</div>
</div>
<div class="stat-icon"><i class="bi bi-truck"></i></div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card">
<div>
<div class="stat-number"><?= $total_users ?></div>
<div class="stat-label">Active Users</div>
</div>
<div class="stat-icon"><i class="bi bi-people"></i></div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card">
<div>
<div class="stat-number"><?= $total_dispatched ?></div>
<div class="stat-label">Total Dispatches</div>
</div>
<div class="stat-icon"><i class="bi bi-box-arrow-up"></i></div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card">
<div>
<div class="stat-number"><?= $total_returned ?></div>
<div class="stat-label">Returned Assets</div>
</div>
<div class="stat-icon"><i class="bi bi-arrow-return-left"></i></div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card">
<div>
<div class="stat-number"><?= $total_ewaste ?></div>
<div class="stat-label">E-Waste</div>
</div>
<div class="stat-icon"><i class="bi bi-recycle"></i></div>
</div>
</div>

</div>

</div>


<div class="row g-4">

<?php if(in_array($role,['Admin','SuperAdmin'])): ?>
<div class="col-lg-4 col-md-6">
<div class="card dashboard-card theme-info border-0 shadow-sm">
<div class="card-body text-center p-4">
<div class="dashboard-icon-container"><i class="bi bi-people"></i></div>
<h5 class="card-title">User Management</h5>
<p class="card-text">Manage system users.</p>
<a href="/cecsms/users/manage_users.php" class="btn dashboard-btn">Open</a>
</div>
</div>
</div>
<?php endif; ?>


<?php if(in_array($role,['SuperAdmin'])): ?>

<div class="col-lg-4 col-md-6">
<div class="card dashboard-card theme-primary border-0 shadow-sm">
<div class="card-body text-center p-4">
<div class="dashboard-icon-container"><i class="bi bi-database"></i></div>
<h5 class="card-title">Master Data</h5>
<p class="card-text">Manage core system data.</p>
<a href="/cecsms/master/master_dashboard.php" class="btn dashboard-btn">Manage</a>
</div>
</div>
</div>

<div class="col-lg-4 col-md-6">
<div class="card dashboard-card theme-primary border-0 shadow-sm">
<div class="card-body text-center p-4">
<div class="dashboard-icon-container"><i class="bi bi-tools"></i></div>
<h5 class="card-title">Service Management</h5>
<p class="card-text">Manage services.</p>
<a href="/cecsms/services/index.php" class="btn dashboard-btn">Open</a>
</div>
</div>
</div>

<?php endif; ?>


<div class="col-lg-4 col-md-6">
<div class="card dashboard-card theme-dark border-0 shadow-sm">
<div class="card-body text-center p-4">
<div class="dashboard-icon-container"><i class="bi bi-pc-display"></i></div>
<h5 class="card-title">Computer Stock</h5>
<p class="card-text">Manage computer inventory.</p>
<a href="/cecsms/stock/dashboard.php" class="btn dashboard-btn">Open</a>
</div>
</div>
</div>


<div class="col-lg-4 col-md-6">
<div class="card dashboard-card theme-warning border-0 shadow-sm">
<div class="card-body text-center p-4">
<div class="dashboard-icon-container"><i class="bi bi-columns-gap"></i></div>
<h5 class="card-title">Furniture Stock</h5>
<p class="card-text">Manage furniture inventory.</p>
<a href="/cecsms/furniture/index.php" class="btn dashboard-btn">Open</a>
</div>
</div>
</div>


<div class="col-lg-4 col-md-6">
<div class="card dashboard-card theme-danger border-0 shadow-sm">
<div class="card-body text-center p-4">
<div class="dashboard-icon-container"><i class="bi bi-lightning-charge"></i></div>
<h5 class="card-title">Electronics Stock</h5>
<p class="card-text">Manage electronic equipment.</p>
<a href="/cecsms/electronics/index.php" class="btn dashboard-btn">Open</a>
</div>
</div>
</div>


<?php if(in_array($role,['Admin','SuperAdmin'])): ?>

<div class="col-lg-4 col-md-6">
<div class="card dashboard-card theme-success border-0 shadow-sm">
<div class="card-body text-center p-4">
<div class="dashboard-icon-container"><i class="bi bi-recycle"></i></div>
<h5 class="card-title">E-Waste Management</h5>
<p class="card-text">Manage e-waste inventory.</p>
<a href="/cecsms/ewaste/index.php" class="btn dashboard-btn">Open</a>
</div>
</div>
</div>

<?php endif; ?>

</div>

</div>