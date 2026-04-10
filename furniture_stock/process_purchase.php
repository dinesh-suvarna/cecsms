<?php
include "../config/db.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Capture Header Data
    $master_sl = $_POST['master_sl_no'];
    $p_date = $_POST['purchase_date'];
    $vendor_id = $_POST['vendor_id'];
    $bill_no = $_POST['bill_no'];

    // 2. Insert into purchase_ledger
    $stmt = $conn->prepare("INSERT INTO purchase_ledger (master_sl_no, purchase_date, vendor_id, bill_no) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $master_sl, $p_date, $vendor_id, $bill_no);
    
    if ($stmt->execute()) {
        $ledger_id = $conn->insert_id; // Get the ID of the bill we just saved

        // 3. Loop through the items
        $item_stmt = $conn->prepare("INSERT INTO purchase_items (ledger_id, item_name, qty, unit_price, gst_percent, net_total, grand_total) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($_POST['item_name'] as $key => $name) {
            $qty = $_POST['qty'][$key];
            $price = $_POST['unit_price'][$key];
            $gst_p = $_POST['gst_percent'][$key];
            
            // Re-calculate on server side for safety
            $net = $qty * $price;
            $grand = $net + ($net * $gst_p / 100);

            $item_stmt->bind_param("isddddd", $ledger_id, $name, $qty, $price, $gst_p, $net, $grand);
            $item_stmt->execute();
        }

        header("Location: view_purchase_ledger.php?msg=success");
    } else {
        echo "Error: " . $conn->error;
    }
}
?>