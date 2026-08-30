<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../config/db.php";

/**
 * Fetch announcements targeted to the current user's role
 */
function get_role_announcements() {
    global $conn;
    $current_role = $_SESSION['role'] ?? 'Staff';

    $sql = "SELECT * FROM system_announcements 
            WHERE is_active = 1 
            AND (target_role = ? OR target_role = 'All') 
            ORDER BY id DESC LIMIT 3";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $current_role);
    $stmt->execute();
    return $stmt->get_result();
}
?>