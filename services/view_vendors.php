<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/db.php";

$page_title = "View Vendors";
include "layout.php";

/* Fetch Vendors */
$result = $conn->query("SELECT * FROM vendors ORDER BY id DESC");

?>

<div class="card shadow">
    <div class="card-body">

        <h4 class="mb-3">All Vendors</h4>

    <!-- 🔔 Show Messages -->
        <?php if(isset($_GET['error']) && $_GET['error'] == 'used'): ?>
            <div class="alert alert-danger">
                Vendor cannot be deleted because it is used in services.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['success']) && $_GET['success'] == 'deleted'): ?>
            <div class="alert alert-success">
                Vendor deleted successfully.
            </div>
        <?php endif; ?>

        <?php if ($result && $result->num_rows > 0): ?>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Sl.No</th>
                            <th>Vendor Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php 
                    $i = 1;
                    while($row = $result->fetch_assoc()): 
                    ?>

                        <tr>
                            <td><?= $i++; ?></td>
                            <td><?= htmlspecialchars($row['vendor_name']); ?></td>
                            <td>
                                <a href="delete_vendor.php?id=<?= $row['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this vendor?');">
                                   Delete
                                </a>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <div class="alert alert-info">
                No vendors found.
            </div>

        <?php endif; ?>

    </div>
</div>

<?php include "footer.php"; ?>