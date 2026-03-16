<?php 
$role = $_SESSION["role"] ?? ''; 
if (!isset($page_title)) $page_title = "Admin Panel";

/* Detect current page */
$current_page = basename($_SERVER['PHP_SELF']);

/* Prevent caching */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title) ?> | Admin Portal</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>

:root{
--sidebar-width:270px;
--primary-indigo:#6366f1;
}

/* THEMES */

[data-bs-theme="light"]{
--bg-body:#f8fafc;
--sidebar-bg:#ffffff;
--sidebar-text:#64748b;
--topbar-bg:#ffffff;
--border-color:#e2e8f0;
}

[data-bs-theme="dark"]{
--bg-body:#0f172a;
--sidebar-bg:#1e293b;
--sidebar-text:#94a3b8;
--topbar-bg:#1e293b;
--border-color:#334155;
}

body{
background:var(--bg-body);
font-family:'Inter',sans-serif;
transition:.3s;
}

/* SIDEBAR */

#sidebar{
width:var(--sidebar-width);
min-height:100vh;
background:var(--sidebar-bg);
border-right:1px solid var(--border-color);
position:fixed;
transition:.3s;
z-index:1040;
display:flex;
flex-direction:column;
}

.sidebar-logo{
padding:1.5rem;
font-weight:700;
font-size:1.25rem;
color:var(--primary-indigo);
display:flex;
align-items:center;
gap:10px;
}

.section-label{
padding:1.2rem 1.5rem .5rem;
font-size:.65rem;
text-transform:uppercase;
letter-spacing:.05rem;
font-weight:700;
color:var(--sidebar-text);
opacity:.6;
}

#sidebar .nav-link{
color:var(--sidebar-text);
padding:.75rem 1.5rem;
margin:.2rem 1rem;
border-radius:10px;
display:flex;
align-items:center;
gap:12px;
font-size:.9rem;
font-weight:500;
transition:.2s;
}

#sidebar .nav-link:hover{
background:rgba(99,102,241,.1);
color:var(--primary-indigo);
}

#sidebar .nav-link.active{
background:var(--primary-indigo);
color:#fff !important;
box-shadow:0 4px 12px rgba(99,102,241,.3);
}

/* SUBMENU */

#sidebar .collapse .nav-link{
font-size:.85rem;
padding:6px 1.5rem;
opacity:.85;
}

#sidebar .collapse .nav-link:hover{
opacity:1;
}

/* MAIN */

.main-content{
margin-left:var(--sidebar-width);
padding:1.5rem;
transition:.3s;
}

.topbar{
background:var(--topbar-bg);
border-radius:16px;
padding:.75rem 1.5rem;
margin-bottom:2rem;
border:1px solid var(--border-color);
box-shadow:0 4px 6px rgba(0,0,0,.05);
}

/* MOBILE */

@media(max-width:992px){
#sidebar{margin-left:-270px;}
.main-content{margin-left:0;}
#sidebar.active{margin-left:0;}
}

</style>
</head>

<body>

<nav id="sidebar">

<div class="sidebar-logo">
<div class="bg-primary text-white rounded-3 px-2 py-1">
<i class="bi bi-shield-lock"></i>
</div>
<span>Admin<span class="text-dark">Center</span></span>
</div>

<div class="section-label">General</div>

<ul class="nav flex-column">

<li class="nav-item">
<a href="/cecsms/index.php"
class="nav-link <?= ($current_page=='admin_dashboard.php')?'active':'' ?>">
<i class="bi bi-speedometer2"></i>
Dashboard
</a>
</li>

</ul>

<?php if(in_array($role,['SuperAdmin','Admin'])): ?>

<div class="section-label">System Control</div>

<ul class="nav flex-column">

<li class="nav-item">
<a href="/cecsms/users/manage_users.php"
class="nav-link <?= ($current_page=='manage_users.php')?'active':'' ?>">
<i class="bi bi-people"></i>
User Management
</a>
</li>

<li class="nav-item">
<a href="/cecsms/services/index.php"
class="nav-link <?= (strpos($_SERVER['PHP_SELF'],'services'))?'active':'' ?>">
<i class="bi bi-tools"></i>
Services
</a>
</li>

<li class="nav-item">
<a href="/cecsms/master/master_dashboard.php"
class="nav-link <?= ($current_page=='master_dashboard.php')?'active':'' ?>">
<i class="bi bi-database-gear"></i>
Master Data
</a>
</li>

</ul>

<?php endif; ?>

<div class="section-label">Inventory Modules</div>

<ul class="nav flex-column">

<li class="nav-item">

<a class="nav-link d-flex justify-content-between align-items-center
<?= in_array($current_page,['dashboard.php','dispatch.php','dispatch_report.php'])?'active':'' ?>"
data-bs-toggle="collapse"
href="#stockMenu">

<span>
<i class="bi bi-pc-display"></i>
Computer Stock
</span>

<i class="bi bi-chevron-down small"></i>

</a>

<div class="collapse
<?= in_array($current_page,['dashboard.php','dispatch.php','dispatch_report.php'])?'show':'' ?>"
id="stockMenu">

<ul class="nav flex-column ms-3">

<li class="nav-item">
<a href="/cecsms/stock/dashboard.php"
class="nav-link <?= ($current_page=='dashboard.php')?'active':'' ?>">
Stock Dashboard
</a>
</li>

<li class="nav-item">
<a href="/cecsms/stock/dispatch.php"
class="nav-link <?= ($current_page=='dispatch.php')?'active':'' ?>">
Dispatch Assets
</a>
</li>

<li class="nav-item">
<a href="/cecsms/stock/dispatch_report.php"
class="nav-link <?= ($current_page=='dispatch_report.php')?'active':'' ?>">
Dispatch Report
</a>
</li>

</ul>

</div>

</li>

<?php if(in_array($role,['SuperAdmin','Admin'])): ?>

<li class="nav-item">
<a href="/cecsms/ewaste/index.php"
class="nav-link <?= (strpos($_SERVER['PHP_SELF'],'ewaste'))?'active':'' ?>">
<i class="bi bi-recycle"></i>
E-Waste
</a>
</li>

<?php endif; ?>

</ul>

<div class="mt-auto p-3">

<a href="/cecsms/admin/logout.php"
class="btn btn-outline-danger w-100 rounded-3 btn-sm">

<i class="bi bi-box-arrow-right me-2"></i>
Logout

</a>

</div>

</nav>

<div class="main-content">

<header class="topbar d-flex justify-content-between align-items-center">

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

</header>

<div class="container-fluid p-0">
<?php if(isset($content)) echo $content; ?>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>

/* Theme toggle */

const themeToggler=document.getElementById('themeToggler');
const themeIcon=document.getElementById('themeIcon');
const html=document.documentElement;

const savedTheme=localStorage.getItem('admin_theme')||'light';

html.setAttribute('data-bs-theme',savedTheme);
updateTheme(savedTheme);

themeToggler.addEventListener('click',()=>{

const current=html.getAttribute('data-bs-theme');
const newTheme=current==='light'?'dark':'light';

html.setAttribute('data-bs-theme',newTheme);
localStorage.setItem('admin_theme',newTheme);

updateTheme(newTheme);

});

function updateTheme(theme){

themeIcon.className=theme==='light'
?'bi bi-moon-stars-fill fs-5'
:'bi bi-sun-fill fs-5 text-warning';

}

/* Sidebar toggle */

document.getElementById('sidebarToggle')
?.addEventListener('click',()=>{

document.getElementById('sidebar')
.classList.toggle('active');

});

/* Reload when using browser back */

window.addEventListener("pageshow",function(event){

if(event.persisted) location.reload();

});

</script>

</body>
</html>