<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/db.php";

// Sanitize Service ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // Delete record using Prepared Statements
    $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: view_services.php?msg=deleted");
        exit();
    } else {
        $stmt->close();
        header("Location: view_services.php?msg=error");
        exit();
    }
} else {
    header("Location: view_services.php");
    exit();
}