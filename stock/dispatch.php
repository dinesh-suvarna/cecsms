<?php
include "../config/db.php";
include "../includes/session.php";
include "../includes/csrf.php";
include "../includes/functions.php";

$user_id   = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? '';

if($user_role !== 'SuperAdmin'){
    echo "<div class='container mt-5'>
            <div class='alert alert-danger text-center'>
                <h5>Access Denied</h5>
                <p>Only Superadmin can dispatch stock.</p>
            </div>
          </div>";
    exit;
}

$page_title = "Dispatch Stock";
$page_icon  = "bi-truck";

/* Fetch Institutions */
$institutions = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name ASC");

/* Fetch stock with Model Info */
$stocks = $conn->query("
    SELECT 
        sd.id,
        im.item_name,
        im.category,
        mdl.model_name,
        sd.serial_number,
        sd.quantity,
        im.stock_type,
        sd.status,
        IFNULL((SELECT SUM(dd.quantity - IFNULL(dd.returned_quantity,0)) 
                FROM dispatch_details dd 
                WHERE dd.stock_detail_id = sd.id), 0) AS dispatched_qty
    FROM stock_details sd
    JOIN items_master im ON sd.stock_item_id = im.id
    LEFT JOIN item_models mdl ON sd.model_id = mdl.id
    WHERE sd.status = 'available'
    ORDER BY im.category ASC, im.item_name ASC, mdl.model_name ASC
");

$error = "";
$success = "";

if(isset($_POST['submit'])){
    $institution = (int)$_POST['institution_id'];
    $division    = (int)$_POST['division_id'];
    $unit        = (int)$_POST['unit_id'];
    $date        = $_POST['dispatch_date'];
    $remarks     = trim($_POST['remarks'] ?? '');
    $stock_ids   = $_POST['stock_ids'] ?? [];
    $bulk_qty    = $_POST['bulk_qty'] ?? [];

    if(empty($stock_ids) && empty($bulk_qty)){
        notify("warning", "Select at least one item");
        header("Location: " . $_SERVER['PHP_SELF']);
    exit;
        
    }

    if(empty($error)){
        $conn->begin_transaction();
        try{
            $stmt1 = $conn->prepare("INSERT INTO dispatch_master (institution_id, division_id, unit_id, dispatch_date, remarks, user_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt1->bind_param("iiissi", $institution, $division, $unit, $date, $remarks, $user_id);
            $stmt1->execute();
            $dispatch_id = $conn->insert_id;
            $stmt1->close();

            $updateSerial = $conn->prepare("UPDATE stock_details SET status='dispatched' WHERE id=? AND status='available'");
            $updateBulk   = $conn->prepare("UPDATE stock_details SET quantity = quantity - ? WHERE id=? AND quantity >= ?");
            $insertDetails = $conn->prepare("INSERT INTO dispatch_details (dispatch_id, stock_detail_id, quantity) VALUES (?, ?, ?)");

            foreach($stock_ids as $sid){
                $sid = (int)$sid;
                $updateSerial->bind_param("i",$sid);
                $updateSerial->execute();
                $qty = 1;
                $insertDetails->bind_param("iii",$dispatch_id,$sid,$qty);
                $insertDetails->execute();
            }

            
            foreach($bulk_qty as $sid => $qty){
                $sid = (int)$sid;
                $qty = (int)$qty;
                if($qty <= 0) continue;

                

                // ONLY record the dispatch
                $insertDetails->bind_param("iii", $dispatch_id, $sid, $qty);
                $insertDetails->execute();
            }

            $updateSerial->close();
            $updateBulk->close();
            $insertDetails->close();
            
            $conn->commit();
            

        
            notify("success", "Stock Dispatched successfully");
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }catch(Exception $e){
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

ob_start();
?>

<style>
    /* Layout Containers */
    .inventory-source { 
        max-height: 650px; 
        overflow-y: auto; 
        padding-right: 10px; 
    }
    
    .sticky-top-card { 
        position: sticky; 
        top: 20px; 
    }

    /* Grid for Serials */
    .serial-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 10px;
        width: 100%;
    }

    /* Combined Button Logic */
    .btn-hardware {
        min-height: 40px;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        text-align: left;
        width: 100%;
        padding: 5px 12px;
        font-size: 0.8rem;
        border-radius: 8px; /* Slightly more modern than 20px for grid items */
        border: 1px solid #dee2e6;
        background: #fff;
        transition: all 0.2s ease;
        
        /* Text Truncation Logic */
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .btn-hardware:hover {
        border-color: #4361ee;
        background-color: #f8f9ff;
        color: #4361ee;
    }

    /* Utility Classes */
    .item-hidden { display: none !important; }
    
    .category-label { 
        font-size: 0.75rem; 
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 1px; 
        color: #adb5bd; 
        margin-top: 1rem;
    }

    .model-header { 
        cursor: pointer; 
        background: #f8f9fa; 
        transition: 0.2s; 
    }
    .model-header:hover { background: #e9ecef; }
</style>

<div class="container-fluid mt-4">
    <form method="POST">
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body">
                        <h5 class="fw-semibold mb-3"><i class="bi bi-geo-alt me-2 text-primary"></i>Dispatch Details</h5>
                        <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                        <?php if($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
                        
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="small fw-bold">Institution</label>
                                <select name="institution_id" class="form-select" required>
                                    <option value="">Select Institution</option>
                                    <?php while($row = $institutions->fetch_assoc()): ?>
                                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['institution_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold">Division</label>
                                <select name="division_id" class="form-select" required><option value="">Select Division</option></select>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold">Unit</label>
                                <select name="unit_id" class="form-select" required><option value="">Select Unit</option></select>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold">Date</label>
                                <input type="date" name="dispatch_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-12">
                                <input type="text" name="remarks" class="form-control" placeholder="Add remarks or reference details...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <input type="text" id="stockSearch" class="form-control" placeholder="Search item, model, or serial...">
                        </div>

                        <div class="inventory-source">
                            <?php
                            $grouped = [];
                            $stocks->data_seek(0);
                            while($row = $stocks->fetch_assoc()){
                                $rem = $row['quantity'] - $row['dispatched_qty'];
                                if($rem <= 0) continue;
                                $grouped[$row['category']][$row['item_name']][$row['model_name'] ?: 'Standard'][] = $row;
                            }

                            $m_idx = 0;
                            foreach($grouped as $cat => $items): ?>
                                <div class="category-section mb-3">
                                    <p class="category-label fw-bold text-uppercase mb-2"><?= $cat ?></p>
                                    <?php foreach($items as $itemName => $models): ?>
                                        <div class="accordion accordion-flush mb-2" id="acc-<?= md5($itemName) ?>">
                                            <?php foreach($models as $modelName => $units): 
                                                $m_idx++;
                                                $collapseId = "collapse" . $m_idx;
                                            ?>
                                                <div class="accordion-item border rounded-3 mb-2 overflow-hidden shadow-sm">
                                                    <h2 class="accordion-header">
                                                        <button class="accordion-button collapsed py-2 px-3 small fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                                                            <i class="bi bi-cpu me-2"></i> <?= htmlspecialchars($itemName) ?> - <?= htmlspecialchars($modelName) ?>
                                                            <span class="badge bg-light text-dark ms-auto me-2 border"><?= count($units) ?></span>
                                                        </button>
                                                    </h2>
                                                    <div id="<?= $collapseId ?>" class="accordion-collapse collapse" data-bs-parent="#acc-<?= md5($itemName) ?>">
                                                        <div class="accordion-body p-2 bg-white">
                                                            <div class="serial-grid"> <?php 
                                                                foreach($units as $u): 
                                                                    $available = $u['quantity'] - $u['dispatched_qty']; 
                                                                    if($available <= 0) continue;
                                                                    $lowerItem = strtolower($itemName);
                                                                    $lowerCat  = strtolower($cat);
                                                                    
                                                                    // Comprehensive Icon Logic
                                                                    if (str_contains($lowerItem, 'mouse')) {
                                                                        $icon = 'bi-mouse3';
                                                                    } elseif (str_contains($lowerItem, 'keyboard')) {
                                                                        $icon = 'bi-keyboard';
                                                                    } elseif (str_contains($lowerItem, 'computer') || str_contains($lowerItem, 'desktop') || str_contains($lowerItem, 'monitor')) {
                                                                        $icon = 'bi-pc-display';
                                                                    } elseif (str_contains($lowerItem, 'printer')) {
                                                                        $icon = 'bi-printer';
                                                                    } elseif (str_contains($lowerItem, 'scanner')) {
                                                                        $icon = 'bi-qr-code-scan'; // Or 'bi-scanner' if using latest Bootstrap Icons
                                                                    } elseif (str_contains($lowerItem, 'cctv') || str_contains($lowerItem, 'camera')) {
                                                                        $icon = 'bi-camera-video';
                                                                    } elseif (str_contains($lowerItem, 'ups') || str_contains($lowerItem, 'battery')) {
                                                                        $icon = 'bi-lightning-charge';
                                                                    } else {
                                                                        $icon = 'bi-box'; // Your requested default
                                                                    }
                                                            
                                                                ?>
                                                                    <button type="button" 
                                                                        id="btn-stock-<?= $u['id'] ?>"
                                                                        class="btn btn-hardware btn-add-item" 
                                                                        data-id="<?= $u['id'] ?>" 
                                                                        data-name="<?= htmlspecialchars($itemName) ?>"
                                                                        data-serial="<?= $u['serial_number'] ?: 'BULK' ?>"
                                                                        data-type="<?= $u['serial_number'] ? 'serial' : 'bulk' ?>"
                                                                        data-max="<?= $available ?>">
                                                                        
                                                                        <i class="bi <?= $icon ?> me-2 text-primary"></i>
                                                                        <?= $u['serial_number'] ?: "QTY: $available" ?>
                                                                    </button>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card shadow-sm border-0 rounded-4 sticky-top-card">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3 text-success"><i class="bi bi-list-check me-2"></i>Dispatch Queue</h6>
                        <div class="table-responsive" style="min-height: 300px;">
                            <table class="table align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Asset Details</th>
                                        <th>Serial</th>
                                        <th style="width: 110px;">Qty</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="dispatchBody">
                                    <tr id="emptyMsg"><td colspan="4" class="text-center py-5 text-muted small">No items selected yet.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="small fw-bold text-muted" id="itemCounter">0 items selected</span>
                            <button type="submit" name="submit" class="btn btn-success rounded-pill px-5 shadow fw-bold">Confirm Dispatch</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('stockSearch').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    
    // 1. Loop through every accordion item (The Model level)
    document.querySelectorAll('.accordion-item').forEach(accordion => {
        let hasVisibleHardware = false;
        
        // 2. Loop through every serial/hardware button inside this accordion
        accordion.querySelectorAll('.btn-hardware').forEach(btn => {
            // Check item name, serial, or data-attributes
            let content = btn.innerText.toLowerCase() + " " + btn.dataset.serial.toLowerCase();
            
            if (content.includes(filter)) {
                btn.classList.remove('d-none'); // Show matching button
                hasVisibleHardware = true;
            } else {
                btn.classList.add('d-none'); // Hide non-matching button
            }
        });

        // 3. Handle Accordion Visibility & Auto-Open
        const collapseElement = accordion.querySelector('.accordion-collapse');
        const bsCollapse = bootstrap.Collapse.getInstance(collapseElement) || new bootstrap.Collapse(collapseElement, {toggle: false});

        if (hasVisibleHardware && filter !== "") {
            accordion.classList.remove('d-none');
            bsCollapse.show(); // Auto-expand to show the result
        } else if (hasVisibleHardware && filter === "") {
            accordion.classList.remove('d-none');
            bsCollapse.hide(); // Collapse back when search is cleared
        } else {
            accordion.classList.add('d-none'); // Hide the whole model if no match
        }
    });

    // 4. Hide Category Labels (Desktop/Laptop/etc) if all their children are hidden
    document.querySelectorAll('.category-section').forEach(section => {
        const visibleItems = section.querySelectorAll('.accordion-item:not(.d-none)');
        section.style.display = (visibleItems.length > 0) ? '' : 'none';
    });
});

const institutionSelect = document.querySelector("[name='institution_id']");
const divisionSelect    = document.querySelector("[name='division_id']");
const unitSelect        = document.querySelector("[name='unit_id']");

institutionSelect.addEventListener("change", function(){
    fetch("get_divisions_units.php?institution_id=" + this.value).then(res => res.json()).then(data => {
        divisionSelect.innerHTML = '<option value="">Select Division</option>';
        data.divisions.forEach(div => { divisionSelect.innerHTML += `<option value="${div.id}">${div.name}</option>`; });
        unitSelect.innerHTML = '<option value="">Select Unit</option>';
        data.units.forEach(unit => { unitSelect.innerHTML += `<option value="${unit.id}">${unit.name}</option>`; });
    });
});

divisionSelect.addEventListener("change", function(){
    fetch("get_divisions_units.php?division_id=" + this.value).then(res => res.json()).then(data => {
        unitSelect.innerHTML = '<option value="">Select Unit</option>';
        data.units.forEach(unit => { unitSelect.innerHTML += `<option value="${unit.id}">${unit.name}</option>`; });
    });
});

// --- DYNAMIC DISPATCH LOGIC ---

document.querySelectorAll('.btn-add-item').forEach(btn => {
    btn.addEventListener('click', function() {
        const d = this.dataset;
        const body = document.getElementById('dispatchBody');
        const empty = document.getElementById('emptyMsg');

        // Hide item from left list
        this.classList.add('item-hidden');

        if(empty) empty.remove();

        
        const row = document.createElement('tr');
        row.id = "queue-row-" + d.id;
        row.innerHTML = `
            <td><div class="small fw-bold">${d.name}</div></td>
            <td><span class="badge bg-light text-dark border">${d.serial}</span></td>
            <td>
                ${d.type === 'serial' 
                    ? `<input type="hidden" name="stock_ids[]" value="${d.id}"> <span class="badge bg-info">1 Unit</span>` 
                    : `<div class="input-group input-group-sm" style="width: 120px;">
                        <input type="number" name="bulk_qty[${d.id}]" class="form-control" value="1" min="1" max="${d.max}">
                        <span class="input-group-text">/ ${d.max}</span>
                    </div>`
                }
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row" data-id="${d.id}">
                    <i class="bi bi-trash3-fill fs-5"></i>
                </button>
            </td>
        `;
        body.appendChild(row);
        updateUI();

        // Re-enable item on remove
        row.querySelector('.remove-row').addEventListener('click', function() {
            const originalBtn = document.getElementById('btn-stock-' + this.dataset.id);
            if(originalBtn) originalBtn.classList.remove('item-hidden');
            row.remove();
            updateUI();
        });
    });
});

function updateUI() {
    const count = document.querySelectorAll('#dispatchBody tr:not(#emptyMsg)').length;
    document.getElementById('itemCounter').innerText = count + " items in queue";
    if(count === 0 && !document.getElementById('emptyMsg')) {
        document.getElementById('dispatchBody').innerHTML = '<tr id="emptyMsg"><td colspan="4" class="text-center py-5 text-muted small">No items selected yet.</td></tr>';
    }
}


</script>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>