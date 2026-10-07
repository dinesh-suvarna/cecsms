<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/crypto.php";
session_start();

if (!isset($_GET['id'])) {
    header("Location: view_stock_details.php");
    exit;
}

$enc_id = $_GET['id'];
$id = decrypt_id($enc_id);

if (!$id || !is_numeric($id)) {
    header("Location: view_stock_details.php");
    exit;
}

// 1. Check if the stock item exists and verify its dispatch status
$stmt = $conn->prepare("
    SELECT sd.*, 
           IFNULL((SELECT SUM(quantity - IFNULL(returned_quantity,0)) 
                   FROM dispatch_details 
                   WHERE stock_detail_id = sd.id), 0) AS dispatched_qty
    FROM stock_details sd
    WHERE sd.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$stock = $result->fetch_assoc();
$stmt->close();

if (!$stock) {
    header("Location: view_stock_details.php?error=not_found");
    exit;
}

// 2. Strict Server-Side Safeguard: Block deletion if any quantity is dispatched
if ($stock['status'] !== 'available' || (int)$stock['dispatched_qty'] > 0) {
    header("Location: view_stock_details.php?error=cannot_delete");
    exit;
}

// 3. Perform Deletion
$del = $conn->prepare("DELETE FROM stock_details WHERE id = ?");
$del->bind_param("i", $id);

if ($del->execute()) {
    $del->close();
    header("Location: view_stock_details.php?success=deleted");
    exit;
} else {
    $del->close();
    header("Location: view_stock_details.php?error=delete_failed");
    exit;
}
?>