<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/session.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM system_announcements WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

// Redirect back to dashboard
$referer = $_SERVER['HTTP_REFERER'] ?? '/cecsms/admin/admin_dashboard.php';
header("Location: " . $referer);
exit;