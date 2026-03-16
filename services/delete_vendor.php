<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/db.php";

if (isset($_GET['id'])) {

    $id = (int)$_GET['id'];

    // 🔎 CHECK: Is vendor used in services?
    $check = $conn->prepare("SELECT id FROM services WHERE vendor_id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        // Vendor is used — cannot delete
        $check->close();
        header("Location: view_vendors.php?error=used");
        exit();
    }

    $check->close();

    // ✅ Safe to delete
    $stmt = $conn->prepare("DELETE FROM vendors WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

header("Location: view_vendors.php?success=deleted");
exit();