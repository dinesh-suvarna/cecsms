<?php
include "../config/db.php";
session_start();

// Check if ID is present
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $source = $_GET['source'] ?? 'log'; // Default to log if source is missing

    if ($source === 'log') {
        $stmt = $conn->prepare("UPDATE asset_logs SET is_read = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close(); // Clean up the statement
        
        header("Location: asset_logs.php");
        exit(); // Always use exit() after a header redirect
    } else {
        header("Location: assign_asset.php");
        exit();
    }
}

// Fallback: If no ID was provided, just go back to the dashboard
header("Location: division_dashboard.php");
exit();