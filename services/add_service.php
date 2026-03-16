<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/db.php";

$page_title = "Add Service";
$page_icon  = "bi-tools"; 
include "layout.php";

$today = date("Y-m-d");
$success = false;

if(isset($_POST['submit'])){

    $date   = $_POST['tdate'] ?? '';
    $item   = trim($_POST['item_name'] ?? '');
    $type   = trim($_POST['service_type'] ?? '');
    $vendor_id = (int)($_POST['vendor_id'] ?? 0);
    $bill   = trim($_POST['bill_number'] ?? '');
    $servicedate = $_POST['service_date'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);

    // Basic Validations
    if ($date > $today || $servicedate > $today) {

        echo "<div class='alert alert-danger'>Future dates not allowed</div>";

    } elseif ($vendor_id <= 0) {

        echo "<div class='alert alert-danger'>Please select a vendor</div>";

    } elseif ($amount <= 0) {

        echo "<div class='alert alert-danger'>Amount must be greater than 0</div>";

    } elseif (empty($bill)) {

        echo "<div class='alert alert-danger'>Bill number required</div>";

    } else {

        $stmt = $conn->prepare("INSERT INTO services 
        (date,item_name,service_type,vendor_id,bill_number,service_date,amount)
        VALUES(?,?,?,?,?,?,?)");

        $stmt->bind_param("sssissd",
            $date,
            $item,
            $type,
            $vendor_id,
            $bill,
            $servicedate,
            $amount
        );

        if($stmt->execute()){
            $success = true;
            $_POST = [];
        } else {
            echo "<div class='alert alert-danger'>Error: ".$stmt->error."</div>";
        }

        $stmt->close();
    }
}
?>

<?php
$vendors = [];

// ✅ Fetch id + name
$result = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name ASC");

if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $vendors[] = $row;
    }
}
?>

<form method="POST">

<div class="row">
<div class="col-md-6">
<label>Date</label>
<input type="date" name="tdate" value="<?= $today ?>" max="<?= $today ?>" class="form-control" required>
</div>
</div>

<div class="mt-3">
<label>Item</label>
<select name="item_name" class="form-select" required oninvalid="this.setCustomValidity('Please select an item')"
       oninput="this.setCustomValidity('')">
<option>--Select Item--</option>
<option>Printer</option>
<option>Projector</option>
<option>Motherboard</option>
<option>SMPS</option>
<option>UPS</option>
<option>Network</option>
<option>CCTV</option>
</select>
</div>

<div class="mt-3">
<label>Service Type</label>
<input type="text" name="service_type" class="form-control" maxlength="100" required oninvalid="this.setCustomValidity('Please enter service type')"
       oninput="this.setCustomValidity('')">
</div>
<div class="mt-3">
<label>Vendor</label>
<select name="vendor_id" class="form-select" required required oninvalid="this.setCustomValidity('Please enter vendor details')"
       oninput="this.setCustomValidity('')">
    <option value="">--Select Vendor--</option>

    <?php foreach($vendors as $vendor): ?>
        <option value="<?= $vendor['id'] ?>">
            <?= htmlspecialchars($vendor['vendor_name']) ?>
        </option>
    <?php endforeach; ?>

</select>
</div>

<div class="mt-3">
<label>Bill No</label>
<input type="text" name="bill_number"
       class="form-control"
       maxlength="50"
       pattern="[A-Za-z0-9\-\/]+"
       title="Only letters, numbers, / and - allowed"
       required oninvalid="this.setCustomValidity('Please enter bill number')"
       oninput="this.setCustomValidity('')">
</div>

<div class="mt-3">
<label>Bill Date</label>
<input type="date" name="service_date" value="<?= $today ?>" max="<?= $today ?>" class="form-control" required >
</div> 

<div class="mt-3">
<label>Amount</label>
<input type="number" name="amount" class="form-control" min="0" step="0.01" required oninvalid="this.setCustomValidity('Please enter service amount')"
       oninput="this.setCustomValidity('')">
</div>

<button class="btn btn-primary mt-3" name="submit">Save</button>
<a href="view_services.php" class="btn btn-secondary mt-3">View</a>

<?php if(isset($success) && $success): ?>
<div class="toast-container position-fixed top-0 end-0 p-3">
  <div id="successToast" class="toast align-items-center text-bg-success border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body">
        ✅ Service Added Successfully!
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if(isset($success) && $success): ?>
<script>
document.addEventListener("DOMContentLoaded", function(){
    var toast = new bootstrap.Toast(document.getElementById('successToast'));
    toast.show();
});
</script>
<?php endif; ?>

<?php include "footer.php"; ?>