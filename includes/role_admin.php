<?php
require_once "../config/db.php";
require_once "../includes/session.php";

/* ================= BASIC LOGIN CHECK ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

/* ================= VERIFY USER FROM DATABASE ================= */
$stmt = $conn->prepare("
    SELECT id, role, status, institution_id, division_id
    FROM users
    WHERE id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

/* ================= VALIDATION ================= */
if (!$user || $user['status'] !== 'Active') {
    session_destroy();
    header("Location: login.php");
    exit();
}

/* ================= ROLE CHECK ================= */
if (!in_array($user['role'], ['SuperAdmin','Admin'])) {
    header("Location: login.php");
    exit();
}

/* ================= ADMIN EXTRA VALIDATION ================= */
if ($user['role'] === 'Admin') {

    // Admin must have institution + division
    if (empty($user['institution_id']) || empty($user['division_id'])) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
}

/* ================= SESSION RE-SYNC (SECURITY) ================= */
$_SESSION['role']           = $user['role'];
$_SESSION['institution_id'] = $user['institution_id'];
$_SESSION['division_id']    = $user['division_id'];