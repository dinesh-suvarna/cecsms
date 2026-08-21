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

<style>
/* Enterprise UI Local Overrides & Scaled Font Sizing */
.stock-form-card {
    border: 1px solid var(--erp-border, #dce3e9) !important;
    background: #ffffff;
    border-radius: 6px !important;
}

.stock-card-header {
    background: #f8fafc;
    border-bottom: 1px solid var(--erp-border, #dce3e9);
    padding: 0.85rem 1.25rem;
}

.stock-card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--erp-navy-dark, #102f4a);
}

.form-label-erp {
    font-size: 0.82rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 0.35rem;
}

.form-control-erp, .form-select-erp {
    font-size: 0.9rem !important;
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
    border: 1px solid #cbd5e1;
    color: #1e293b;
    transition: all 0.15s ease-in-out;
}

.form-control-erp:focus, .form-select-erp:focus {
    border-color: var(--erp-navy, #173f63) !important;
    box-shadow: 0 0 0 3px rgba(23, 63, 99, 0.1) !important;
}

.form-control-erp[disabled], .form-select-erp[disabled] {
    background-color: #f1f5f9 !important;
    border-color: #e2e8f0;
    color: #94a3b8;
}

.input-group-text-erp {
    font-size: 0.9rem;
    background-color: #f8fafc;
    border: 1px solid #cbd5e1;
    color: #64748b;
    font-weight: 600;
}

.badge-status-erp {
    font-size: 0.8rem;
    padding: 0.5rem 0.85rem;
    border-radius: 4px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.btn-erp-primary {
    background-color: var(--erp-navy, #173f63);
    border-color: var(--erp-navy, #173f63);
    color: #ffffff;
    font-size: 0.9rem;
    font-weight: 600;
    padding: 0.6rem 2rem;
    border-radius: 4px;
    transition: all 0.15s ease;
}

.btn-erp-primary:hover {
    background-color: var(--erp-navy-dark, #102f4a);
    border-color: var(--erp-navy-dark, #102f4a);
    color: #ffffff;
}
</style>
<div class="container-fluid mt-4">

            <!-- Page Header -->
            <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                <div>
                    <h4 class="fw-bold mb-1 text-dark" style="font-size: 1.25rem;">
                        <i class="bi bi-box-seam me-2" style="color: var(--erp-navy, #173f63);"></i>Add Stock Purchase Entry
                    </h4>
                    <p class="text-muted mb-0" style="font-size: 0.85rem;">Record incoming stock items, models, serial numbers, and invoice information.</p>
                </div>
            </div>

            <?php if(!empty($errorMsg)): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-1 shadow-sm mb-4" style="font-size: 0.88rem;" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($errorMsg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-1 shadow-sm mb-4" style="font-size: 0.88rem;" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>Stock Details Added Successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" id="stockForm">
                
                <!-- Section 1: Item & Classification Details -->
                <div class="card stock-form-card shadow-sm mb-4">
                    <div class="stock-card-header">
                        <span class="stock-card-title"><i class="bi bi-tags me-2"></i>1. Product Classification</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label form-label-erp">Category <span class="text-danger">*</span></label>
                                <select name="category" id="categorySelect" class="form-select form-select-erp" required>
                                    <option value="">Select Category</option>
                                    <?php foreach($categoryEnumValues as $catVal): ?>
                                        <option value="<?= htmlspecialchars($catVal) ?>" <?= ($catVal === $oldCategory) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($catVal) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label form-label-erp">Item Name <span class="text-danger">*</span></label>
                                <select name="item_master_id" id="itemSelect" class="form-select form-select-erp" required disabled>
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
                                <label class="form-label form-label-erp">Model</label>
                                <select name="model_id" id="modelSelect" class="form-select form-select-erp" disabled>
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
                <div class="card stock-form-card shadow-sm mb-4">
                    <div class="stock-card-header">
                        <span class="stock-card-title"><i class="bi bi-cpu me-2"></i>2. Quantity & Serial Numbers</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label form-label-erp">Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" id="quantityInput" class="form-control form-control-erp" min="1" value="<?= htmlspecialchars($oldQty) ?>" required>
                            </div>
                            <div class="col-md-8 d-flex align-items-end">
                                <span id="stockTypeBadge" class="badge-status-erp bg-light text-secondary border">
                                    <i class="bi bi-info-circle"></i>Select an item to view tracking type
                                </span>
                            </div>

                            <div class="col-12 mt-3">
                                <div id="serialContainer" class="row g-3">
                                    <?php
                                    if(!empty($oldSerials)){
                                        foreach($oldSerials as $i => $serial){
                                    ?>
                                        <div class="col-md-4">
                                            <label class="form-label form-label-erp">Serial Number <?= $i+1 ?> <span class="text-danger">*</span></label>
                                            <input type="text" name="serial_number[]" class="form-control form-control-erp text-uppercase" value="<?= htmlspecialchars($serial) ?>" required autocomplete="off">
                                        </div>
                                    <?php }} ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Purchase & Billing Details -->
                <div class="card stock-form-card shadow-sm mb-4">
                    <div class="stock-card-header">
                        <span class="stock-card-title"><i class="bi bi-file-earmark-text me-2"></i>3. Invoice & Vendor Information</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label form-label-erp">Bill No</label>
                                <input type="text" name="bill_no" class="form-control form-control-erp" value="<?= htmlspecialchars($oldBill) ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label form-label-erp">Bill Date</label>
                                <input type="date" name="bill_date" class="form-control form-control-erp" value="<?= htmlspecialchars($oldBillDt) ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label form-label-erp">PO Number</label>
                                <input type="text" name="po_number" class="form-control form-control-erp" value="<?= htmlspecialchars($oldPO) ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label form-label-erp">Vendor</label>
                                <select name="vendor_id" class="form-select form-select-erp">
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
                                <label class="form-label form-label-erp">Total Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text input-group-text-erp">₹</span>
                                    <input type="number" step="0.01" name="amount" class="form-control form-control-erp border-start-0" value="<?= htmlspecialchars($oldAmount) ?>">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label form-label-erp">Warranty Upto</label>
                                <input type="date" name="warranty_upto" class="form-control form-control-erp" value="<?= htmlspecialchars($oldWarranty) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button Area -->
                <div class="d-flex justify-content-end mb-5">
                    <button type="submit" name="submit" class="btn btn-erp-primary shadow-sm">
                        <i class="bi bi-check2-circle me-1.5"></i> Save Stock Details
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
    } else {
        itemSelect.value = "";
        itemSelect.setAttribute("disabled", "disabled");
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
    } else {
        modelSelect.removeAttribute("required");
        modelSelect.setAttribute("disabled", "disabled");
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
            stockTypeBadge.className = "badge-status-erp bg-warning-subtle text-warning-emphasis border border-warning-subtle";
            stockTypeBadge.innerHTML = `<i class="bi bi-barcode"></i>Serialized Item (Requires ${qtyInput.value || 0} serial numbers)`;
        } else {
            stockTypeBadge.className = "badge-status-erp bg-info-subtle text-info-emphasis border border-info-subtle";
            stockTypeBadge.innerHTML = `<i class="bi bi-layers"></i>Non-Serialized Bulk Item`;
        }
    } else {
        stockTypeBadge.className = "badge-status-erp bg-light text-secondary border";
        stockTypeBadge.innerHTML = `<i class="bi bi-info-circle"></i>Select an item to view tracking type`;
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
                <label class="form-label form-label-erp">Serial Number ${i} <span class="text-danger">*</span></label>
                <input type="text"
                       name="serial_number[]"
                       class="form-control form-control-erp text-uppercase"
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