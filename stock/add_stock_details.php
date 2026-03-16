<?php
$page_title = "Add Stock Details";
$page_icon  = "bi-receipt";
include "../config/db.php";


/* Fetch Items from item_master */
$items = $conn->query("SELECT id, item_name, stock_type FROM items_master ORDER BY item_name ASC");

/* Fetch Vendors */
$vendors = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name ASC");

$errorMsg = "";
$oldSerials = [];
$oldQty = '';
$oldItem = '';
$oldBill = '';
$oldPO = '';
$oldVendor = '';
$oldAmount = '';
$oldWarranty = '';

if(isset($_POST['submit'])){

    $item_id  = (int)($_POST['item_master_id'] ?? 0);
    $model_id = !empty($_POST['model_id']) ? (int)$_POST['model_id'] : null;
    $qty      = (int)($_POST['quantity'] ?? 0);
    $bill_no  = trim($_POST['bill_no'] ?? '');
    $bill_dt  = $_POST['bill_date'] ?: null;
    $po_no    = trim($_POST['po_number'] ?? '');
    $vendor   = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
    $amount   = !empty($_POST['amount']) ? (float)$_POST['amount'] : null;
    $warranty = $_POST['warranty_upto'] ?: null;

    // Basic validation
    if($qty <= 0){
        $errorMsg = "Quantity must be greater than 0.";
    }

    if(!empty($bill_dt) && $bill_dt > date('Y-m-d')){
        $errorMsg = "Bill date cannot be future date.";
    }

    if(empty($errorMsg)){

        // 1️⃣ Fetch item and stock type
        $stmtType = $conn->prepare("SELECT stock_type FROM items_master WHERE id = ?");
        $stmtType->bind_param("i", $item_id);
        $stmtType->execute();
        $resultType = $stmtType->get_result();
        $itemData = $resultType->fetch_assoc();
        $stmtType->close();

        if(!$itemData){
            $errorMsg = "Invalid item selected.";
        } else {
            $stockType = $itemData['stock_type']; // 'serial' or 'non_serial'
        }
    }

    if(empty($errorMsg)){

        // 2️⃣ Check if item has models
        $stmtCheckModel = $conn->prepare("
            SELECT COUNT(*) 
            FROM item_models 
            WHERE item_id = ? AND status='Active'
        ");
        $stmtCheckModel->bind_param("i", $item_id);
        $stmtCheckModel->execute();
        $stmtCheckModel->bind_result($modelCount);
        $stmtCheckModel->fetch();
        $stmtCheckModel->close();

        // Model is required if item has models
        if($modelCount > 0 && empty($model_id)){
            $errorMsg = "Model is required for this item.";
        }

        // Validate selected model belongs to the item (if selected)
        if(!empty($model_id)){
            $stmtModel = $conn->prepare("
                SELECT id 
                FROM item_models 
                WHERE id = ? AND item_id = ? AND status='Active'
            ");
            $stmtModel->bind_param("ii", $model_id, $item_id);
            $stmtModel->execute();
            $stmtModel->store_result();

            if($stmtModel->num_rows == 0){
                $errorMsg = "Selected model does not belong to this item.";
            }
            $stmtModel->close();
        }
    }

    if(empty($errorMsg)){

        // 3️⃣ Serial number validation
        $serials = $_POST['serial_number'] ?? [];
        $serials = array_map('trim', $serials);
        $filledSerials = array_filter($serials);

        // Serialized items: quantity must match serials
        if($stockType === 'serial' && count($filledSerials) != $qty){
            $errorMsg = "Serial numbers must match quantity for serialized items.";
        }

        // Non-serialized items: serials should not be entered
        if($stockType === 'non_serial' && count($filledSerials) > 0){
            $errorMsg = "Serial numbers are not allowed for non-serialized items.";
        }

        // Duplicate serials in form
        if(empty($errorMsg) && count($filledSerials) !== count(array_unique($filledSerials))){
            $errorMsg = "Duplicate serial numbers entered.";
        }

        // Duplicate serials in DB
        if(empty($errorMsg) && !empty($filledSerials)){
            $placeholders = implode(',', array_fill(0, count($filledSerials), '?'));
            $query = "SELECT serial_number FROM stock_details WHERE stock_item_id = ? AND serial_number IN ($placeholders)";
            $stmtCheck = $conn->prepare($query);

            $types = 'i' . str_repeat('s', count($filledSerials));
            $params = array_merge([$item_id], $filledSerials);
            $stmtCheck->bind_param($types, ...$params);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();

            if($resultCheck->num_rows > 0){
                $errorMsg = "One or more serial numbers already exist for this item.";
            }
            $stmtCheck->close();
        }
    }

    // ✅ Only proceed to insert if no errors
    if(empty($errorMsg)){
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                INSERT INTO stock_details
                (stock_item_id, model_id, quantity, serial_number, bill_no, bill_date, po_number, vendor_id, amount, warranty_upto)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if($stockType === 'serial'){
                foreach($filledSerials as $serial){
                    $singleQty = 1;
                    $stmt->bind_param(
                        "iiissssids",
                        $item_id,
                        $model_id,
                        $singleQty,
                        $serial,
                        $bill_no,
                        $bill_dt,
                        $po_no,
                        $vendor,
                        $amount,
                        $warranty
                    );
                    $stmt->execute();
                }
            } else {
                $nullSerial = null;
                $stmt->bind_param(
                    "iiissssids",
                    $item_id,
                    $model_id,
                    $qty,
                    $nullSerial,
                    $bill_no,
                    $bill_dt,
                    $po_no,
                    $vendor,
                    $amount,
                    $warranty
                );
                $stmt->execute();
            }

            $stmt->close();
            $conn->commit();
            header("Location: add_stock_details.php?success=1");
            exit;

        } catch(Exception $e){
            $conn->rollback();
            $errorMsg = "Something went wrong. Please try again.";
        }
    }
}


ob_start();
?>

<div class="container-fluid mt-4">
<div class="card shadow-sm border-0 rounded-4">
<div class="card-body">

<h5 class="mb-4 fw-semibold">Add Stock Purchase Entry</h5>

<?php if(!empty($errorMsg)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<?php if(isset($_GET['success'])): ?>
<div class="alert alert-success">Stock Details Added Successfully!</div>
<?php endif; ?>

<form method="POST" autocomplete="off">

<div class="row">
    <div class="col-md-6 mb-3">
        <label>Item Name</label>
        <select name="item_master_id" id="itemSelect" class="form-select" required>
        <option value="">Select Item</option>
        <?php while($row = $items->fetch_assoc()): ?>
            <option value="<?= $row['id'] ?>"
                    data-type="<?= $row['stock_type'] ?>"
                    <?= ($row['id']==$oldItem)?'selected':'' ?>>
                <?= htmlspecialchars($row['item_name']) ?>
            </option>
        <?php endwhile; ?>
        </select>
        </div>
    <div class="col-md-6 mb-3">
    <label>Model</label>
    <select name="model_id" id="modelSelect" class="form-select"  >
    <option value="">Select Model</option>

    <?php
        $modelQuery = $conn->query("
        SELECT id, model_name, item_id
        FROM item_models
        WHERE status='Active'
        ORDER BY model_name
    ");

    while($model = $modelQuery->fetch_assoc()){
        echo "<option value='{$model['id']}' data-item='{$model['item_id']}'>
        {$model['model_name']}
        </option>";
    }
    ?>

    </select>
    </div>

    <div class="col-md-6 mb-3">
        <label>Quantity</label>
        <input type="number" name="quantity" id="quantityInput" class="form-control" min="1" value="<?= htmlspecialchars($oldQty) ?>" required>
    </div>

    <div class="col-12">
        <div id="serialContainer">
            <?php
            if(!empty($oldSerials)){
                foreach($oldSerials as $i => $serial){
            ?>
                <div class="mb-3">
                    <label>Serial Number <?= $i+1 ?></label>
                    <input type="text" name="serial_number[]" class="form-control" value="<?= htmlspecialchars($serial) ?>" required autocomplete="off">
                </div>
            <?php }} ?>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <label>Bill No</label>
        <input type="text" name="bill_no" class="form-control" value="<?= htmlspecialchars($oldBill) ?>">
    </div>

    <div class="col-md-6 mb-3">
        <label>Bill Date</label>
        <input type="date" name="bill_date" class="form-control" value="<?= htmlspecialchars($bill_dt ?? '') ?>">
    </div>

    <div class="col-md-6 mb-3">
        <label>PO Number</label>
        <input type="text" name="po_number" class="form-control" value="<?= htmlspecialchars($oldPO) ?>">
    </div>

    <div class="col-md-6 mb-3">
        <label>Vendor</label>
        <select name="vendor_id" class="form-select">
            <option value="">Select Vendor</option>
            <?php while($row = $vendors->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= ($row['id']==$oldVendor)?'selected':'' ?>>
                    <?= htmlspecialchars($row['vendor_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="col-md-6 mb-3">
        <label>Amount</label>
        <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($oldAmount) ?>">
    </div>

    <div class="col-md-6 mb-3">
        <label>Warranty Upto</label>
        <input type="date" name="warranty_upto" class="form-control" value="<?= htmlspecialchars($oldWarranty) ?>">
    </div>

</div>

<button type="submit" name="submit" class="btn btn-success rounded-pill">
    Save Stock Details
</button>

</form>

</div>
</div>
</div>

<script>
const itemSelect = document.getElementById("itemSelect");
const modelSelect = document.getElementById("modelSelect");
const qtyInput = document.getElementById("quantityInput");
const serialContainer = document.getElementById("serialContainer");

itemSelect.addEventListener("change", function(){

let item = this.value;
let hasModel = false;

for(let option of modelSelect.options){

    if(option.dataset.item === item){
        option.style.display = "block";
        hasModel = true;
    }
    else if(option.value === ""){
        option.style.display = "block";
    }
    else{
        option.style.display = "none";
    }
}

modelSelect.value = "";

/* Make model required only if item has model */
if(hasModel){
    modelSelect.setAttribute("required","required");
}else{
    modelSelect.removeAttribute("required");
}

if(hasModel){
    modelSelect.removeAttribute("disabled");
}else{
    modelSelect.setAttribute("disabled","disabled");
}

});

function updateSerialFields() {

    const selectedOption = itemSelect.options[itemSelect.selectedIndex];
    const stockType = selectedOption.getAttribute("data-type");
    const qty = parseInt(qtyInput.value);

    serialContainer.innerHTML = "";

    if(stockType === "serial" && qty > 0){

        for(let i = 1; i <= qty; i++){
            serialContainer.innerHTML += `
                <div class="mb-3">
                    <label>Serial Number ${i}</label>
                    <input type="text"
                           name="serial_number[]"
                           class="form-control"
                           required
                           autocomplete="off">
                </div>
            `;
        }

    }
}

itemSelect.addEventListener("change", updateSerialFields);
qtyInput.addEventListener("input", updateSerialFields);


</script>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>