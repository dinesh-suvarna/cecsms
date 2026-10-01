<?php 
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";
session_start();

$page_title = "Component Asset Registry";
$page_icon  = "bi-tags";

$user_division_id = $_SESSION['division_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'Division'; 

/* ================= HANDLE INDIVIDUAL ASSET TAG UPDATE ================= */
if (isset($_POST['update_asset_tag'])) {
    $db_id = (int)$_POST['db_id'];
    $new_asset_tag = trim($_POST['new_asset_tag']);

    if (!empty($new_asset_tag)) {
        if ($user_role === 'SuperAdmin') {
            $stmt = $conn->prepare("UPDATE component_assets SET asset_tag = ? WHERE id = ?");
            $stmt->bind_param("si", $new_asset_tag, $db_id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("UPDATE component_assets ca JOIN component_stock cs ON ca.stock_id = cs.id SET ca.asset_tag = ? WHERE ca.id = ? AND cs.division_id = ?");
            $stmt->bind_param("sii", $new_asset_tag, $db_id, $user_division_id);
            $stmt->execute();
        }
        $_SESSION['success'] = "Asset tag updated successfully.";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* ================= HANDLE DELETE ASSET ================= */
if (isset($_POST['action']) && $_POST['action'] === 'delete_asset') {
    $response = ['success' => false];
    $asset_id = (int)$_POST['asset_id'];

    if ($user_role === 'SuperAdmin') {
        $del_query = "DELETE FROM component_assets WHERE id = $asset_id";
    } else {
        $del_query = "DELETE ca FROM component_assets ca 
                      JOIN component_stock cs ON ca.stock_id = cs.id 
                      WHERE ca.id = $asset_id AND cs.division_id = $user_division_id";
    }

    if ($conn->query($del_query)) {
        $_SESSION['success'] = "Asset tag successfully removed.";
        $response['success'] = true;
    }
    echo json_encode($response);
    exit();
}

/* ================= HELPERS ================= */
function getAssetIcon(string $itemName, $category = '') {
    $name = strtolower($itemName);
    $cat  = strtolower($category);
    
    switch (true) {
        // Oscilloscopes & Power Scopes
        case (strpos($name, 'oscilloscope') !== false || strpos($name, 'power scope') !== false):
            return 'bi-activity';
        // Function Generators
        case (strpos($name, 'function generator') !== false):
            return 'bi-soundwave';
        // Regulated Power Supply
        case (strpos($name, 'power supply') !== false):
            return 'bi-plug-fill';
        // Trainer Kits
        case (strpos($name, 'trainer kit') !== false):
            return 'bi-laptop';
        // IC Testers
        case (strpos($name, 'ic tester') !== false):
            return 'bi-cpu-fill';
        // Standard Components
        case (strpos($name, 'ram') !== false || $cat === 'ram'):
            return 'bi-memory';
        case (strpos($name, 'ssd') !== false || strpos($name, 'hdd') !== false || strpos($name, 'storage') !== false):
            return 'bi-hdd';
        case (strpos($name, 'processor') !== false || strpos($name, 'cpu') !== false):
            return 'bi-cpu';
        case (strpos($name, 'motherboard') !== false):
            return 'bi-motherboard';
        case (strpos($name, 'smps') !== false || strpos($name, 'power') !== false):
            return 'bi-lightning-charge';
        case (strpos($name, 'fan') !== false || strpos($name, 'cooler') !== false):
            return 'bi-fan';
        default:
            return 'bi-box-seam';
    }
}

ob_start();
?>

<style>
:root {
    --erp-navy: #123b63;
    --erp-navy-dark: #0b2942;
    --erp-blue: #2b628f;
    --erp-panel: #ffffff;
    --erp-panel-soft: #f7f9fb;
    --erp-border: #d9e0e7;
    --erp-text: #20384d;
    --erp-text-soft: #526679;
    --erp-muted: #718191;
    --erp-shadow: 0 1px 3px rgba(20,45,70,.05);
}

.container-fluid { max-width: 1440px; padding: 24px 28px 36px; }
.extra-small { font-size: .78rem; }

.form-control-erp, .form-select-erp {
    font-size: 0.88rem;
    border-radius: 5px;
    border: 1px solid var(--erp-border);
    padding: 0.55rem 0.75rem;
    color: var(--erp-text);
    background-color: #ffffff;
}
.form-control-erp:focus, .form-select-erp:focus {
    border-color: var(--erp-blue) !important;
    box-shadow: 0 0 0 3px rgba(43, 98, 143, 0.15) !important;
}

/* Accordion Styling matching comp.JPG */
.unit-accordion .accordion-item {
    border: 1px solid var(--erp-border) !important;
    margin-bottom: 0.75rem;
    border-radius: 6px !important;
    background: #ffffff;
    overflow: hidden;
}

.unit-accordion .accordion-button {
    background-color: #f8fafc;
    color: var(--erp-navy-dark);
    font-weight: 700;
    font-size: 0.95rem;
    padding: 0.85rem 1.25rem;
}

.unit-accordion .accordion-button:not(.collapsed) {
    background-color: #edf3f8;
    color: var(--erp-navy);
    border-left: 4px solid var(--erp-navy);
    box-shadow: none;
}

.unit-accordion .accordion-button::after {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23123b63'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
}

/* Table layout matching comp in.JPG */
.table-erp-minimal th {
    background-color: var(--erp-panel-soft) !important;
    color: var(--erp-text-soft);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 12px 16px;
    border-bottom: 1px solid var(--erp-border);
}

.table-erp-minimal td {
    padding: 12px 16px;
    font-size: 0.88rem;
    color: var(--erp-text);
    border-bottom: 1px solid #edf0f3;
}

.table-erp-minimal tbody tr:last-child td { border-bottom: none; }
.hover-row:hover td { background-color: #f8fafc !important; }

.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 4px;
    border: 1px solid var(--erp-border);
    background: #ffffff;
    color: var(--erp-text-soft);
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.88rem;
}
.btn-icon:hover { background: var(--erp-panel-soft); color: var(--erp-navy); }

.icon-box {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    background: #edf3f8;
    color: var(--erp-blue);
    border: 1px solid rgba(18,59,99,.08);
}

.btn-erp-primary {
    background-color: var(--erp-navy);
    color: #ffffff;
    border: none;
    font-weight: 600;
    font-size: 0.85rem;
    padding: 0.55rem 1.25rem;
    border-radius: 4px;
    transition: background-color 0.15s ease;
}
.btn-erp-primary:hover { background-color: var(--erp-navy-dark); color: #ffffff; }
</style>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1060;">
    <div id="liveToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true" style="background: var(--erp-navy-dark);">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center extra-small">
                <i class="bi bi-check-circle-fill text-success me-2"></i>
                <span id="toastMsg"></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<div class="container-fluid py-0">
    <!-- Header Section -->
    <div class="d-flex flex-wrap align-items-center justify-content-between pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="icon-box">
                <i class="bi bi-tags fs-5"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1" style="color: var(--erp-navy-dark); font-size: 1.25rem;">
                    Component Asset Registry
                </h4>
                <p class="text-muted small mb-0">Track all uniquely tagged individual hardware components and microcontrollers.</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="input-group bg-white rounded border overflow-hidden" style="width: 300px;">
                <span class="input-group-text bg-transparent border-0 pe-1"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="assetSearch" class="form-control border-0 extra-small" placeholder="Search by asset tag, name, spec...">
            </div>
            <a href="tag_component_assets.php" class="btn btn-erp-primary">
                <i class="bi bi-plus-circle me-1"></i> Tag New Assets
            </a>
        </div>
    </div>

    <!-- Accordion Section -->
    <?php
    $where_sql = ($user_role === 'SuperAdmin') ? "1=1" : "cs.division_id = $user_division_id";
    
    $query = "
        SELECT 
            ca.id AS asset_id,
            ca.asset_tag,
            cs.item_name,
            cs.category,
            cs.specification
        FROM component_assets ca
        JOIN component_stock cs ON ca.stock_id = cs.id
        WHERE $where_sql
        ORDER BY cs.item_name ASC, ca.asset_tag ASC
    ";
    
    $result = $conn->query($query);
    $component_groups = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $component_groups[$row['item_name']][] = $row;
        }
    }
    ?>

    <?php if (empty($component_groups)): ?>
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body text-center py-5 text-muted small">
                <i class="bi bi-folder2-open display-6 d-block mb-2 opacity-50"></i>
                No component asset tags found.
            </div>
        </div>
    <?php else: ?>
        <div class="accordion unit-accordion" id="componentAccordion">
            <?php $i = 0; foreach ($component_groups as $item_name => $grouped_items): $i++; 
                $collapseId = "componentCollapse_" . $i;
                $first_item = $grouped_items[0];
                $category = $first_item['category'] ?? 'General';
                $sl_no = 1;
            ?>
            <div class="accordion-item shadow-sm asset-group-card">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed d-flex align-items-center justify-content-between flex-wrap gap-2" 
                            type="button" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#<?= $collapseId ?>">
                        <div class="d-flex align-items-center flex-wrap gap-2 me-3">
                            <i class="bi <?= getAssetIcon($item_name, $category) ?> fs-5 me-1" style="color: var(--erp-navy);"></i>
                            <span class="fw-bold text-dark me-2"><?= htmlspecialchars($item_name) ?></span>
                        
                        </div>
                        <div class="me-3">
                            <span class="badge bg-light text-secondary border fw-bold px-2 py-1" style="font-size: 0.75rem;">
                                Total Items: <?= count($grouped_items) ?>
                            </span>
                        </div>
                    </button>
                </h2>
                
                <div id="<?= $collapseId ?>" class="accordion-collapse collapse" data-bs-parent="#componentAccordion">
                    <div class="accordion-body p-0 bg-white">
                        <div class="table-responsive">
                            <table class="table align-middle table-erp-minimal mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;" class="ps-3">Sl.No</th>
                                        <th>Component Item</th>
                                        <th>Specifications & Category</th>
                                        <th>Asset Tag ID</th>
                                        <th class="pe-3 text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($grouped_items as $asset): ?>
                                    <tr class="hover-row asset-row-item">
                                        <td class="ps-3 text-muted fw-semibold"><?= $sl_no++ ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($item_name) ?></div>
                                        </td>
                                        <td>
                                            <div class="text-secondary fw-semibold extra-small"><?= htmlspecialchars($asset['specification'] ?? 'No specifications added') ?></div>
                                            <div class="text-muted extra-small"><?= htmlspecialchars($category) ?></div>
                                        </td>
                                        <td>
                                            <div class="d-inline-flex align-items-center gap-2">
                                                <span class="fw-bold text-primary" style="font-size: 0.88rem;">
                                                    <?= htmlspecialchars($asset['asset_tag']) ?>
                                                </span>
                                                <button class="btn btn-icon btn-sm py-0 px-1 border-0 bg-transparent text-secondary" onclick="openEditTagModal(<?= $asset['asset_id'] ?>, '<?= htmlspecialchars($asset['asset_tag'], ENT_QUOTES) ?>')" title="Edit Tag">
                                                    <i class="bi bi-pencil-square text-primary"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="pe-3 text-end">
                                            <button class="btn btn-icon delete-asset-btn text-danger" data-id="<?= $asset['asset_id'] ?>" title="Delete Tag">
                                                <i class="bi bi-trash"></i>
                                            </button>
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
    <?php endif; ?>
</div>

<!-- Edit Asset Tag Modal -->
<div class="modal fade" id="editTagModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom p-3">
                <h6 class="fw-bold mb-0">Update Component Asset Tag</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="db_id" id="edit_db_id">
                    <label class="form-label small fw-semibold text-secondary">Asset Tag ID</label>
                    <input type="text" name="new_asset_tag" id="edit_asset_tag" class="form-control fw-bold form-control-lg fs-6 text-uppercase" required autocomplete="off">
                </div>
                <div class="modal-footer border-0 p-3 pt-0">
                    <button type="submit" name="update_asset_tag" class="btn btn-erp-primary w-100 fw-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function(){
    // Toast notification support if set
    <?php if(isset($_SESSION['success'])): ?>
        const toast = new bootstrap.Toast(document.getElementById('liveToast'));
        $('#toastMsg').text("<?= $_SESSION['success'] ?>");
        toast.show();
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    // Live search filter across the accordion cards and rows
    $("#assetSearch").on("keyup", function() {
        let value = $(this).val().toLowerCase();
        $(".asset-group-card").each(function() {
            let cardText = $(this).text().toLowerCase();
            if (cardText.indexOf(value) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
});

function openEditTagModal(id, tag) {
    document.getElementById('edit_db_id').value = id;
    document.getElementById('edit_asset_tag').value = tag;
    new bootstrap.Modal(document.getElementById('editTagModal')).show();
}

// Delete Individual Asset Tag action with SweetAlert2
$(document).on('click', '.delete-asset-btn', function() {
    const assetId = $(this).data('id');
    
    Swal.fire({
        title: 'Delete Asset Tag?',
        text: "This will permanently delete this individual tag from the registry.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#123b63',
        cancelButtonColor: '#718191',
        confirmButtonText: '<i class="bi bi-trash me-1"></i> Yes, delete it',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        customClass: {
            popup: 'rounded-3 shadow-lg border-0',
            title: 'fw-bold text-dark fs-5',
            htmlContainer: 'extra-small text-muted',
            confirmButton: 'btn btn-erp-primary border-0 px-3 py-2 ms-2',
            cancelButton: 'btn btn-erp-secondary border-0 px-3 py-2'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('<?= $_SERVER['PHP_SELF'] ?>', { action: 'delete_asset', asset_id: assetId }, function(res) {
                if (res.success) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: 'Asset tag has been removed.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                        customClass: { popup: 'rounded-3 shadow-lg border-0' }
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', 'Could not delete asset tag.', 'error');
                }
            }, 'json');
        }
    });
});
</script>

<?php
$content = ob_get_clean();
if ($user_role === 'SuperAdmin') { 
    include "../stock/stocklayout.php"; 
} else { 
    include "../divisions/divisionslayout.php"; 
}
?>