<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/db.php";

$page_title = "View Services";
$page_icon  = "bi-card-checklist";
include "layout.php";

$where = "";

if(isset($_GET['from']) && isset($_GET['to']) && $_GET['from'] && $_GET['to']){
    $from = $_GET['from'];
    $to = $_GET['to'];

    $where = "WHERE services.service_date BETWEEN ? AND ?";

    $stmt = $conn->prepare("
        SELECT services.*, vendors.vendor_name 
        FROM services
        JOIN vendors ON services.vendor_id = vendors.id
        $where
        ORDER BY services.service_date DESC
    ");

    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();

    $stmt2 = $conn->prepare("
        SELECT SUM(amount) as total 
        FROM services 
        WHERE service_date BETWEEN ? AND ?
    ");

    $stmt2->bind_param("ss", $from, $to);
    $stmt2->execute();
    $total = $stmt2->get_result()->fetch_assoc()['total'];

} else {

    $result = $conn->query("
        SELECT services.*, vendors.vendor_name 
        FROM services
        JOIN vendors ON services.vendor_id = vendors.id
        ORDER BY services.service_date DESC
    ");

    $total = $conn->query("SELECT SUM(amount) as total FROM services")
                  ->fetch_assoc()['total'];
}
?>

<div class="alert alert-info my-4">
    <strong>Total Amount: ₹ <?= number_format($total ?? 0,2) ?></strong>
</div>

<form class="row g-3 mb-3">

    <div class="col-md-3">
        <input type="date" name="from" value="<?= $_GET['from'] ?? '' ?>" class="form-control" max="<?= date('Y-m-d') ?>">
    </div>

    <div class="col-md-3">
        <input type="date" name="to" value="<?= $_GET['to'] ?? '' ?>" class="form-control" max="<?= date('Y-m-d') ?>">
    </div>

    <div class="col-md-3">
        <button class="btn btn-primary">Filter</button>
        <a href="view_services.php" class="btn btn-secondary">Reset</a>
    </div>

    <div class="col-md-3 text-end">
        <a href="export_excel.php" class="btn btn-success">Export Excel</a>
        <a href="add_service.php" class="btn btn-dark">Add New</a>
    </div>

</form>

<table class="table table-bordered table-striped shadow-sm bg-white">
    <thead class="table-dark">
        <tr>
            <th>SL</th>
            <th>Date</th>
            <th>Item</th>
            <th>Type</th>
            <th>Vendor</th>
            <th>Bill_No</th>
            <th>Bill_Date</th>
            <th>Amount</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
<?php 
$sl = 1;
while($row = $result->fetch_assoc()){ 
    $status = $row['bill_status'] ?? 'Unpaid';
    $btn_class = ($status == 'Paid') ? 'btn-success' : 'btn-danger';
?>
<tr>
    <td><?= $sl++ ?></td>
    <td><?= $row['date'] ?></td>
    <td><?= $row['item_name'] ?></td>
    <td><?= $row['service_type'] ?></td>
    <td><?= htmlspecialchars($row['vendor_name']) ?></td>
    <td><?= $row['bill_number'] ?></td>
    <td><?= $row['service_date'] ?></td>
    <td>₹ <?= number_format($row['amount'],2) ?></td>
    <td class="text-nowrap">
        <a href="edit_service.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary me-1">
            <i class="bi bi-pencil-fill"></i>
        </a>

        <a href="delete_service.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger me-1"
           onclick="return confirm('Delete this record?')">
            <i class="bi bi-trash-fill"></i>
        </a>

        <button class="btn btn-sm toggle-pill <?= $btn_class ?>" 
                data-id="<?= $row['id'] ?>" 
                data-status="<?= $status ?>">
            <?= $status ?>
        </button>
    </td>
</tr>
<?php } ?>
    </tbody>
</table>

<!-- JS for AJAX toggle -->
<script>
document.querySelectorAll('.toggle-pill').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        let newStatus = btn.getAttribute('data-status') === 'Unpaid' ? 'Paid' : 'Unpaid';

        if(newStatus === 'Unpaid'){
            if(!confirm("Are you sure you want to mark this bill as Unpaid?")){
                return;
            }
        }

        fetch('toggle_bill_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&status=${newStatus}`
        })
        .then(res => res.text())
        .then(res => {
            if(res === 'success'){
                btn.textContent = newStatus;
                btn.setAttribute('data-status', newStatus);
                btn.classList.toggle('btn-success', newStatus === 'Paid');
                btn.classList.toggle('btn-danger', newStatus === 'Unpaid');
            } else {
                alert("Failed to update status: " + res);
            }
        })
        .catch(err => alert("Error: " + err));
    });
});
</script>

<style>
.toggle-pill {
    border-radius: 50px;
    min-width: 90px;
    font-weight: 500;
    transition: all 0.3s ease;
}
</style>

<?php include "footer.php"; ?>
