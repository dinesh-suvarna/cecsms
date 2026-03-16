<?php
require_once "../includes/session.php";
require_once "../includes/security_headers.php";
require_once "../includes/csrf.php";
require_once "../config/db.php";

/* Destroy previous session if exists */
if (isset($_SESSION["user_id"])) {
    $_SESSION = [];
    session_unset();
    session_destroy();
    session_start();
}

$error = "";

/* Timeout message */
if (isset($_GET['timeout'])) {
    $error = "Session expired due to inactivity. Please login again.";
}

/* LOGIN PROCESS */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        die("CSRF validation failed!");
    }

    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=? AND status='Active'");
    $stmt->bind_param("s",$username);
    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows === 1){

        $user = $result->fetch_assoc();

        if(password_verify($password,$user["password"])){

            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["institution_id"] = $user["institution_id"];
            $_SESSION["last_activity"] = time();

            header("Location: ../index.php");
            exit();

        }else{
            $error = "Invalid Password!";
        }

    }else{
        $error = "User not found or inactive!";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<title>Stock Management System | Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>

:root{
--primary:#0f172a;
--accent:#38bdf8;
--glass:rgba(255,255,255,.92);
}

body{
margin:0;
font-family:'Inter',sans-serif;
height:100vh;
overflow:hidden;
background:linear-gradient(-45deg,#0f172a,#1e293b,#020617,#0f172a);
background-size:400% 400%;
animation:gradientMove 12s ease infinite;
}

@keyframes gradientMove{
0%{background-position:0% 50%;}
50%{background-position:100% 50%;}
100%{background-position:0% 50%;}
}

/* LEFT SIDE */

.left-side{
background:radial-gradient(circle at top left,#1e293b,#020617);
color:white;
display:flex;
flex-direction:column;
justify-content:center;
align-items:center;
padding:60px;
}

.left-side i{
font-size:80px;
background:linear-gradient(45deg,#38bdf8,#818cf8);
background-clip:text;
-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
margin-bottom:20px;
}

.left-side h2{
font-weight:700;
font-size:2.2rem;
}

/* RIGHT SIDE */

.right-side{
display:flex;
align-items:center;
justify-content:center;
}

/* LOGIN CARD */

.login-card{
width:100%;
max-width:420px;
padding:2px;
border-radius:24px;
background:linear-gradient(120deg,#38bdf8,#818cf8,#22c55e,#38bdf8);
background-size:300% 300%;
animation:borderGlow 6s linear infinite;
}

.login-inner{
background:var(--glass);
border-radius:22px;
padding:48px;
box-shadow:0 20px 40px rgba(0,0,0,.15);
animation:cardFade .7s ease;
}

@keyframes borderGlow{
0%{background-position:0% 50%;}
50%{background-position:100% 50%;}
100%{background-position:0% 50%;}
}

@keyframes cardFade{
from{opacity:0;transform:translateY(30px);}
to{opacity:1;transform:translateY(0);}
}

.system-title{
font-weight:700;
color:var(--primary);
}

/* INPUTS */

.form-label{
font-weight:600;
font-size:.85rem;
text-transform:uppercase;
letter-spacing:.5px;
}

.input-group-text{
background:transparent;
border-right:none;
color:#94a3b8;
}

.form-control{
border-left:none;
border-radius:12px;
padding:12px;
background:#f1f5f9;
}

.form-control:focus{
box-shadow:0 0 0 4px rgba(56,189,248,.15);
}

/* BUTTON */

.btn-custom{
background:linear-gradient(135deg,#0f172a,#1e293b);
border:none;
border-radius:12px;
padding:14px;
font-weight:600;
color:white;
transition:.25s;
}

.btn-custom:hover{
background:linear-gradient(135deg,#1e293b,#020617);
transform:translateY(-2px);
box-shadow:0 12px 25px rgba(0,0,0,.25);
color:white;
}

/* LOADER */

.login-loader{
position:fixed;
inset:0;
background:rgba(0,0,0,.5);
display:flex;
align-items:center;
justify-content:center;
backdrop-filter:blur(6px);
opacity:0;
visibility:hidden;
transition:.3s;
z-index:9999;
}

.login-loader.show{
opacity:1;
visibility:visible;
}

.loader-box{
background:white;
padding:40px;
border-radius:16px;
text-align:center;
}

/* RESPONSIVE */

@media(max-width:768px){
.left-side{display:none;}
.login-inner{padding:30px;}
}

</style>
</head>

<body>

<div class="container-fluid">
<div class="row vh-100">

<!-- LEFT SIDE -->

<div class="col-md-6 left-side d-none d-md-flex">

<i class="bi bi-pc-display-horizontal"></i>

<h2 class="text-center">
Stock <br>
<span style="color:var(--accent)">Management System</span>
</h2>

<p class="text-center text-secondary">
Manage inventory, dispatch assets and track service records.
</p>

</div>

<!-- RIGHT SIDE -->

<div class="col-md-6 right-side">

<div class="login-card">
<div class="login-inner">

<div class="text-center mb-4">

<img src="../admin/assets/logo.png" height="50" class="mb-3">

<h3 class="system-title">Admin Login</h3>

<p class="text-muted">Enter your credentials</p>

</div>

<?php if($error): ?>

<div class="alert alert-danger">
<i class="bi bi-exclamation-triangle me-2"></i>
<?= htmlspecialchars($error) ?>
</div>

<?php endif; ?>

<form method="POST">

<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

<div class="mb-3">
<label class="form-label">Username</label>
<div class="input-group">
<span class="input-group-text"><i class="bi bi-person"></i></span>
<input type="text" name="username" class="form-control" required>
</div>
</div>

<div class="mb-4">
<label class="form-label">Password</label>
<div class="input-group">
<span class="input-group-text"><i class="bi bi-lock"></i></span>
<input type="password" name="password" id="password" class="form-control" required>
<span class="input-group-text" id="togglePassword" style="cursor:pointer">
<i class="bi bi-eye"></i>
</span>
</div>
</div>

<button id="loginBtn" class="btn btn-custom w-100">

<span class="btn-text">
<i class="bi bi-box-arrow-in-right me-2"></i>Login
</span>

<span class="btn-loader d-none">
<span class="spinner-border spinner-border-sm me-2"></span>
Authenticating...
</span>

</button>

</form>

<div class="text-center mt-5 text-muted" style="font-size:12px">
© <?= date("Y") ?> Stock Management System
</div>

</div>
</div>

</div>
</div>
</div>

<!-- PAGE LOADER -->

<div id="loginLoader" class="login-loader">
<div class="loader-box">
<div class="spinner-border text-info" style="width:3rem;height:3rem;"></div>
<p class="mt-3 mb-0">Signing you in...</p>
</div>
</div>

<script>

const form=document.querySelector("form");
const loader=document.getElementById("loginLoader");
const btn=document.getElementById("loginBtn");

form.addEventListener("submit",function(){

loader.classList.add("show");

btn.disabled=true;

btn.querySelector(".btn-text").classList.add("d-none");
btn.querySelector(".btn-loader").classList.remove("d-none");

});

const toggle=document.getElementById("togglePassword");
const password=document.getElementById("password");

toggle.addEventListener("click",function(){

const type=password.type==="password"?"text":"password";
password.type=type;

this.innerHTML=type==="password"
?'<i class="bi bi-eye"></i>'
:'<i class="bi bi-eye-slash"></i>';

});

</script>

</body>
</html>