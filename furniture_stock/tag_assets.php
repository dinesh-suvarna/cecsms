<?php
include "../config/db.php";
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

// Security: Generate CSRF Token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;

// 1. Fetch pending stock entries - Prepared Statement for Division Filter
$query_str = "
    SELECT s.id, s.total_qty, s.bill_no, i.item_name, u.division_id, u.unit_code,
    (SELECT COUNT(fa.id) FROM furniture_assets fa WHERE fa.stock_id = s.id) as assigned_count
    FROM furniture_stock s
    JOIN furniture_items i ON s.furniture_item_id = i.id
    JOIN units u ON s.unit_id = u.id
    WHERE 1=1";

if ($user_role !== 'SuperAdmin') {
    $query_str .= " AND u.division_id = ?";
}
$query_str .= " GROUP BY s.id HAVING assigned_count < s.total_qty ORDER BY s.id ASC";

$stmt = $conn->prepare($query_str);
if ($user_role !== 'SuperAdmin') {
    $stmt->bind_param("i", $user_division);
}
$stmt->execute();
$all_pending_query = $stmt->get_result();

$pending_list = [];
while($row = $all_pending_query->fetch_assoc()) {
    $pending_list[] = $row;
}

// 2. Determine which stock is currently being edited
$stock_id = isset($_GET['stock_id']) ? (int)$_GET['stock_id'] : 0;
if ($stock_id === 0 && !empty($pending_list)) {
    $stock_id = (int)$pending_list[0]['id'];
}

// 3. Fetch details for the active stock_id - Secure Prepared Statement
$stock = null;
$current_assets = 0;
if ($stock_id > 0) {
    $stock_stmt = $conn->prepare("SELECT 
        s.*, i.item_name, i.item_code, u.unit_code, u.unit_name 
        FROM furniture_stock s 
        JOIN furniture_items i ON s.furniture_item_id = i.id 
        JOIN units u ON s.unit_id = u.id 
        WHERE s.id = ?");
    $stock_stmt->bind_param("i", $stock_id);
    $stock_stmt->execute();
    $stock = $stock_stmt->get_result()->fetch_assoc();

    $check_stmt = $conn->prepare("SELECT COUNT(id) as current_count FROM furniture_assets WHERE stock_id = ?");
    $check_stmt->bind_param("i", $stock_id);
    $check_stmt->execute();
    $current_assets = $check_stmt->get_result()->fetch_assoc()['current_count'];
}

// 4. Handle Generation
$error_message = ""; 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_tags']) && $stock) {
    // Security: CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid security token.");
    }

    $qty = (int)$stock['total_qty'];
    if ($current_assets < $qty) {
        $prefix = strtoupper(trim($_POST['prefix']));
        $start_no = (int)$_POST['start_no'];
        $remaining = $qty - $current_assets;
        $duplicates = [];

        // Prepare statements for the loop
        $tag_check = $conn->prepare("SELECT id FROM furniture_assets WHERE asset_tag = ?");
        $tag_insert = $conn->prepare("INSERT INTO furniture_assets (stock_id, asset_tag) VALUES (?, ?)");

        for ($i = 0; $i < $remaining; $i++) {
            $current_no = str_pad($start_no + $i, 2, '0', STR_PAD_LEFT);
            $full_tag = $prefix . $current_no;

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
            $error_message = "The following tags are already in use: " . implode(", ", $duplicates);
        } else {
            header("Location: tag_assets.php?msg=success");
            exit();
        }
    }
}

$page_title = "Asset Tagging Queue";
ob_start();
?>

<div class="container-fluid py-4 px-4">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold m-0"><i class="bi bi-layers-half me-2 text-primary"></i>Pending Queue</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($pending_list)): ?>
                            <div class="p-4 text-center text-muted small">
                                <i class="bi bi-check2-all d-block fs-2 mb-2"></i>
                                No pending items to tag.
                            </div>
                        <?php else: ?>
                            <?php foreach ($pending_list as $p): ?>
                                <a href="tag_assets.php?stock_id=<?= $p['id'] ?>" 
                                   class="list-group-item list-group-item-action p-3 border-0 <?= ($stock_id == $p['id']) ? 'bg-primary-subtle border-start border-primary border-4' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="text-truncate">
                                            <div class="fw-bold text-dark small text-uppercase"><?= htmlspecialchars($p['item_name']) ?></div>
                                            <div class="text-muted extra-small">Bill: #<?= htmlspecialchars($p['bill_no']) ?> </div>
                                            <div class="text-muted extra-small">Location: <?= htmlspecialchars($p['unit_code']) ?> </div>
                                        </div>
                                        <span class="badge rounded-pill bg-white text-primary border border-primary-subtle">
                                            <?= $p['total_qty'] - $p['assigned_count'] ?> Left
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 min-vh-50">
                <div class="card-body p-5">
                    <?php if (!$stock || ($current_assets >= (int)$stock['total_qty'])): ?>
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <div class="bg-success bg-opacity-10 d-inline-flex p-4 rounded-circle">
                                    <i class="bi bi-check-lg text-success display-4"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold text-dark">Queue Cleared</h4>
                            <p class="text-muted mb-4">All furniture stocks have been assigned unique Asset IDs.</p>
                            <a href="view_assets.php" class="btn btn-outline-dark rounded-pill px-4">Go to Registry</a>
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-center mb-4">
                            <i class="bi bi-tag-fill text-primary fs-3 me-3"></i>
                            <div>
                                <h5 class="fw-bold m-0">Assign Asset ID</h5>
                                <p class="text-muted small m-0">Finalizing stock entry for auditing</p>
                            </div>
                        </div>

                        <div class="alert bg-light border-0 rounded-4 p-4 mb-4">
                            <div class="row align-items-center">
                                <div class="col-sm-8">
                                    <div class="small text-muted text-uppercase fw-bold mb-1">Active Item</div>
                                    <h5 class="fw-bold mb-0 text-primary"><?= htmlspecialchars($stock['item_name']) ?> (<?= htmlspecialchars($stock['item_code']) ?>)</h5>
                                    <div class="small mt-1">Bill Reference: <strong><?= htmlspecialchars($stock['bill_no']) ?></strong></div>
                                    <div class="small mt-1">Location: <strong> <?= htmlspecialchars($stock['unit_code']) ?> </strong></div>
                                </div>
                                <div class="col-sm-4 text-sm-end mt-3 mt-sm-0">
                                    <div class="display-6 fw-bold text-dark"><?= (int)$stock['total_qty'] - $current_assets ?></div>
                                    <div class="small text-muted text-uppercase">To Be Tagged</div>
                                </div>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="row g-4">
                               <div class="col-md-8">
                                    <label class="form-label small fw-bold text-muted text-uppercase">ID Prefix Pattern</label>
                                    <input type="text" id="prefixInput" name="prefix" 
                                           class="form-control form-control-lg rounded-3 border-light-subtle bg-light fw-bold" 
                                           placeholder="e.g. CEC/CSE/2021-22/CT6-1S/" required>
                                    <div class="mt-2 d-flex align-items-center gap-2">
                                        <span class="text-muted small">Live Preview:</span>
                                        <span id="prefixPreview" class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-2 font-monospace" style="display:none;"></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Starting No.</label>
                                    <input type="number" name="start_no" class="form-control form-control-lg rounded-3 border-light-subtle bg-light" value="1" min="1" required>
                                </div>
                            </div>

                            <button type="submit" name="generate_tags" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm mt-5">
                                <i class="bi bi-cpu-fill me-2"></i> Generate & Finalize
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .extra-small { font-size: 0.75rem; }
    .min-vh-50 { min-height: 60vh; }
    .list-group-item { transition: all 0.2s; border-bottom: 1px solid #f8f9fa !important; }
    .list-group-item:hover { background-color: #f8f9fa; }
    .bg-primary-subtle { background-color: #eef2ff !important; }
    .font-monospace { font-family: 'Monaco', 'Consolas', monospace; letter-spacing: 1px; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
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

const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('msg') === 'success') {
    Swal.fire({ icon: 'success', title: 'Success', text: 'Asset IDs generated successfully!', timer: 2000, showConfirmButton: false });
}

<?php if (!empty($error_message)): ?>
    Swal.fire({ icon: 'error', title: 'Duplicate Detected', text: '<?= addslashes($error_message) ?>', confirmButtonColor: '#3085d6' });
<?php endif; ?>
</script>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>