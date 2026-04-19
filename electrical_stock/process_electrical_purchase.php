<?php
include "../config/db.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $master_sl = $_POST['master_sl_no'];
    $p_date = $_POST['purchase_date'];
    $vendor_id = $_POST['vendor_id'];
    $bill_no = $_POST['bill_no'];
    $global_discount = isset($_POST['global_discount']) ? floatval($_POST['global_discount']) : 0;

    // 1. Updated: Added 'category' and passed 'Electrical' explicitly
    $stmt = $conn->prepare("INSERT INTO purchase_ledger (master_sl_no, purchase_date, vendor_id, bill_no, category, discount_amount) VALUES (?, ?, ?, ?, 'Electrical', ?)");
    $stmt->bind_param("ssisd", $master_sl, $p_date, $vendor_id, $bill_no, $global_discount);
    
    if ($stmt->execute()) {
        $ledger_id = $conn->insert_id; 
        $running_total_with_tax = 0;

        $item_stmt = $conn->prepare("INSERT INTO purchase_items (ledger_id, item_name, qty, unit_price, gst_percent, net_total, grand_total) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($_POST['item_name'] as $key => $name) {
            $qty = floatval($_POST['qty'][$key]);
            $price = floatval($_POST['unit_price'][$key]);
            $gst_p = floatval($_POST['gst_percent'][$key]);
            
            $net = $qty * $price;
            $tax_amount = ($net * $gst_p / 100);
            $grand = $net + $tax_amount;

            $running_total_with_tax += $grand;

            $item_stmt->bind_param("isddddd", $ledger_id, $name, $qty, $price, $gst_p, $net, $grand);
            $item_stmt->execute();
        }

        $final_invoice_amount = max(0, $running_total_with_tax - $global_discount);

        $update_stmt = $conn->prepare("UPDATE purchase_ledger SET final_invoice_amount = ? WHERE id = ?");
        $update_stmt->bind_param("di", $final_invoice_amount, $ledger_id);
        $update_stmt->execute();

        // Redirect to the ELECTRICAL specific view
        header("Location: view_electrical_ledger.php?msg=success");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}
?>