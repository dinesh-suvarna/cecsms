<?php
session_start();
if (!isset($_SESSION["user_id"])) { exit("Access Denied"); }

include "../config/db.php";

// 1. Get and Sanitize ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // 2. Use Prepared Statement for Security
    $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // 3. Success Redirect
        header("Location: view_services.php?msg=deleted");
        exit();
    } else {
        // 4. Failure Redirect
        header("Location: view_services.php?msg=error");
        exit();
    }
    $stmt->close();
} else {
    // No valid ID provided
    header("Location: view_services.php");
    exit();
}

$conn->close();
?>