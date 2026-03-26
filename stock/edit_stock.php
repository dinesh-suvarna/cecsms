<?php
include "../config/db.php";
session_start();

if(!isset($_GET['id'])){
    header("Location: view_stock_details.php");
    exit;
}

$id = intval($_GET['id']);
$successMsg = "";

/* Fetch stock details with item type */
$stmt = $conn->prepare("
    SELECT sd.*, im.stock_type 
    FROM stock_details sd
    LEFT JOIN items_master im ON sd.stock_item_id = im.id
    WHERE sd.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if(!$data){
    header("Location: view_stock_details.php");
    exit;
}

/* Fetch Vendors */
$vendors = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name ASC");

/* Update Logic */
if(isset($_POST['update'])){

    $user_id  = $_SESSION['user_id'] ?? 0;

    $serial = trim($_POST['serial_number'] ?? '');
    if($data['stock_type'] === 'non_serial'){
    $serial = NULL;
    }
    $bill_no  = trim($_POST['bill_no']);
    $bill_date = $_POST['bill_date'] ?: NULL;
    $po       = trim($_POST['po_number']);
    $amount   = !empty($_POST['amount']) ? (float)$_POST['amount'] : NULL;
    $warranty = $_POST['warranty_upto'] ?: NULL;
    $vendor   = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : NULL;

    if($data['stock_type'] === 'non_serial'){
        $serial = NULL;
    }

    /* Duplicate serial check */
    if($data['stock_type'] === 'serial' && !empty($serial)){
        $check = $conn->prepare("
            SELECT id FROM stock_details 
            WHERE stock_item_id = ? 
            AND serial_number = ? 
            AND id != ?
        ");
        $check->bind_param("isi", $data['stock_item_id'], $serial, $id);
        $check->execute();
        $dup = $check->get_result();
        if($dup->num_rows > 0){
            die("Serial number already exists for this item.");
        }
        $check->close();
    }

    /* Begin Transaction */
    $conn->begin_transaction();

    try {

        /* Store old values for log */
        $old_serial = $data['serial_number'];
        $old_vendor = $data['vendor_id'];

        /* Update stock */
        $update = $conn->prepare("
            UPDATE stock_details SET
                serial_number = ?,
                bill_no = ?,
                bill_date = ?,
                po_number = ?,
                vendor_id = ?,
                amount = ?,
                warranty_upto = ?
            WHERE id = ?
        ");

        $update->bind_param(
            "ssssidsi",
            $serial,
            $bill_no,
            $bill_date,
            $po,
            $vendor,
            $amount,
            $warranty,
            $id
        );

        $update->execute();
        $update->close();

        /* Insert Audit Log */
        $log = $conn->prepare("
            INSERT INTO stock_edit_logs
            (stock_detail_id, edited_by, old_serial, new_serial, old_vendor, new_vendor)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $log->bind_param(
            "iissii",
            $id,
            $user_id,
            $old_serial,
            $serial,
            $old_vendor,
            $vendor
        );

        $log->execute();
        $log->close();

        $conn->commit();

        /* Refresh updated data */
        $data['serial_number'] = $serial;
        $data['bill_no'] = $bill_no;
        $data['bill_date'] = $bill_date;
        $data['po_number'] = $po;
        $data['vendor_id'] = $vendor;
        $data['amount'] = $amount;
        $data['warranty_upto'] = $warranty;

        $successMsg = "Stock updated successfully.";

    } catch(Exception $e){
        $conn->rollback();
        die("Something went wrong.");
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Stock</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">

<div class="container">
<div class="card shadow rounded-4">
<div class="card-body">

<h5 class="mb-4">Edit Stock</h5>

<?php if(!empty($successMsg)): ?>
<div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>

<form method="POST">

<?php if($data['stock_type'] === 'serial'): ?>
<div class="mb-3">
<label>Serial Number</label>
<input type="text" name="serial_number" class="form-control"
value="<?= htmlspecialchars($data['serial_number']) ?>" required>
</div>
<?php endif; ?>

<div class="mb-3">
<label>Bill No</label>
<input type="text" name="bill_no" class="form-control"
value="<?= htmlspecialchars($data['bill_no']) ?>">
</div>

<div class="mb-3">
<label>Bill Date</label>
<input type="date" name="bill_date" class="form-control"
value="<?= htmlspecialchars($data['bill_date']) ?>">
</div>

<div class="mb-3">
<label>PO Number</label>
<input type="text" name="po_number" class="form-control"
value="<?= htmlspecialchars($data['po_number']) ?>">
</div>

<div class="mb-3">
<label>Vendor</label>
<select name="vendor_id" class="form-select">
    <option value="">Select Vendor</option>
    <?php while($v = $vendors->fetch_assoc()): ?>
        <option value="<?= $v['id'] ?>"
            <?= ($v['id'] == $data['vendor_id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($v['vendor_name']) ?>
        </option>
    <?php endwhile; ?>
</select>
</div>

<div class="mb-3">
<label>Amount</label>
<input type="number" step="0.01" name="amount" class="form-control"
value="<?= htmlspecialchars($data['amount']) ?>">
</div>

<div class="mb-3">
<label>Warranty Upto</label>
<input type="date" name="warranty_upto" class="form-control"
value="<?= htmlspecialchars($data['warranty_upto']) ?>">
</div>

<button type="submit" name="update" class="btn btn-success">
Update
</button>

<a href="view_stock_details.php" class="btn btn-secondary">
Back
</a>

</form>

</div>
</div>
</div>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>