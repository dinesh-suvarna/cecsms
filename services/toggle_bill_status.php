<?php
session_start();
include "../config/db.php";

if(!isset($_SESSION['admin_id'])){
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

if(isset($_POST['id']) && isset($_POST['status'])){
    $id = (int)$_POST['id'];
    $status = $_POST['status'] === 'Paid' ? 'Paid' : 'Unpaid';

    $stmt = $conn->prepare("UPDATE services SET bill_status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    
    if($stmt->execute()){
        echo "success";
    } else {
        http_response_code(500);
        echo "Error updating status";
    }
} else {
    http_response_code(400);
    echo "Invalid request";
}


