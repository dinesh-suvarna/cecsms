<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    http_response_code(403);
    exit("Unauthorized");
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/crypto.php"; 

if (isset($_POST['id']) && isset($_POST['status'])) {
    // Decrypt the token back to the real integer database ID
    $id = decrypt_id($_POST['id']);
    $status = ($_POST['status'] === 'Paid') ? 'Paid' : 'Unpaid';

    // Validate that the decrypted ID is a valid number
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        exit("Invalid ID");
    }

    $stmt = $conn->prepare("UPDATE services SET bill_status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        $stmt->close();
        echo "success";
    } else {
        $stmt->close();
        http_response_code(500);
        echo "Error updating status";
    }
} else {
    http_response_code(400);
    echo "Invalid request";
}