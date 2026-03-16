<?php
require_once "../config/db.php";
require_once "../includes/session.php";

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $id = intval($_POST['id']);

    // 🔎 Check if unit is used in dispatch_master
    $check = $conn->prepare("
        SELECT 1 
        FROM dispatch_master 
        WHERE unit_id = ?
        LIMIT 1
    ");
    $check->bind_param("i", $id);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){

        $_SESSION['error'] = "Cannot delete. This unit has dispatch records.";

    } else {

        $stmt = $conn->prepare("
            UPDATE units 
            SET status='Deleted' 
            WHERE id=?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $_SESSION['success'] = "Unit deleted successfully.";
    }
}

header("Location: unit_list.php");
exit;
?>