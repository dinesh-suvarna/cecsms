<?php
require_once __DIR__ . "/../config/db.php";
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

/* ---------- HELPER: DYNAMIC CATEGORY & ITEM ICONS ---------- */
if (!function_exists('getCategoryIcon')) {
    function getCategoryIcon(string $category): string {
        $cat = strtolower(trim($category));
        if (str_contains($cat, 'computer') || str_contains($cat, 'pc') || str_contains($cat, 'laptop')) {
            return 'bi-pc-display';
        } elseif (str_contains($cat, 'accessory') || str_contains($cat, 'peripherals')) {
            return 'bi-keyboard';
        } elseif (str_contains($cat, 'network') || str_contains($cat, 'router') || str_contains($cat, 'switch')) {
            return 'bi-box-seam';
        } elseif (str_contains($cat, 'component') || str_contains($cat, 'hardware')) {
            return 'bi-cpu';
        } elseif (str_contains($cat, 'furniture')) {
            return 'bi-lamp';
        } elseif (str_contains($cat, 'mobile') || str_contains($cat, 'phone')) {
            return 'bi-phone';
        }
        return 'bi-folder';
    }
}

if (!function_exists('getItemDetailIcon')) {
    function getItemDetailIcon(?string $itemName, ?string $category = ''): string {
        $name = strtolower(trim($itemName ?? ''));
        $cat  = strtolower(trim($category ?? ''));

        // Remove spaces, hyphens, underscores to match variations like "ip com", "access point", etc.
        $cleanName = str_replace([' ', '-', '_'], '', $name);

        switch (true) {
            // 1. HIGH-PRIORITY SPECIFIC ITEM MATCHES
            case (
                str_contains($cleanName, 'accesspoint') || 
                str_contains($cleanName, 'ipcom') || 
                str_contains($name, 'wifi')
            ):
                return 'bi-wifi';

            case (str_contains($name, 'rack') || str_contains($name, 'server')):
                return 'bi-hdd-rack'; 

            case (str_contains($name, 'switch') || str_contains($name, 'patch panel') || str_contains($name, 'hub')):
                return 'bi-hdd-stack'; 

            case (str_contains($name, 'router')):
                return 'bi-router';

            case (str_contains($name, 'computer') || str_contains($name, 'desktop')):
                return 'bi-pc-display';

            case (str_contains($name, 'laptop')):
                return 'bi-laptop';

            case (str_contains($name, 'monitor') || str_contains($name, 'display')):
                return 'bi-display';

            case (str_contains($name, 'printer')):
                return 'bi-printer';

            case (str_contains($name, 'keyboard')):
                return 'bi-keyboard';

            case (str_contains($name, 'mouse')):
                return 'bi-mouse3';

            case (str_contains($name, 'projector')):
                return 'bi-projector'; 

            case (str_contains($name, 'biometric') || str_contains($name, 'fingerprint')):
                return 'bi-person-bounding-box';

            case (str_contains($name, 'ups') || str_contains($name, 'battery')):
                return 'bi-lightning-charge';

            case (str_contains($name, 'table') || str_contains($name, 'desk')):
                return 'bi-table';

            case (str_contains($name, 'chair')):
                return 'bi-person-workspace';

            case (str_contains($name, 'camera') || str_contains($name, 'cctv')):
                return 'bi-camera-video';

            // 2. CATEGORY FALLBACKS 
            case (str_contains($cat, 'computer')):
                return 'bi-pc-display';

            case (str_contains($cat, 'network')):
                return 'bi-box-seam';

            case (str_contains($cat, 'biometric')):
                return 'bi-person-bounding-box';

            case (str_contains($cat, 'mobile') || str_contains($name, 'phone')):
                return 'bi-phone';

            default:
                return 'bi-box-seam';
        }
    }
}

ob_start();
?>

<style>
:root {
    --erp-border: #e2e8f0;
    --erp-bg-body: #f8fafc;
    --erp-navy: #173f63;
    --erp-navy-dark: #102f4a;
    --erp-navy-accent: #004085;
    --erp-blue-bg: #dbeafe;
    --erp-blue-text: #1d4ed8;
    --erp-text-muted: #64748b;


.stock-page-wrapper {
    padding: 0.5rem 1.25rem 2rem 1.25rem;
}

.page-header-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--erp-navy);
}

.header-icon-box {
    width: 42px;
    height: 42px;
    background-color: #eff6ff;
    color: var(--erp-navy-accent);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    border: 1px solid #dbeafe;
}

.erp-card {
    background: #ffffff;
    border: 1px solid var(--erp-border) !important;
    border-radius: 8px !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}

.erp-card-header {
    background: #f8fafc;
    border-bottom: 1px solid var(--erp-border);
    padding: 0.85rem 1.25rem;
}

.erp-card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--erp-navy);
    margin: 0;
}

.erp-card-body {
    padding: 1.25rem;
}

.form-label-erp {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--erp-text-muted);
    margin-bottom: 0.35rem;
}

/* DYNAMIC AUTO-RESIZE SELECTS (From reports.php logic) */
.erp-card .auto-resize-select {
    font-size: 0.88rem !important;
    border-color: #cbd5e1;
    border-radius: 6px;
    padding: 0.45rem 2.25rem 0.45rem 0.75rem;
    transition: width 0.15s ease-in-out, border-color 0.2s ease-in-out;
    max-width: 100% !important;
    min-width: 160px;
    box-sizing: border-box;
}

.erp-card .form-control {
    font-size: 0.88rem !important;
    border-color: #cbd5e1;
    border-radius: 6px;
    padding: 0.45rem 0.75rem;
}

.erp-card .form-control:focus, 
.erp-card .auto-resize-select:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
}

.category-accordion-btn {
    background: #f8fafc !important;
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--erp-navy) !important;
    padding: 0.65rem 0.9rem;
    box-shadow: none !important;
}

.category-accordion-btn:not(.collapsed) {
    border-bottom: 1px solid var(--erp-border);
    background-color: #f1f5f9 !important;
}

.item-accordion-card {
    border: 1px solid var(--erp-border) !important;
    border-radius: 6px !important;
    margin-bottom: 0.5rem;
    background: #ffffff;
    overflow: hidden;
}

.item-accordion-btn {
    padding: 0.55rem 0.75rem;
    background: #ffffff !important;
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
    box-shadow: none !important;
}

.item-accordion-btn:not(.collapsed) {
    background-color: #f8fafc !important;
    border-bottom: 1px solid var(--erp-border);
}

.inventory-source { 
    max-height: 520px; 
    overflow-y: auto; 
    padding-right: 6px; 
}

.serial-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 8px;
    width: 100%;
}

.btn-hardware {
    min-height: 36px;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    width: 100%;
    padding: 5px 8px;
    font-size: 0.78rem;
    font-weight: 600;
    border-radius: 6px; 
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    transition: all 0.15s ease;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.btn-hardware:hover {
    border-color: #2563eb;
    background-color: #eff6ff;
    color: #1d4ed8;
}

.item-hidden { display: none !important; }

.sticky-top-card { 
    position: sticky; 
    top: 15px; 
}

.erp-subtable {
    margin-bottom: 0;
    font-size: 0.88rem;
}

.erp-subtable thead th {
    background-color: #f8fafc;
    color: var(--erp-text-muted);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--erp-border);
    padding: 0.65rem 0.85rem;
}

.erp-subtable td {
    padding: 0.65rem 0.85rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

.badge-erp {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.35rem 0.65rem;
    border-radius: 4px;
}

.btn-erp-navy {
    background-color: var(--erp-navy);
    border-color: var(--erp-navy);
    color: #ffffff;
    font-weight: 600;
    font-size: 0.88rem;
    transition: all 0.15s ease;
}

.btn-erp-navy:hover {
    background-color: var(--erp-navy-dark);
    border-color: var(--erp-navy-dark);
    color: #ffffff;
}
</style>

<div class="stock-page-wrapper">
    <!-- PAGE HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pt-2 mb-3 pb-2 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <div class="header-icon-box">
                <i class="bi <?= $page_icon ?>"></i>
            </div>
            <div>
                <h4 class="page-header-title m-0"><?= $page_title ?></h4>
                <p class="text-muted m-0" style="font-size: 0.85rem;">Select destination details and add available items to the dispatch queue.</p>
            </div>
        </div>
    </div>

    <form method="POST" id="dispatchForm">
        <!-- DESTINATION CARD -->
        <div class="erp-card mb-3">
            <div class="erp-card-header d-flex align-items-center gap-2">
                <i class="bi bi-geo-alt text-primary fs-6"></i>
                <h5 class="erp-card-title">Dispatch Destination & Details</h5>
            </div>
            <div class="erp-card-body">
                <?php if($error): ?><div class="alert alert-danger py-2" style="font-size:0.88rem;"><?= $error ?></div><?php endif; ?>
                <?php if($success): ?><div class="alert alert-success py-2" style="font-size:0.88rem;"><?= $success ?></div><?php endif; ?>
                
                <div class="row g-3 align-items-end">
                    <div class="col-auto">
                        <label class="form-label-erp">Institution</label>
                        <select name="institution_id" class="form-select auto-resize-select" required title="Select Institution">
                            <option value="">Select Institution</option>
                            <?php while($row = $institutions->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>" title="<?= htmlspecialchars($row['institution_name']) ?>">
                                    <?= htmlspecialchars($row['institution_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label-erp">Division</label>
                        <select name="division_id" class="form-select auto-resize-select" required title="Select Division">
                            <option value="">Select Division</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label-erp">Unit</label>
                        <select name="unit_id" class="form-select auto-resize-select" required title="Select Unit">
                            <option value="">Select Unit</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label-erp">Dispatch Date</label>
                        <input type="date" name="dispatch_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-12 mt-2">
                        <input type="text" name="remarks" class="form-control" placeholder="Add remarks or reference details (optional)...">
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- LEFT COLUMN: INVENTORY SELECTOR -->
            <div class="col-lg-5">
                <div class="erp-card">
                    <div class="erp-card-header">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 pe-1"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="stockSearch" class="form-control border-start-0 shadow-none" placeholder="Search item, model, or serial...">
                        </div>
                    </div>
                    <div class="erp-card-body">
                        <div class="inventory-source">
                            <?php
                            $grouped = [];
                            $stocks->data_seek(0);
                            while($row = $stocks->fetch_assoc()){
                                $rem = $row['quantity'] - $row['dispatched_qty'];
                                if($rem <= 0) continue;
                                $grouped[$row['category']][$row['item_name']][$row['model_name'] ?: 'Standard'][] = $row;
                            }

                            $cat_idx = 0;
                            $m_idx = 0;
                            foreach($grouped as $cat => $items): 
                                $cat_idx++;
                                $catCollapseId = "catCollapse" . $cat_idx;
                                $catIcon = getCategoryIcon($cat);
                                
                                $catTotalCount = 0;
                                foreach($items as $models) {
                                    foreach($models as $units) {
                                        $catTotalCount += count($units);
                                    }
                                }
                            ?>
                                <!-- CATEGORY ACCORDION (LEVEL 1) -->
                                <div class="accordion accordion-flush category-section mb-2">
                                    <div class="accordion-item border rounded-3 overflow-hidden shadow-sm">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button category-accordion-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $catCollapseId ?>">
                                                <div class="d-flex align-items-center gap-2 w-100 me-2">
                                                    <i class="bi <?= $catIcon ?>"></i> 
                                                    <span><?= htmlspecialchars($cat) ?></span>
                                                    <span class="badge bg-light text-muted border ms-auto rounded-pill" style="font-size:0.7rem;"><?= $catTotalCount ?> Units</span>
                                                </div>
                                            </button>
                                        </h2>
                                        
                                        <div id="<?= $catCollapseId ?>" class="accordion-collapse collapse">
                                            <div class="accordion-body p-2 bg-white">
                                                
                                                <!-- MODEL ACCORDION (LEVEL 2) -->
                                                <?php foreach($items as $itemName => $models): 
                                                    $itemIcon = getItemDetailIcon($itemName, $cat);
                                                ?>
                                                    <div class="accordion accordion-flush mb-2">
                                                        <?php foreach($models as $modelName => $units): 
                                                            $m_idx++;
                                                            $modelCollapseId = "modelCollapse" . $m_idx;
                                                        ?>
                                                            <div class="accordion-item item-accordion-card" data-search-target="<?= htmlspecialchars(strtolower($itemName . ' ' . $modelName)) ?>">
                                                                <h2 class="accordion-header">
                                                                    <button class="accordion-button item-accordion-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $modelCollapseId ?>">
                                                                        <div class="d-flex align-items-center gap-2 w-100 me-2">
                                                                            <i class="bi <?= $itemIcon ?> text-secondary"></i> 
                                                                            <span><?= htmlspecialchars($itemName) ?> <span class="text-muted fw-normal"> - <?= htmlspecialchars($modelName) ?></span></span>
                                                                            <span class="badge bg-light text-dark border ms-auto rounded-pill" style="font-size:0.7rem;"><?= count($units) ?></span>
                                                                        </div>
                                                                    </button>
                                                                </h2>
                                                                
                                                                <div id="<?= $modelCollapseId ?>" class="accordion-collapse collapse">
                                                                    <div class="accordion-body p-2 bg-white">
                                                                        <div class="serial-grid"> 
                                                                            <?php foreach($units as $u): 
                                                                                $available = $u['quantity'] - $u['dispatched_qty']; 
                                                                                if($available <= 0) continue;
                                                                            ?>
                                                                                <button type="button" 
                                                                                    id="btn-stock-<?= $u['id'] ?>"
                                                                                    class="btn btn-hardware btn-add-item" 
                                                                                    data-id="<?= $u['id'] ?>" 
                                                                                    data-name="<?= htmlspecialchars($itemName) ?>"
                                                                                    data-serial="<?= $u['serial_number'] ?: 'BULK' ?>"
                                                                                    data-type="<?= $u['serial_number'] ? 'serial' : 'bulk' ?>"
                                                                                    data-max="<?= $available ?>">
                                                                                    
                                                                                    <i class="bi <?= $itemIcon ?> me-1 text-secondary"></i>
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
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: QUEUE & CONFIRMATION -->
            <div class="col-lg-7">
                <div class="erp-card sticky-top-card">
                    <div class="erp-card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-list-check text-primary fs-6"></i>
                            <h5 class="erp-card-title">Dispatch Queue</h5>
                        </div>
                        <span class="badge bg-light text-secondary border fw-semibold" id="itemCounter">0 items in queue</span>
                    </div>
                    <div class="erp-card-body p-0">
                        <div class="table-responsive" style="min-height: 380px;">
                            <table class="table erp-subtable align-middle">
                                <thead>
                                    <tr>
                                        <th>Asset Details</th>
                                        <th>Tracking / Serial</th>
                                        <th style="width: 140px;">Qty</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="dispatchBody">
                                    <tr id="emptyMsg">
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-1 opacity-50"></i>
                                            <span style="font-size: 0.88rem;">No items selected yet. Click hardware items from the inventory to queue them.</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 bg-light border-top d-flex justify-content-end align-items-center">
                            <button type="submit" name="submit" class="btn btn-erp-navy px-4 py-2">
                                <i class="bi bi-check2-circle me-1"></i> Confirm Dispatch
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// --- AUTO RESIZE SELECT LOGIC (EXACT MATCH FROM reports.php) ---
function autoResizeSelect(selectElement) {
    if (!selectElement) return;

    const tempSpan = document.createElement('span');
    tempSpan.style.visibility = 'hidden';
    tempSpan.style.position = 'absolute';
    tempSpan.style.whiteSpace = 'nowrap';

    const style = window.getComputedStyle(selectElement);
    tempSpan.style.font = style.font;
    tempSpan.style.fontSize = style.fontSize;
    tempSpan.style.fontFamily = style.fontFamily;
    tempSpan.style.fontWeight = style.fontWeight;

    const selectedText = selectElement.options[selectElement.selectedIndex]?.text || '';
    tempSpan.textContent = selectedText;

    document.body.appendChild(tempSpan);

    const calculatedWidth = Math.ceil(tempSpan.getBoundingClientRect().width) + 50;
    selectElement.style.width = `${calculatedWidth}px`;

    document.body.removeChild(tempSpan);
}

document.addEventListener('DOMContentLoaded', () => {
    const dynamicDropdowns = document.querySelectorAll('.auto-resize-select');
    dynamicDropdowns.forEach(select => {
        autoResizeSelect(select);
        select.addEventListener('change', (e) => autoResizeSelect(e.target));
    });
});

// Accordion Search Script
document.getElementById('stockSearch').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase().trim();
    
    document.querySelectorAll('.category-section').forEach(catSection => {
        let catHasMatch = false;
        
        catSection.querySelectorAll('.item-accordion-card').forEach(modelCard => {
            let modelHasMatch = false;
            let modelText = modelCard.dataset.searchTarget || "";

            modelCard.querySelectorAll('.btn-hardware').forEach(btn => {
                let btnText = btn.innerText.toLowerCase() + " " + (btn.dataset.serial || "").toLowerCase();
                
                if (filter === "" || btnText.includes(filter) || modelText.includes(filter)) {
                    btn.classList.remove('d-none');
                    modelHasMatch = true;
                    catHasMatch = true;
                } else {
                    btn.classList.add('d-none');
                }
            });

            const modelCollapse = modelCard.querySelector('.accordion-collapse');
            const modelBs = bootstrap.Collapse.getInstance(modelCollapse) || new bootstrap.Collapse(modelCollapse, {toggle: false});

            if (modelHasMatch) {
                modelCard.classList.remove('d-none');
                if (filter !== "") {
                    modelBs.show();
                } else {
                    modelBs.hide();
                }
            } else {
                modelCard.classList.add('d-none');
                modelBs.hide();
            }
        });

        const catCollapse = catSection.querySelector('.accordion-collapse');
        const catBs = bootstrap.Collapse.getInstance(catCollapse) || new bootstrap.Collapse(catCollapse, {toggle: false});

        if (catHasMatch) {
            catSection.classList.remove('d-none');
            if (filter !== "") {
                catBs.show();
            } else {
                catBs.hide();
            }
        } else {
            catSection.classList.add('d-none');
            catBs.hide();
        }
    });
});

const institutionSelect = document.querySelector("[name='institution_id']");
const divisionSelect    = document.querySelector("[name='division_id']");
const unitSelect        = document.querySelector("[name='unit_id']");

institutionSelect.addEventListener("change", function(){
    fetch("get_divisions_units.php?institution_id=" + this.value).then(res => res.json()).then(data => {
        divisionSelect.innerHTML = '<option value="">Select Division</option>';
        data.divisions.forEach(div => { 
            divisionSelect.innerHTML += `<option value="${div.id}" title="${div.name}">${div.name}</option>`; 
        });
        unitSelect.innerHTML = '<option value="">Select Unit</option>';
        data.units.forEach(unit => { 
            unitSelect.innerHTML += `<option value="${unit.id}" title="${unit.code}->${unit.name}">${unit.code}->${unit.name}</option>`; 
        });
        
        // Recalculate dynamic width after population
        autoResizeSelect(divisionSelect);
        autoResizeSelect(unitSelect);
    });
});

divisionSelect.addEventListener("change", function(){
    fetch("get_divisions_units.php?division_id=" + this.value).then(res => res.json()).then(data => {
        unitSelect.innerHTML = '<option value="">Select Unit</option>';
        data.units.forEach(unit => { 
            unitSelect.innerHTML += `<option value="${unit.id}" title="${unit.code}->${unit.name}">${unit.code}->${unit.name}</option>`; 
        });

        // Recalculate dynamic width after population
        autoResizeSelect(unitSelect);
    });
});

// Dynamic Dispatch Queue Logic
document.querySelectorAll('.btn-add-item').forEach(btn => {
    btn.addEventListener('click', function() {
        const d = this.dataset;
        const body = document.getElementById('dispatchBody');
        const empty = document.getElementById('emptyMsg');

        this.classList.add('item-hidden');
        if(empty) empty.remove();

        const row = document.createElement('tr');
        row.id = "queue-row-" + d.id;
        row.innerHTML = `
            <td><div class="fw-semibold text-dark" style="font-size:0.88rem;">${d.name}</div></td>
            <td><span class="badge badge-erp bg-light text-dark border font-monospace">${d.serial}</span></td>
            <td>
                ${d.type === 'serial' 
                    ? `<input type="hidden" name="stock_ids[]" value="${d.id}"> <span class="badge badge-erp bg-primary-subtle text-primary border border-primary-subtle">1 Unit</span>` 
                    : `<div class="input-group input-group-sm">
                        <input type="number" name="bulk_qty[${d.id}]" class="form-control" value="1" min="1" max="${d.max}">
                        <span class="input-group-text bg-light">/ ${d.max}</span>
                    </div>`
                }
            </td>
            <td class="text-end pe-3">
                <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1 remove-row" data-id="${d.id}" title="Remove Item">
                    <i class="bi bi-trash3 fs-6"></i>
                </button>
            </td>
        `;
        body.appendChild(row);
        updateUI();

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
        document.getElementById('dispatchBody').innerHTML = `
            <tr id="emptyMsg">
                <td colspan="4" class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-1 opacity-50"></i>
                    <span style="font-size: 0.88rem;">No items selected yet. Click hardware items from the inventory to queue them.</span>
                </td>
            </tr>`;
    }
}
</script>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>