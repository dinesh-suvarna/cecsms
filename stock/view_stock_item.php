<?php
$page_title = "Item Master";
$page_icon = "bi-box";
include "../config/db.php";

$error = "";

/* ================= DELETE ITEM ================= */
if(isset($_GET['delete'])){

    $id = intval($_GET['delete']);

    try {
        $stmt = $conn->prepare("DELETE FROM items_master WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        header("Location: ../stock/manage_items_master.php?deleted=1");
        exit;

    } catch(mysqli_sql_exception $e){
        $error = "Cannot delete item. It may be used in stock.";
    }
}

/* ================= UPDATE ITEM ================= */
if(isset($_POST['update'])){

    $id        = intval($_POST['id']);
    $item_name = trim($_POST['item_name']);
    $category  = $_POST['category'];

    if(empty($item_name)){
        $error = "Item name cannot be empty.";
    } else {

        try {
            $stmt = $conn->prepare("
                UPDATE items_master 
                SET item_name = ?, category = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("ssi", $item_name, $category, $id);
            $stmt->execute();

            header("Location: ../stock/manage_items_master.php?updated=1");
            exit;

        } catch(mysqli_sql_exception $e){
            if($e->getCode() == 1062){
                $error = "Item name already exists!";
            } else {
                $error = "Database error.";
            }
        }
    }
}

/* Fetch Items */
$result = $conn->query("SELECT * FROM item_master ORDER BY id DESC");

ob_start();
?>

<div class="container-fluid mt-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-semibold mb-0">Item Master</h5>
            <small class="text-muted">Manage inventory item names</small>
        </div>

        <a href="add_item_master.php" 
           class="btn btn-success btn-sm rounded-pill px-3">
            <i class="bi bi-plus-lg me-1"></i> Add Item
        </a>
    </div>

    <?php if($result->num_rows > 0): ?>
    <div class="row g-4">

        <?php while($row = $result->fetch_assoc()): 

            $item = strtolower($row['item_name']);

            /* Icon mapping */
            $icon = "bi-box"; // default

            if(str_contains($item, "desktop")) {
                $icon = "bi-pc-display";
            } elseif(str_contains($item, "laptop")) {
                $icon = "bi-laptop";
            } elseif(str_contains($item, "keyboard")) {
                $icon = "bi-keyboard";
            } elseif(str_contains($item, "mouse")) {
                $icon = "bi-mouse";
            } elseif(str_contains($item, "smps")) {
                $icon = "bi-cpu";
            } elseif(str_contains($item, "motherboard")) {
                $icon = "bi-motherboard";
            } elseif(str_contains($item, "switch")) {
                $icon = "bi-hdd-network";
            } elseif(str_contains($item, "printer")) {
                $icon = "bi-printer";
            }
        ?>

        <div class="col-md-6 col-lg-4">

            <div class="card border-0 shadow-sm rounded-4 h-100 hover-shadow">

                <div class="card-body p-4">

                    <!-- Icon -->
                    <div class="mb-3 fs-3 text-primary">
                        <i class="bi <?= $icon ?>"></i>
                    </div>

                    <!-- Item Name -->
                    <h6 class="fw-semibold mb-2">
                        <?= htmlspecialchars($row['item_name']) ?>
                    </h6>

                    <!-- Category -->
                    <span class="badge bg-light text-dark border">
                        <?= htmlspecialchars($row['category']) ?>
                    </span>

                    <!-- Actions -->
                    <div class="mt-4 d-flex justify-content-between">

                        <a href="add_item_master.php?edit=<?= $row['id'] ?>" 
                           class="btn btn-outline-primary btn-sm rounded-pill px-3">
                            <i class="bi bi-pencil-square me-1"></i> Edit
                        </a>

                        <a href="add_item_master.php?delete=<?= $row['id'] ?>" 
                           class="btn btn-outline-danger btn-sm rounded-pill px-3"
                           onclick="return confirm('Delete this item?');">
                            <i class="bi bi-trash me-1"></i> Delete
                        </a>

                    </div>

                </div>
            </div>

        </div>

        <?php endwhile; ?>
    </div>

<?php else: ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-box-seam fs-3 d-block mb-2"></i>
        No items available
    </div>
<?php endif; ?>

</div>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>