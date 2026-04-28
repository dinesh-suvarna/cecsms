<?php
require_once __DIR__ . "/../config/db.php";
session_start();

$notif_division_id = $_SESSION['division_id'] ?? 0;

if ($notif_division_id > 0) {
    // update all logs belonging to this division's users
    $query = "UPDATE asset_logs 
              SET is_read = 1 
              WHERE is_read = 0 
              AND performed_by IN (SELECT id FROM users WHERE division_id = ?)";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $notif_division_id);
    $stmt->execute();
    $stmt->close();
}

// Redirect back to wherever they were
$referrer = $_SERVER['HTTP_REFERER'] ?? 'division_dashboard.php';
header("Location: $referrer");
exit();