<?php
$page_title = "Add Stock Details";
$page_icon  = "bi-receipt";
require_once __DIR__ . "/../config/db.php";

/* Extract Category ENUM values directly from items_master table schema */
$categoryEnumValues = [];
$enumQuery = $conn->query("SHOW COLUMNS FROM items_master LIKE 'category'");
if ($enumQuery && $enumRow = $enumQuery->fetch_assoc()) {
    preg_match("/^enum\((.*)\)$/", $enumRow['Type'], $matches);
    if (isset($matches[1])) {
        foreach (explode(',', $matches[1]) as $value) {
            $categoryEnumValues[] = trim($value, "'");
        }
    }
}

/* Fetch Active Items with category & stock_type */
$items = $conn->query("SELECT id, item_name, category, stock_type FROM items_master WHERE status='Active' ORDER BY item_name ASC");

/* Fetch Vendors */
$vendors = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name ASC");

$errorMsg = "";
$oldSerials = [];
$oldQty = '';
$oldCategory = '';
$oldItem = '';
$oldModel = '';
$oldBill = '';
$oldBillDt = '';
$oldPO = '';
$oldVendor = '';
$oldAmount = '';
$oldWarranty = '';

if(isset($_POST['submit'])){

    $category    = trim($_POST['category'] ?? '');
    $item_id     = (int)($_POST['item_master_id'] ?? 0);
    $model_id    = !empty($_POST['model_id']) ? (int)$_POST['model_id'] : null;
    $qty         = (int)($_POST['quantity'] ?? 0);
    $bill_no     = trim($_POST['bill_no'] ?? '');
    $bill_dt     = $_POST['bill_date'] ?: null;
    $po_no       = trim($_POST['po_number'] ?? '');
    $vendor      = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
    $amount      = !empty($_POST['amount']) ? (float)$_POST['amount'] : null;
    $warranty    = $_POST['warranty_upto'] ?: null;

    // Repopulate form fields in case of validation fallback
    $oldCategory = $category;
    $oldItem     = $item_id;
    $oldModel    = $model_id;
    $oldQty      = $qty;
    $oldBill     = $bill_no;
    $oldBillDt   = $bill_dt;
    $oldPO       = $po_no;
    $oldVendor   = $vendor;
    $oldAmount   = $amount;
    $oldWarranty = $warranty;
    $oldSerials  = $_POST['serial_number'] ?? [];

    $filledSerials = []; 
    $stockType = 'non_serial'; 

    // Basic validation
    if($qty <= 0){
        $errorMsg = "Quantity must be greater than 0.";
    }

    if(!empty($bill_dt) && $bill_dt > date('Y-m-d')){
        $errorMsg = "Bill date cannot be future date.";
    }

    if(empty($errorMsg)){
        // Fetch item and stock type
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
        // Check if item has models
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
        // Serial number validation processing
        $serials = $_POST['serial_number'] ?? [];
        
        $serials = array_map(function($val) {
            return strtoupper(trim($val));
        }, $serials);
        
        $filledSerials = array_filter($serials);

        if($stockType === 'serial' && count($filledSerials) != $qty){
            $errorMsg = "Serial numbers must match quantity for serialized items.";
        }

        if($stockType === 'non_serial' && count($filledSerials) > 0){
            $errorMsg = "Serial numbers are not allowed for non-serialized items.";
        }

        if(empty($errorMsg) && count($filledSerials) !== count(array_unique($filledSerials))){
            $errorMsg = "Duplicate serial numbers entered.";
        }

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

    // Insert records
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

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <!-- Page Header -->
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="fw-bold mb-1"><i class="bi bi-box-seam text-primary me-2"></i>Add Stock Purchase Entry</h4>
                    <p class="text-muted small mb-0">Record incoming stock items, models, serial numbers, and invoice info.</p>
                </div>
            </div>

            <?php if(!empty($errorMsg)): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($errorMsg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>Stock Details Added Successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" id="stockForm">
                
                <!-- Section 1: Item & Classification Details -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <h6 class="fw-bold text-primary mb-0"><i class="bi bi-tags me-2"></i>1. Product Classification</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-secondary small">Category</label>
                                <select name="category" id="categorySelect" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php foreach($categoryEnumValues as $catVal): ?>
                                        <option value="<?= htmlspecialchars($catVal) ?>" <?= ($catVal === $oldCategory) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($catVal) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-secondary small">Item Name</label>
                                <select name="item_master_id" id="itemSelect" class="form-select bg-light" required disabled>
                                    <option value="">Select Item</option>
                                    <?php 
                                    if($items):
                                        $items->data_seek(0); 
                                        while($row = $items->fetch_assoc()): 
                                    ?>
                                        <option value="<?= $row['id'] ?>"
                                                data-category="<?= htmlspecialchars($row['category']) ?>"
                                                data-type="<?= htmlspecialchars($row['stock_type']) ?>"
                                                <?= ($row['id'] == $oldItem) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($row['item_name']) ?>
                                        </option>
                                    <?php 
                                        endwhile; 
                                    endif;
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-secondary small">Model</label>
                                <select name="model_id" id="modelSelect" class="form-select bg-light" disabled>
                                    <option value="">Select Model</option>
                                    <?php
                                        $modelQuery = $conn->query("
                                            SELECT id, model_name, item_id
                                            FROM item_models
                                            WHERE status='Active'
                                            ORDER BY model_name
                                        ");

                                        while($model = $modelQuery->fetch_assoc()){
                                            $selected = ($model['id'] == $oldModel) ? 'selected' : '';
                                            echo "<option value='{$model['id']}' data-item='{$model['item_id']}' {$selected}>
                                            " . htmlspecialchars($model['model_name']) . "
                                            </option>";
                                        }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Quantity & Serial Mapping -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <h6 class="fw-bold text-primary mb-0"><i class="bi bi-cpu me-2"></i>2. Quantity & Serial Numbers</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-secondary small">Quantity</label>
                                <input type="number" name="quantity" id="quantityInput" class="form-control bg-light" min="1" value="<?= htmlspecialchars($oldQty) ?>" required>
                            </div>
                            <div class="col-md-8 d-flex align-items-end">
                                <span id="stockTypeBadge" class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill small">
                                    <i class="bi bi-info-circle me-1"></i>Select an item to view tracking type
                                </span>
                            </div>

                            <div class="col-12 mt-3">
                                <div id="serialContainer" class="row g-3">
                                    <?php
                                    if(!empty($oldSerials)){
                                        foreach($oldSerials as $i => $serial){
                                    ?>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold text-secondary small">Serial Number <?= $i+1 ?></label>
                                            <input type="text" name="serial_number[]" class="form-control text-uppercase" value="<?= htmlspecialchars($serial) ?>" required autocomplete="off">
                                        </div>
                                    <?php }} ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Purchase & Billing Details -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <h6 class="fw-bold text-primary mb-0"><i class="bi bi-file-earmark-text me-2"></i>3. Invoice & Vendor Information</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-secondary small">Bill No</label>
                                <input type="text" name="bill_no" class="form-control" value="<?= htmlspecialchars($oldBill) ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-secondary small">Bill Date</label>
                                <input type="date" name="bill_date" class="form-control" value="<?= htmlspecialchars($oldBillDt) ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-secondary small">PO Number</label>
                                <input type="text" name="po_number" class="form-control" value="<?= htmlspecialchars($oldPO) ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-secondary small">Vendor</label>
                                <select name="vendor_id" class="form-select">
                                    <option value="">Select Vendor</option>
                                    <?php 
                                    if($vendors):
                                        $vendors->data_seek(0);
                                        while($row = $vendors->fetch_assoc()): 
                                    ?>
                                        <option value="<?= $row['id'] ?>" <?= ($row['id'] == $oldVendor) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($row['vendor_name']) ?>
                                        </option>
                                    <?php 
                                        endwhile; 
                                    endif;
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-secondary small">Total Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">₹</span>
                                    <input type="number" step="0.01" name="amount" class="form-control border-start-0" value="<?= htmlspecialchars($oldAmount) ?>">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-secondary small">Warranty Upto</label>
                                <input type="date" name="warranty_upto" class="form-control" value="<?= htmlspecialchars($oldWarranty) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button Area -->
                <div class="d-flex justify-content-end mb-5">
                    <button type="submit" name="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm">
                        <i class="bi bi-check-circle me-2"></i>Save Stock Details
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<script>
const stockForm      = document.getElementById("stockForm");
const categorySelect = document.getElementById("categorySelect");
const itemSelect     = document.getElementById("itemSelect");
const modelSelect    = document.getElementById("modelSelect");
const qtyInput       = document.getElementById("quantityInput");
const serialContainer= document.getElementById("serialContainer");
const stockTypeBadge = document.getElementById("stockTypeBadge");

/* Step 1: Filter Items based on Selected Category Name */
function filterItems() {
    let selectedCat = categorySelect.value;
    let hasItems = false;
    let currentItemVal = itemSelect.value;

    for (let option of itemSelect.options) {
        if (option.value === "") {
            option.style.display = "block";
            continue;
        }

        if (selectedCat !== "" && option.dataset.category === selectedCat) {
            option.style.display = "block";
            hasItems = true;
        } else {
            option.style.display = "none";
        }
    }

    if (selectedCat && hasItems) {
        itemSelect.removeAttribute("disabled");
        itemSelect.classList.remove("bg-light");
    } else {
        itemSelect.value = "";
        itemSelect.setAttribute("disabled", "disabled");
        itemSelect.classList.add("bg-light");
    }

    let validOption = itemSelect.querySelector(`option[value="${currentItemVal}"]:not([style*="display: none"])`);
    if (!validOption) {
        itemSelect.value = "";
    }

    filterModels();
}

/* Step 2: Filter Models based on Selected Item */
function filterModels() {
    let selectedItem = itemSelect.value;
    let hasModel = false;

    for (let option of modelSelect.options) {
        if (option.value === "") {
            option.style.display = "block";
            continue;
        }

        if (selectedItem !== "" && option.dataset.item === selectedItem) {
            option.style.display = "block";
            hasModel = true;
        } else {
            option.style.display = "none";
        }
    }

    if (selectedItem && hasModel) {
        modelSelect.setAttribute("required", "required");
        modelSelect.removeAttribute("disabled");
        modelSelect.classList.remove("bg-light");
    } else {
        modelSelect.removeAttribute("required");
        modelSelect.setAttribute("disabled", "disabled");
        modelSelect.classList.add("bg-light");
        modelSelect.value = "";
    }

    updateStockTypeBadge();
}

/* Update stock type info badge */
function updateStockTypeBadge() {
    const selectedOption = itemSelect.options[itemSelect.selectedIndex];
    if (selectedOption && selectedOption.value !== "") {
        const type = selectedOption.getAttribute("data-type");
        if (type === "serial") {
            stockTypeBadge.className = "badge bg-warning-subtle text-warning-emphasis px-3 py-2 rounded-pill small";
            stockTypeBadge.innerHTML = `<i class="bi bi-barcode me-1"></i>Serialized Item (Requires ${qtyInput.value || 0} serial numbers)`;
        } else {
            stockTypeBadge.className = "badge bg-info-subtle text-info-emphasis px-3 py-2 rounded-pill small";
            stockTypeBadge.innerHTML = `<i class="bi bi-layers me-1"></i>Non-Serialized Bulk Item`;
        }
    } else {
        stockTypeBadge.className = "badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill small";
        stockTypeBadge.innerHTML = `<i class="bi bi-info-circle me-1"></i>Select an item to view tracking type`;
    }
}

/* Step 3: Render Serial Number Fields dynamically */
function updateSerialFields() {
    const selectedOption = itemSelect.options[itemSelect.selectedIndex];
    updateStockTypeBadge();

    if (!selectedOption || selectedOption.value === "") {
        serialContainer.innerHTML = "";
        return;
    }

    const stockType = selectedOption.getAttribute("data-type");
    const qty = parseInt(qtyInput.value) || 0;

    // Retain rendered inputs if quantity hasn't changed
    if (qty === serialContainer.querySelectorAll('input').length && qty > 0) return;

    serialContainer.innerHTML = "";

    if (stockType === "serial" && qty > 0) {
        for (let i = 1; i <= qty; i++) {
            const col = document.createElement("div");
            col.className = "col-md-4";
            col.innerHTML = `
                <label class="form-label fw-semibold text-secondary small">Serial Number ${i}</label>
                <input type="text"
                       name="serial_number[]"
                       class="form-control text-uppercase"
                       required
                       placeholder="e.g. SN1000${i}"
                       autocomplete="off">
            `;
            serialContainer.appendChild(col);
        }
    }
}

// Event Listeners
categorySelect.addEventListener("change", function() {
    filterItems();
    updateSerialFields();
});

itemSelect.addEventListener("change", function() {
    filterModels();
    updateSerialFields();
});

qtyInput.addEventListener("input", updateSerialFields);

// Enable all disabled select options before submit so values pass to PHP $_POST
stockForm.addEventListener("submit", function() {
    itemSelect.removeAttribute("disabled");
    modelSelect.removeAttribute("disabled");
});

// On Page Load Initialization (Handles old input postbacks after server validation)
window.addEventListener('DOMContentLoaded', () => {
    if (categorySelect.value) {
        filterItems();
        if ("<?= $oldItem ?>") {
            itemSelect.value = "<?= $oldItem ?>";
            filterModels();
        }
        if ("<?= $oldModel ?>") {
            modelSelect.value = "<?= $oldModel ?>";
        }
    }
    updateStockTypeBadge();
});
</script>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>