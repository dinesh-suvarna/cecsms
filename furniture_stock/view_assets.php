<?php
include "../config/db.php";
session_start();

// Check if user is logged in
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

// Fetch all generated assets with their stock and item details
$assets_query = "
    SELECT 
        fa.id as asset_db_id, 
        fa.asset_tag, 
        fa.status,
        fa.last_verified_date,
        fa.created_at,
        s.bill_no, 
        s.bill_date,
        i.item_name,
        v.vendor_name,
        u.unit_name
    FROM furniture_assets fa
    JOIN furniture_stock s ON fa.stock_id = s.id
    JOIN furniture_items i ON s.furniture_item_id = i.id
    JOIN vendors v ON s.vendor_id = v.id
    JOIN units u ON s.unit_id = u.id
    ORDER BY fa.id DESC
";

$assets = $conn->query($assets_query);

$page_title = "Asset ID Registry";
ob_start();
?>

<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Asset ID Registry</h4>
            <p class="text-muted small mb-0">Complete list of all unique hardware identifiers</p>
        </div>
        <div class="d-flex gap-2">
            <a href="tag_assets.php" class="btn btn-outline-primary rounded-pill px-4">
                <i class="bi bi-tag me-2"></i>Queue
            </a>
            <a href="add_furniture.php" class="btn btn-primary rounded-pill px-4">
                <i class="bi bi-plus-lg me-2"></i>New Stock
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <div class="input-group">
                <span class="input-group-text bg-transparent border-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="assetSearch" class="form-control border-0 shadow-none" placeholder="Search by Tag, Item Name, Bill Number or Unit...">
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="assetsTable">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-uppercase small fw-bold text-muted">Asset Tag ID</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted">Item & Vendor</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted">Status</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted">Bill / Invoice</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted">Location/Unit</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted">Verification</th>
                            <th class="pe-4 py-3 text-end text-uppercase small fw-bold text-muted">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($assets && $assets->num_rows > 0): ?>
                            <?php while($row = $assets->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-2 fw-bold">
                                            <?= htmlspecialchars($row['asset_tag']) ?>
                                        </span>
                                        <div class="extra-small text-muted mt-1">Generated: <?= date('d/m/y', strtotime($row['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['item_name']) ?></div>
                                        <div class="extra-small text-muted"><?= htmlspecialchars($row['vendor_name']) ?></div>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_map = [
                                            'Available' => 'bg-success',
                                            'Issued'    => 'bg-info',
                                            'Damaged'   => 'bg-warning text-dark',
                                            'Disposed'  => 'bg-danger'
                                        ];
                                        $badge_class = $status_map[$row['status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $badge_class ?> rounded-pill" style="font-size: 0.7rem;">
                                            <?= $row['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-dark">#<?= htmlspecialchars($row['bill_no']) ?></div>
                                        <div class="extra-small text-muted"><?= date('d M Y', strtotime($row['bill_date'])) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace">
                                            <?= htmlspecialchars($row['unit_name']) ?>
                                        </span>
                                    </td>
                                    <td class="small">
                                        <?php if ($row['last_verified_date']): ?>
                                            <span class="text-dark"><i class="bi bi-patch-check-fill text-success me-1"></i><?= date('d/m/Y', strtotime($row['last_verified_date'])) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Not Verified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <div class="btn-group">
                                            <button class="btn btn-light btn-sm rounded-circle shadow-sm me-1" title="Print Tag">
                                                <i class="bi bi-printer text-primary"></i>
                                            </button>
                                            <button class="btn btn-light btn-sm rounded-circle shadow-sm" title="Verify Item">
                                                <i class="bi bi-shield-check text-success"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block mb-3 opacity-50"></i>
                                    No assets found in the registry.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .extra-small { font-size: 0.72rem; }
    .bg-primary-subtle { background-color: #eef2ff !important; }
    .table thead th { letter-spacing: 0.5px; border-bottom: 1px solid #f0f0f0; }
    .font-monospace { font-family: 'Courier New', Courier, monospace; }
    #assetSearch:focus { border: none; box-shadow: none; }
    .table-hover tbody tr:hover { background-color: #fcfcfd; }
</style>

<script>
document.getElementById('assetSearch').addEventListener('keyup', function() {
    let filter = this.value.toUpperCase();
    let rows = document.querySelector("#assetsTable tbody").rows;
    
    for (let i = 0; i < rows.length; i++) {
        // Only skip the "No assets found" row if it exists
        if (rows[i].cells.length < 2) continue;

        let text = rows[i].textContent.toUpperCase();
        rows[i].style.display = text.includes(filter) ? "" : "none";
    }
});
</script>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>