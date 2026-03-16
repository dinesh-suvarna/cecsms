<?php
require_once __DIR__ . "/../includes/session.php";
require_once __DIR__ . "/../includes/security_headers.php";
require_once __DIR__ . "/../config/db.php";

/* -----------------------------
   Prevent caching
------------------------------ */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/* -----------------------------
   Check login
------------------------------ */
if (!isset($_SESSION["user_id"])) {
    header("Location: admin/login.php");
    exit();
}

/* -----------------------------
   Re-Validate User From DB
------------------------------ */
$stmt = $conn->prepare("
    SELECT role, status, institution_id, division_id
    FROM users
    WHERE id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || $user['status'] !== 'Active') {
    session_destroy();
    header("Location: login.php");
    exit();
}

/* -----------------------------
   Sync Session (Prevents Tampering)
------------------------------ */
$_SESSION['role']           = $user['role'];
$_SESSION['institution_id'] = $user['institution_id'];
$_SESSION['division_id']    = $user['division_id'];

/* -----------------------------
   ROLE CONSTANTS
------------------------------ */
define('ROLE_SUPERADMIN', 'SuperAdmin');
define('ROLE_ADMIN', 'Admin');
define('ROLE_STAFF', 'Staff');

/* -----------------------------
   Role check function
------------------------------ */
function requireRole($allowed_roles = []) {

    if (!isset($_SESSION['role']) ||
        !in_array($_SESSION['role'], $allowed_roles)) {

        header("Location: ../admin/access_denied.php");
        exit();
    }
}