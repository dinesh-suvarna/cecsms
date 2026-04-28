<?php
require_once __DIR__ . "/../config/db.php";
require_once "../includes/session.php";


if (isset($_POST['id']) && isset($_POST['status_action'])) {
    $id = intval($_POST['id']);
    $action = $_POST['status_action'];
    
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

header("Location: units.php");
exit();