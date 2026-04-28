<?php
require_once __DIR__ . "/../config/db.php";
session_start();

// Security Check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // 1. Delete associated items first (Foreign Key Constraint)
    $conn->query("DELETE FROM purchase_items WHERE ledger_id = $id");

    // 2. Delete the ledger entry
    $delete = $conn->query("DELETE FROM purchase_ledger WHERE id = $id");

    if ($delete) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}
?>