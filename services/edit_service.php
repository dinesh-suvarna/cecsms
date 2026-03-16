<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/db.php";

$page_title = "Edit Service";
include "layout.php";

$id = intval($_GET['id']); // ✅ secure

// ✅ Fetch service with vendor
$stmt = $conn->prepare("
    SELECT services.*, vendors.vendor_name 
    FROM services
    JOIN vendors ON services.vendor_id = vendors.id
    WHERE services.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

// ✅ Fetch all vendors for dropdown
$vendors = [];
$result = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name ASC");
while($row = $result->fetch_assoc()){
    $vendors[] = $row;
}

if(isset($_POST['update'])){

    $stmt = $conn->prepare("UPDATE services SET 
        service_date=?,
        bill_number=?,
        item_name=?,
        service_type=?,
        vendor_id=?,
        amount=? 
        WHERE id=?");

    $stmt->bind_param("ssssidi",
        $_POST['service_date'],
        $_POST['bill_number'],
        $_POST['item_name'],
        $_POST['service_type'],
        $_POST['vendor_id'],   // ✅ foreign key
        $_POST['amount'],
        $id
    );

    $stmt->execute();
    echo "<div class='alert alert-success'>Updated</div>";
}
?>

<form method="POST">

<div class="col-md-3">
    <label>Bill Date</label>
    <input type="date" name="service_date" value="<?= $data['service_date'] ?>" class="form-control mb-2">
</div>

<div class="col-md-3">
    <label>Bill Number</label>
    <input type="text" name="bill_number" value="<?= $data['bill_number'] ?>" class="form-control mb-2">
</div>

<div class="col-md-3">
    <label>Item Name</label>
    <input type="text" name="item_name" value="<?= $data['item_name'] ?>" class="form-control mb-2">
</div>

<div class="col-md-3">
    <label>Service Type</label>
    <input type="text" name="service_type" value="<?= $data['service_type'] ?>" class="form-control mb-2">
</div>

<div class="col-md-3">
    <label>Vendor</label>
    <select name="vendor_id" class="form-select mb-2" required>
        <?php foreach($vendors as $vendor): ?>
            <option value="<?= $vendor['id'] ?>"
                <?= ($vendor['id'] == $data['vendor_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($vendor['vendor_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="col-md-3">
    <label>Amount</label>
    <input type="number" step="0.01" name="amount" value="<?= $data['amount'] ?>" class="form-control mb-2">
</div>

<button name="update" class="btn btn-success">Update</button>
<a href="view_services.php" class="btn btn-secondary">View</a>

</form>

<?php include "footer.php"; ?>