<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../config/db.php";

// Restrict publishing permissions to SuperAdmin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SuperAdmin') {
    header("Location: ../admin/access_denied.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_role = $_SESSION['role'];
    $target_role = trim($_POST['target_role']);
    $title       = trim($_POST['title']);
    $message     = trim($_POST['message']);

    if (!empty($title) && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO system_announcements (sender_role, target_role, title, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $sender_role, $target_role, $title, $message);
        $stmt->execute();
    }
}

// After successfully inserting the record into the database:
header("Location: " . $_SERVER['HTTP_REFERER'] . "?broadcast_published=1");
exit();
?>