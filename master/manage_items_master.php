<?php
require_once "../config/db.php";
require_once "../includes/session.php";
require_once "../admin/auth.php";

$page_title = "Manage Items (Master)";
$page_icon  = "bi-boxes";

$error = "";

/* ================= DELETE ITEM ================= */
if(isset($_GET['delete'])){

    $id = intval($_GET['delete']);

    /* STEP 1: Check if item used in stock_details */
    $check = $conn->prepare("
        SELECT id FROM stock_details 
        WHERE stock_item_id = ? 
        LIMIT 1
    ");
    $check->bind_param("i", $id);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){
        $error = "Cannot delete item. It is already used in stock details.";
    } else {

        /* STEP 2: Safe to delete */
        $stmt = $conn->prepare("DELETE FROM items_master WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        header("Location: manage_items_master.php?deleted=1");
        exit;
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

            header("Location: manage_items_master.php?updated=1");
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

/* ================= FETCH ITEMS ================= */
$items = $conn->query("
    SELECT * 
    FROM items_master 
    ORDER BY item_name ASC
");

ob_start();
?>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body">

        <h5 class="fw-semibold mb-4">
            <i class="bi bi-boxes me-2"></i>Manage Items
        </h5>

        <?php if(isset($_GET['deleted'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Item deleted successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Item updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Sl.No</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th width="220">Actions</th>
                    </tr>
                </thead>
                <tbody>

                <?php $i = 1; ?>
                <?php while($row = $items->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>

                        <!-- UPDATE FORM -->
                        <td>
                            <form method="POST" class="d-flex gap-2 align-items-center">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">

                                <input type="text"
                                       name="item_name"
                                       class="form-control"
                                       value="<?= htmlspecialchars($row['item_name']) ?>"
                                       required>
                        </td>

                        <td>
                                <select name="category" class="form-select" required>
                                    <option value="Computer" <?= $row['category']=='Computer'?'selected':'' ?>>Computer</option>
                                    <option value="Accessory" <?= $row['category']=='Accessory'?'selected':'' ?>>Accessory</option>
                                    <option value="Component" <?= $row['category']=='Component'?'selected':'' ?>>Component</option>
                                    <option value="Networking" <?= $row['category']=='Networking'?'selected':'' ?>>Networking</option>
                                </select>
                        </td>

                        <td class="d-flex gap-2">

                                <!-- UPDATE BUTTON -->
                                <button type="submit"
                                        name="update"
                                        class="btn btn-sm btn-success">
                                    <i class="bi bi-check-circle me-1"></i> Update
                                </button>

                            </form>

                            <!-- DELETE BUTTON OUTSIDE FORM -->
                            <a href="manage_items_master.php?delete=<?= $row['id'] ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Are you sure you want to delete this item?');">
                                <i class="bi bi-trash me-1"></i> Delete
                            </a>

                        </td>
                    </tr>
                <?php endwhile; ?>

                </tbody>
            </table>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>