<?php
require_once "../config/db.php";
require_once "../includes/session.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = intval($_POST['id']);

    // Check if division is used in dispatch_master
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM dispatch_master WHERE division_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $usedInDispatch = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    // Check if division is used in units
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM units WHERE division_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $usedInUnits = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    if ($usedInDispatch > 0 || $usedInUnits > 0) {
        $_SESSION['error'] = "Division cannot be deleted! It is being used.";
    } else {
        // Soft delete
        $stmt = $conn->prepare("UPDATE divisions SET status='Deleted' WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Division deleted successfully.";
        } else {
            $_SESSION['error'] = "Error deleting division.";
        }
        $stmt->close();
    }
}

// Redirect back to list
header("Location: division_list.php");
exit;