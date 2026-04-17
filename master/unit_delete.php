<?php
require_once "../config/db.php";
require_once "../includes/session.php";

/**
 * Logic preserved: This file handles both Deactivation (soft delete) 
 * and Reactivation based on the 'status_action' sent from units.php
 */

if (isset($_POST['id']) && isset($_POST['status_action'])) {
    $id = intval($_POST['id']);
    $action = $_POST['status_action'];
    
    // Determine new status based on button action
    $new_status = ($action === 'Activate') ? 'Active' : 'Inactive';

    $stmt = $conn->prepare("UPDATE units SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Unit " . strtolower($action) . "d successfully.";
    } else {
        $_SESSION['error'] = "Failed to update unit status.";
    }
    
    $stmt->close();
}

// Redirect back to the main management page
header("Location: units.php");
exit();