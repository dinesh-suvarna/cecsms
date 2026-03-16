<?php
require_once "../config/db.php";
require_once "../includes/session.php";
require_once "../admin/auth.php";

$page_title = "Add Item (Master)";
$page_icon  = "bi-box";

$success = "";
$error   = "";

$item_name = "";
$category  = "";
$stock_type = "serial";

if(isset($_POST['submit'])){

    $item_name  = trim($_POST['item_name']);
    $category   = $_POST['category'] ?? '';
    $stock_type = $_POST['stock_type'] ?? 'serial';

    if(empty($item_name)){
        $error = "Item name is required.";
    } elseif(!in_array($stock_type, ['serial','non_serial'])){
        $error = "Invalid stock type selected.";
    } else {

        try {

            $stmt = $conn->prepare("
                INSERT INTO items_master (item_name, category, stock_type)
                VALUES (?, ?, ?)
            ");

            $stmt->bind_param("sss", $item_name, $category, $stock_type);
            $stmt->execute();

            $success = "Item added successfully!";

            // Clear form after success
            $item_name = "";
            $category  = "";
            $stock_type = "serial";

        } catch(mysqli_sql_exception $e){

            if($e->getCode() == 1062){
                $error = "Item already exists!";
            } else {
                $error = "Database error occurred.";
            }
        }
    }
}
ob_start();
?>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body">

        <h5 class="fw-semibold mb-4">
            <i class="bi bi-box me-2"></i>Add Item (Master)
        </h5>

        <?php if($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Stock Type</label>
                <select name="stock_type" class="form-select" required>
                    <option value="serial" <?= ($stock_type=='serial')?'selected':'' ?>>
                        Serialized (Has Serial Number)
                    </option>
                    <option value="non_serial" <?= ($stock_type=='non_serial')?'selected':'' ?>>
                        Non-Serialized (Bulk Quantity)
                    </option>
                </select>
            </div>

             <div class="mb-3">
                <label class="form-label">Category</label>
                <select name="category" class="form-select" required>
                    <option value="Computer" <?= ($category=='Computer')?'selected':'' ?>>Computer</option>
                    <option value="Accessory" <?= ($category=='Accessory')?'selected':'' ?>>Accessory</option>
                    <option value="Component" <?= ($category=='Component')?'selected':'' ?>>Component</option>
                    <option value="Networking" <?= ($category=='Networking')?'selected':'' ?>>Networking</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Item Name</label>
                <input type="text"
                        name="item_name"
                        value="<?= htmlspecialchars($item_name) ?>"
                        class="form-control"
                        placeholder="Enter item name (e.g., Printer)"
                        required>
            </div>

           

            <button type="submit"
                    name="submit"
                    class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Save Item
            </button>

        </form>

    </div>
</div>

<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>