<?php
require_once __DIR__ . "/../config/db.php";
require_once "../includes/session.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = intval($_POST['id']);

    // Check usage
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM dispatch_master WHERE division_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $usedInDispatch = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM units WHERE division_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $usedInUnits = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    if ($usedInDispatch > 0 || $usedInUnits > 0) {
        $_SESSION['error'] = "Division cannot be deactivated! It is in use.";
    } else {
        $stmt = $conn->prepare("UPDATE divisions SET status='Deleted' WHERE id=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Division deleted successfully.";
        } else {
            $_SESSION['error'] = "Error deleting division.";
        }
    }
}

header("Location: divisions.php");
exit;