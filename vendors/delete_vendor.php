<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/db.php";

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // 1. Check if vendor is used in SERVICES
    $checkServices = $conn->prepare("SELECT id FROM services WHERE vendor_id = ? LIMIT 1");
    $checkServices->bind_param("i", $id);
    $checkServices->execute();
    $checkServices->store_result();

    if ($checkServices->num_rows > 0) {
        $checkServices->close();
        header("Location: vendor_manager.php?error=used_in_services");
        exit();
    }
    $checkServices->close();

    // 2. Check if vendor is used in STOCK_DETAILS
    $checkStock = $conn->prepare("SELECT id FROM stock_details WHERE vendor_id = ? LIMIT 1");
    $checkStock->bind_param("i", $id);
    $checkStock->execute();
    $checkStock->store_result();

    if ($checkStock->num_rows > 0) {
        $checkStock->close();
        header("Location: vendor_manager.php?error=used_in_stock");
        exit();
    }
    $checkStock->close();

    // 3. Safe to delete
    $stmt = $conn->prepare("DELETE FROM vendors WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $type = $_GET['type'] ?? 'Computer';
        header("Location: vendor_manager.php?success=1&type=" . urlencode($type));
        
    } else {
        header("Location: vendor_manager.php?error=failed");
    }
    $stmt->close();
} else {
    header("Location: vendor_manager.php");
}
exit();