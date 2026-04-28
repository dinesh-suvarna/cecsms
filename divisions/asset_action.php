<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if($id && $action){

    if($action == "repair"){
        $status = "repair";
    }
    elseif($action == "dispose"){
        $status = "disposed";
    }
    else{
        die("Invalid action");
    }

    $stmt = $conn->prepare("
        UPDATE division_assets
        SET status = ?
        WHERE id = ?
    ");

    $stmt->bind_param("si",$status,$id);
    $stmt->execute();
}
$_SESSION['toast_message'] = "Asset status updated successfully!";
$_SESSION['toast_type'] = "success";
header("Location: returned_assets.php");
exit;