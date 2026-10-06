<?php
require_once __DIR__ . "/../config/db.php";
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$selected_category = $_GET['category'] ?? 'computer';
$selected_year = $_GET['year'] ?? '';


switch ($selected_category) {
    case 'electrical':
        $table = "electrical_assets";
        $id_column = "asset_tag";
        $join_clause = "JOIN electrical_stock ON electrical_assets.stock_id = electrical_stock.id 
                        JOIN electrical_items ON electrical_stock.electrical_item_id = electrical_items.id
                        LEFT JOIN units ON electrical_stock.unit_id = units.id
                        LEFT JOIN divisions ON units.division_id = divisions.id";
        $name_column = "electrical_items.item_name";
        break;
    case 'computer':
        $table = "division_assets";
        $id_column = "division_asset_id";
        $join_clause = "JOIN stock_details ON division_assets.stock_detail_id = stock_details.id 
                        JOIN items_master ON stock_details.stock_item_id = items_master.id
                        JOIN dispatch_details ON division_assets.dispatch_detail_id = dispatch_details.id
                        JOIN dispatch_master ON dispatch_details.dispatch_id = dispatch_master.id
                        LEFT JOIN units ON dispatch_master.unit_id = units.id
                        LEFT JOIN divisions ON dispatch_master.division_id = divisions.id";
        $name_column = "items_master.item_name";
        break;
    case 'furniture':
    default:
        $table = "furniture_assets";
        $id_column = "asset_tag";
        $join_clause = "JOIN furniture_stock ON furniture_assets.stock_id = furniture_stock.id 
                        JOIN furniture_items ON furniture_stock.furniture_item_id = furniture_items.id
                        LEFT JOIN units ON furniture_stock.unit_id = units.id
                        LEFT JOIN divisions ON units.division_id = divisions.id";
        $name_column = "furniture_items.item_name";
        break;
}

// Fetch distinct years dynamically using regex
$years_query = $conn->query("
    SELECT DISTINCT 
        REGEXP_SUBSTR($id_column, '[0-9]{4}-[0-9]{2}') as extracted_year 
    FROM $table 
    WHERE $id_column REGEXP '[0-9]{4}-[0-9]{2}'
    HAVING extracted_year IS NOT NULL
    ORDER BY extracted_year DESC
");

// Build filter conditions
$where_clauses = [];
if (!empty($selected_year)) {
    $safe_year = $conn->real_escape_string($selected_year);
    $where_clauses[] = "$table.$id_column LIKE '%$safe_year%'";
}
$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch assets along with Division and Unit info
$assets_query = $conn->query("
    SELECT 
        $table.*, 
        $name_column as item_name,
        COALESCE(divisions.division_name, 'Central / Unassigned') as division_name,
        COALESCE(units.unit_name, 'General Stock') as unit_name,
        COALESCE(units.unit_code, '') as unit_code
    FROM $table 
    $join_clause 
    $where_sql 
    ORDER BY division_name ASC, unit_name ASC, $table.id DESC
");

// Group results programmatically by Division -> Unit
// Group results programmatically by Division -> Unit
$grouped_data = [];
if ($assets_query) {
    while ($row = $assets_query->fetch_assoc()) {
        $div = $row['division_name'];
        
        // Combine unit_code and unit_name if unit_code exists
        $raw_unit_name = $row['unit_name'];
        $raw_unit_code = trim($row['unit_code']);
        
        if (!empty($raw_unit_code) && $raw_unit_name !== 'General Stock') {
            $unit = strtolower($raw_unit_code) . ' - ' . $raw_unit_name;
        } else {
            $unit = $raw_unit_name;
        }
        
        $grouped_data[$div][$unit][] = $row;
    }
}

$page_title = "Asset Year Filter";
ob_start();
?>

<div class="erp-page-container">
    <!-- PAGE HEADER -->
    <div class="inst-header mb-4">
        <div class="inst-header-left">
            <div class="inst-header-icon"></div>
            <div>
                <h3 class="mb-0">Procurement Year Asset Filter</h3>
                <p class="text-muted">Filter furniture, electrical, and computer assets using the year embedded in their Asset IDs.</p>
            </div>
        </div>
    </div>

    <!-- FILTER FORM PANEL -->
    <div class="inst-panel p-3 mb-4">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-secondary">Asset Category</label>
                <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="computer" <?= $selected_category == 'computer' ? 'selected' : '' ?>>Computer Assets</option>
                    <option value="furniture" <?= $selected_category == 'furniture' ? 'selected' : '' ?>>Furniture Assets</option>
                    <option value="electrical" <?= $selected_category == 'electrical' ? 'selected' : '' ?>>Electrical Assets</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label small fw-semibold text-secondary">Procurement Year (From Asset ID)</label>
                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Years --</option>
                    <?php while($y = $years_query->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($y['extracted_year']) ?>" <?= $selected_year == $y['extracted_year'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($y['extracted_year']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <?php if(!empty($selected_year) || $selected_category != 'furniture'): ?>
                <div class="col-md-auto">
                    <a href="?" class="btn btn-erp-cancel btn-sm">Reset Filters</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- ACCORDION VIEW CONTAINER -->
    <div class="inst-panel p-3">
        <?php if(!empty($grouped_data)): ?>
            <div class="accordion" id="assetAccordion">
                <?php $div_index = 0; foreach($grouped_data as $division_name => $units): $div_index++; ?>
                    <div class="card mb-3 border">
                        <div class="card-header bg-light py-2" id="headingDiv<?= $div_index ?>">
                            <h2 class="mb-0">
                                <button class="btn btn-link text-dark text-decoration-none fw-bold w-100 text-start d-flex justify-content-between align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDiv<?= $div_index ?>" aria-expanded="true" aria-controls="collapseDiv<?= $div_index ?>">
                                    <span><i class="bi bi-building me-2"></i><?= htmlspecialchars($division_name) ?></span>
        
                                </button>
                            </h2>
                        </div>

                        <div id="collapseDiv<?= $div_index ?>" class="collapse show" aria-labelledby="headingDiv<?= $div_index ?>" data-bs-parent="#assetAccordion">
                            <div class="card-body p-2">
                                <div class="accordion" id="unitAccordion<?= $div_index ?>">
                                    <?php $unit_index = 0; foreach($units as $unit_name => $assets): $unit_index++; ?>
                                        <div class="card mb-2 border-0 shadow-sm">
                                            <div class="card-header bg-white py-2 border-bottom" id="headingUnit<?= $div_index ?>_<?= $unit_index ?>">
                                                <button class="btn btn-link btn-sm text-secondary text-decoration-none fw-semibold w-100 text-start d-flex justify-content-between align-items-center collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUnit<?= $div_index ?>_<?= $unit_index ?>" aria-expanded="false" aria-controls="collapseUnit<?= $div_index ?>_<?= $unit_index ?>">
                                                    <span><i class="bi bi-door-open me-2"></i><?= htmlspecialchars($unit_name) ?></span>
                                                    <span class="badge bg-light text-dark border"><?= count($assets) ?> Items</span>
                                                </button>
                                            </div>

                                            <div id="collapseUnit<?= $div_index ?>_<?= $unit_index ?>" class="collapse" aria-labelledby="headingUnit<?= $div_index ?>_<?= $unit_index ?>" data-bs-parent="#unitAccordion<?= $div_index ?>">
                                                <div class="card-body p-0">
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-striped align-middle mb-0">
                                                            <thead>
                                                                <tr class="small text-muted bg-light">
                                                                    <th class="ps-3">#</th>
                                                                    <th>ASSET ID / TAG</th>
                                                                    <th>ITEM NAME</th>
                                                                    <th>STATUS</th>
                                                                    <th>DATE</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php $sl = 1; foreach($assets as $asset): ?>
                                                                    <tr>
                                                                        <td class="ps-3 text-muted small"><?= $sl++ ?></td>
                                                                        <td><span class="font-monospace fw-semibold text-dark"><?= htmlspecialchars($asset[$id_column]) ?></span></td>
                                                                        <td><?= htmlspecialchars($asset['item_name']) ?></td>
                                                                        <td>
                                                                            <span class="badge bg-<?= in_array(strtolower($asset['status']), ['available', 'active']) ? 'success' : 'secondary' ?>">
                                                                                <?= htmlspecialchars($asset['status']) ?>
                                                                            </span>
                                                                        </td>
                                                                        <td class="text-muted small">
                                                                            <?= htmlspecialchars(($asset['updated_at'] ?? null) ?: ($asset['created_at'] ?? '')) ?>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-folder2-open display-4 d-block mb-2"></i>
                No asset records found matching the selected filters.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
$content = ob_get_clean();
include "masterlayout.php"; 
?>