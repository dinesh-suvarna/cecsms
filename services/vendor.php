<?php
include "../config/db.php";
$page_title = "Add Vendor";
$page_icon  = "bi-people"; 
include "layout.php";

$success = false;

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $vendor_name = trim($_POST['vendor_name'] ?? '');

    if(!empty($vendor_name)){

        $stmt = $conn->prepare("INSERT INTO vendors (vendor_name) VALUES (?)");
        $stmt->bind_param("s", $vendor_name);

        if($stmt->execute()){
            $success = true;
        }

        $stmt->close();
    }
}
?>

<form method="POST">

<div class="mt-3">
    <label>Vendor Name</label>
    <input type="text" name="vendor_name" class="form-control" required oninvalid="this.setCustomValidity('Please add vendor name')"
       oninput="this.setCustomValidity('')">
</div>

<button type="submit" class="btn btn-primary mt-3">Save Vendor</button>
<a href="view_vendors.php" class="btn btn-secondary mt-3">View Vendors</a>

</form>


<!-- SweetAlert CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if($success): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: 'Vendor Added Successfully!',
    confirmButtonColor: '#3085d6'
}).then(() => {
    window.location = "vendor.php";
});
</script>
<?php endif; ?>

<?php include "footer.php"; ?>
