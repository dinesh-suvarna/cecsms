<?php
include "../config/db.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Capture Header Data
    $master_sl = $_POST['master_sl_no'];
    $p_date = $_POST['purchase_date'];
    $vendor_id = $_POST['vendor_id'];
    $bill_no = $_POST['bill_no'];
    
    // Capture the Global Discount
    $global_discount = isset($_POST['global_discount']) ? floatval($_POST['global_discount']) : 0;

    // 2. Insert into purchase_ledger (Initial insert)
    $stmt = $conn->prepare("INSERT INTO purchase_ledger (master_sl_no, purchase_date, vendor_id, bill_no, discount_amount) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisd", $master_sl, $p_date, $vendor_id, $bill_no, $global_discount);
    
    if ($stmt->execute()) {
        $ledger_id = $conn->insert_id; 
        $running_total_with_tax = 0; // To calculate final_invoice_amount

        // 3. Loop through the items
        $item_stmt = $conn->prepare("INSERT INTO purchase_items (ledger_id, item_name, qty, unit_price, gst_percent, net_total, grand_total) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($_POST['item_name'] as $key => $name) {
            $qty = floatval($_POST['qty'][$key]);
            $price = floatval($_POST['unit_price'][$key]);
            $gst_p = floatval($_POST['gst_percent'][$key]);
            
            // Math for each row
            $net = $qty * $price;
            $tax_amount = ($net * $gst_p / 100);
            $grand = $net + $tax_amount;

            // Add to the total sum for the entire invoice
            $running_total_with_tax += $grand;

            $item_stmt->bind_param("isddddd", $ledger_id, $name, $qty, $price, $gst_p, $net, $grand);
            $item_stmt->execute();
        }

        // 4. Calculate Final Invoice Amount (Items Sum - Global Discount)
        $final_invoice_amount = $running_total_with_tax - $global_discount;
        if($final_invoice_amount < 0) $final_invoice_amount = 0;

        // 5. Update the purchase_ledger with the calculated final total
        $update_stmt = $conn->prepare("UPDATE purchase_ledger SET final_invoice_amount = ? WHERE id = ?");
        $update_stmt->bind_param("di", $final_invoice_amount, $ledger_id);
        $update_stmt->execute();

        header("Location: view_purchase_ledger.php?msg=success");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}
?>