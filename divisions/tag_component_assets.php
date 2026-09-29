<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$page_title = "Component Asset Tagging";
$page_icon  = "bi-cpu";

/* ================= CURRENT USER INFO ================= */
$role = $_SESSION['role'] ?? '';
$division_id = $_SESSION['division_id'] ?? 0;

// Security: Generate CSRF Token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ================= HANDLE TAG GENERATION ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_tags'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid security token.");
    }

    $stock_id = (int)($_POST['stock_id'] ?? 0);
    $prefix   = strtoupper(trim($_POST['prefix'] ?? ''));
    $start_no = (int)($_POST['start_no'] ?? 1);
    $transaction_started = false; // Track transaction state

    if ($stock_id > 0 && !empty($prefix)) {
        try {
            // 1. Get stock details & total quantity
            $stockQuery = $conn->prepare("SELECT total_quantity FROM component_stock WHERE id = ?");
            $stockQuery->bind_param("i", $stock_id);
            $stockQuery->execute();
            $stockRes = $stockQuery->get_result()->fetch_assoc();

            if (!$stockRes) {
                throw new Exception("Stock entry not found.");
            }

            $total_qty = (int)$stockRes['total_quantity'];

            // 2. Count already generated tags for this stock
            $countQuery = $conn->prepare("SELECT COUNT(*) AS assigned FROM component_assets WHERE stock_id = ?");
            $countQuery->bind_param("i", $stock_id);
            $countQuery->execute();
            $current_assigned = (int)$countQuery->get_result()->fetch_assoc()['assigned'];

            $remaining = $total_qty - $current_assigned;

            if ($remaining <= 0) {
                throw new Exception("All units for this stock entry have already been tagged!");
            }

            $conn->begin_transaction();
            $transaction_started = true; // Mark as started

            $tag_check = $conn->prepare("SELECT id FROM component_assets WHERE asset_tag = ?");
            $tag_insert = $conn->prepare("INSERT INTO component_assets (stock_id, asset_tag) VALUES (?, ?)");
            $duplicates = [];

            // 3. Loop through remaining quantity and insert tags
            for ($i = 0; $i < $remaining; $i++) {
                $current_no = str_pad($start_no + $i, 2, '0', STR_PAD_LEFT);
                $full_tag = $prefix . $current_no;

                // Check uniqueness globally
                $tag_check->bind_param("s", $full_tag);
                $tag_check->execute();
                if ($tag_check->get_result()->num_rows > 0) {
                    $duplicates[] = $full_tag;
                } else {
                    $tag_insert->bind_param("is", $stock_id, $full_tag);
                    $tag_insert->execute();
                }
            }

            if (!empty($duplicates)) {
                $conn->rollback();
                $transaction_started = false;
                $_SESSION['swal_type'] = "error";
                $_SESSION['swal_msg']  = "Duplicate Asset Tags found in system: " . implode(", ", $duplicates);
            } else {
                $conn->commit();
                $transaction_started = false;
                $_SESSION['swal_type'] = "success";
                $_SESSION['swal_msg']  = "Successfully generated $remaining asset tags!";
            }

        } catch (Exception $e) {
            if ($transaction_started) {
                $conn->rollback();
            }
            $_SESSION['swal_type'] = "error";
            $_SESSION['swal_msg']  = "Error: " . $e->getMessage();
        }

        header("Location: tag_component_assets.php?stock_id=$stock_id");
        exit;
    }
}

/* ================= FETCH PENDING COMPONENT STOCK ================= */
$whereClause = ($role === 'SuperAdmin') ? "" : "WHERE cs.division_id = ?";

$sql = "
    SELECT 
        cs.id,
        cs.item_name,
        cs.category,
        cs.specification,
        cs.total_quantity,
        cs.bill_no,
        v.vendor_name,
        IFNULL(ca.assigned_count, 0) AS assigned_count
    FROM component_stock cs
    LEFT JOIN vendors v ON v.id = cs.vendor_id
    LEFT JOIN (
        SELECT stock_id, COUNT(*) AS assigned_count 
        FROM component_assets 
        GROUP BY stock_id
    ) ca ON ca.stock_id = cs.id
    $whereClause
    HAVING assigned_count < total_quantity
    ORDER BY cs.id DESC
";

if ($role === 'SuperAdmin') {
    $pending_result = $conn->query($sql);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $division_id);
    $stmt->execute();
    $pending_result = $stmt->get_result();
}

$pending_list = [];
while ($row = $pending_result->fetch_assoc()) {
    $pending_list[] = $row;
}

// Determine active stock_id from URL or default to first item in queue
$stock_id = isset($_GET['stock_id']) ? (int)$_GET['stock_id'] : 0;
if ($stock_id === 0 && !empty($pending_list)) {
    $stock_id = (int)$pending_list[0]['id'];
}

// Fetch details and current generated count for the active stock item (including Unit name/code)
$active_stock = null;
$current_assets = 0;
if ($stock_id > 0) {
    $activeQuery = $conn->prepare("
        SELECT cs.*, v.vendor_name, u.unit_name, u.unit_code 
        FROM component_stock cs 
        LEFT JOIN vendors v ON v.id = cs.vendor_id 
        LEFT JOIN units u ON u.id = cs.unit_id 
        WHERE cs.id = ?
    ");
    $activeQuery->bind_param("i", $stock_id);
    $activeQuery->execute();
    $active_stock = $activeQuery->get_result()->fetch_assoc();

    $countQuery = $conn->prepare("SELECT COUNT(*) AS current_count FROM component_assets WHERE stock_id = ?");
    $countQuery->bind_param("i", $stock_id);
    $countQuery->execute();
    $current_assets = (int)$countQuery->get_result()->fetch_assoc()['current_count'];
}

ob_start();
?>

<div class="container-fluid p-0">
    <!-- Header Block -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4 bg-white p-3 rounded-3 border">
        <div>
            <h5 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                <span class="p-2 rounded-2 d-inline-flex" style="background-color: #edf3f8; color: #123b63;">
                    <i class="bi <?= $page_icon ?> fs-5"></i>
                </span>
                Component Asset Tagging Queue
            </h5>
            <p class="text-muted small m-0 mt-1">Batch generate unique internal asset identifiers for newly stocked hardware components.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: Pending Queue Sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold text-dark m-0"><i class="bi bi-layers-half me-2 text-primary"></i>Pending Stock Queue</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($pending_list)): ?>
                            <div class="p-4 text-center text-muted small">
                                <i class="bi bi-check2-all d-block fs-2 mb-2 text-success"></i>
                                All component stocks are fully tagged!
                            </div>
                        <?php else: ?>
                            <?php foreach ($pending_list as $p): 
                                $remaining_qty = $p['total_quantity'] - $p['assigned_count'];
                            ?>
                                <a href="tag_component_assets.php?stock_id=<?= $p['id'] ?>" 
                                   class="list-group-item list-group-item-action p-3 border-0 border-bottom <?= ($stock_id == $p['id']) ? 'active-queue-item' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="text-truncate me-2">
                                            <div class="fw-bold text-dark small text-uppercase"><?= htmlspecialchars($p['item_name']) ?></div>
                                            <div class="text-muted extra-small">Category: <?= htmlspecialchars($p['category'] ?? 'General') ?></div>
                                            <div class="text-muted extra-small">Bill: <?= htmlspecialchars($p['bill_no'] ?? '-') ?></div>
                                        </div>
                                        <span class="badge rounded-pill bg-light text-dark border extra-small fw-semibold">
                                            <?= $remaining_qty ?> Left
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Dynamic Generator Form -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4 p-md-5">
                    <?php if (!$active_stock || ($current_assets >= (int)$active_stock['total_quantity'])): ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <span class="bg-success bg-opacity-15 text-success p-3 rounded-circle d-inline-flex">
                                    <i class="bi bi-check-lg display-5"></i>
                                </span>
                            </div>
                            <h5 class="fw-bold text-dark">Ready for Assignment</h5>
                            <p class="text-muted small mb-4">Select another pending item from the queue or proceed to assign these tags to units.</p>
                            <a href="assign_asset.php" class="btn btn-navy px-4 rounded-3 small">Go to Assign Assets</a>
                        </div>
                    <?php else: 
                        $left_to_tag = (int)$active_stock['total_quantity'] - $current_assets;
                    ?>
                        <div class="d-flex align-items-center mb-4">
                            <i class="bi bi-tag-fill fs-4 me-2 text-primary" style="color: #123b63 !important;"></i>
                            <div>
                                <h6 class="fw-bold m-0 text-dark">Assign Asset ID</h6>
                                <p class="text-muted extra-small m-0">Create internal asset identifiers for hardware components. </p>
                            </div>
                        </div>

                        <!-- Active Stock Summary Box -->
                        <div class="card border-0 bg-light rounded-3 p-4 mb-4 shadow-none border">
                            <div class="row align-items-center">
                                <div class="col-sm-8">
                                    <div class="extra-small text-muted text-uppercase fw-bold mb-1" style="letter-spacing: 0.05em;">Active Item</div>
                                    <h5 class="fw-bold mb-2 text-primary" style="color: #123b63 !important;"><?= htmlspecialchars($active_stock['item_name']) ?></h5>
                                    
                                    <div class="small text-dark mb-1">
                                        <span class="text-muted">Specification:</span> <?= htmlspecialchars($active_stock['specification'] ?? 'N/A') ?>
                                    </div>
                                    <div class="small text-dark mb-1">
                                        <span class="text-muted">Bill Reference:</span> <span class="fw-semibold"><?= htmlspecialchars($active_stock['bill_no'] ?? '-') ?></span>
                                    </div>
                                    <div class="small text-dark mb-1">
                                        <span class="text-muted">Vendor:</span> <?= htmlspecialchars($active_stock['vendor_name'] ?? 'Direct') ?>
                                    </div>
                                    <div class="small text-dark">
                                        <span class="text-muted">Location:</span> 
                                        <span class="fw-semibold">
                                            <?= htmlspecialchars(($active_stock['unit_name'] ?? 'N/A') . (!empty($active_stock['unit_code']) ? ' (' . $active_stock['unit_code'] . ')' : '')) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-sm-4 text-sm-end mt-3 mt-sm-0 border-start-sm">
                                    <div class="fs-2 fw-bold text-dark lh-1 mb-1"><?= $left_to_tag ?></div>
                                    <div class="extra-small text-muted text-uppercase fw-bold" style="letter-spacing: 0.05em;">To Be Tagged</div>
                                </div>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="stock_id" value="<?= $active_stock['id'] ?>">

                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold text-muted text-uppercase">ID Prefix Pattern</label>
                                    <input type="text" id="prefixInput" name="prefix" 
                                           class="form-control form-control-custom w-100 text-uppercase fw-medium" 
                                           placeholder="e.g. CEC/CMP/RAM/2026-27/" required autocomplete="off">
                                    <div class="mt-2 d-flex align-items-center gap-2">
                                        <span class="text-muted extra-small">Live Preview:</span>
                                        <span id="prefixPreview" class="badge bg-light text-primary border px-2 py-1 rounded font-monospace extra-small" style="display:none;"></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Starting Number</label>
                                    <input type="number" name="start_no" class="form-control form-control-custom w-100" value="1" min="1" required>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                                <button type="submit" name="generate_tags" class="btn btn-navy px-4 py-2 small fw-semibold">
                                    <i class="bi bi-cpu-fill me-1"></i> Generate Asset Tags
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if(isset($_SESSION['swal_msg'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['swal_type'] ?>',
        title: '<?= $_SESSION['swal_type'] == "success" ? "Success" : "Notice" ?>',
        text: '<?= $_SESSION['swal_msg'] ?>',
        timer: 3500, showConfirmButton: false, toast: true, position: 'top-end'
    });
</script>
<?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); endif; ?>

<script>
// Live Tag Preview Generator
const prefixInput = document.getElementById('prefixInput');
if (prefixInput) {
    prefixInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
        const preview = document.getElementById('prefixPreview');
        if (this.value.length > 0) {
            preview.style.display = 'inline-block';
            preview.textContent = this.value + "01"; 
        } else {
            preview.style.display = 'none';
        }
    });
}
</script>

<style>
    :root {
        --primary-navy: #123b63;
        --border-color: #d9e0e7;
    }

    .form-control-custom { 
        border-radius: 6px; 
        border: 1px solid var(--border-color); 
        padding: 0.5rem 0.75rem; 
        transition: all 0.2s ease; 
        font-size: 0.85rem; 
        background: #fff; 
    }
    .form-control-custom:focus { 
        border-color: var(--primary-navy); 
        box-shadow: 0 0 0 3px rgba(18, 59, 99, 0.1); 
    }
    
    .btn-navy {
        background-color: var(--primary-navy);
        color: #ffffff;
        border: none;
        transition: background-color 0.15s ease-in-out;
    }
    .btn-navy:hover {
        background-color: #0b2942;
        color: #ffffff;
    }

    .active-queue-item {
        background-color: #edf3f8 !important;
        border-left: 4px solid var(--primary-navy) !important;
    }

    .extra-small { font-size: 0.72rem; }
</style>

<?php
$content = ob_get_clean();
include "../divisions/divisionslayout.php";
?>