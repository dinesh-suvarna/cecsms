<?php
require_once "../config/db.php";
require_once "../includes/session.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = intval($_POST['id']);

    $stmt = $conn->prepare("UPDATE divisions SET status='Active' WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Division restored successfully.";
    } else {
        $_SESSION['error'] = "Error restoring division.";
    }
}

header("Location: divisions.php");
exit;