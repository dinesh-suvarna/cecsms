<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/db.php";

$page_title = "Categorized Vendor Directory";

function getCategoryData($conn, $category) {
    $v_stmt = $conn->prepare("SELECT * FROM vendors WHERE category = ? ORDER BY vendor_name ASC");
    $v_stmt->bind_param("s", $category);
    $v_stmt->execute();
    $vendors = $v_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $results = [];

    foreach ($vendors as $vendor) {
        $vendor_id = $vendor['id'];
        
        if ($category === 'Computer') {
            $query = "SELECT 
                        MAX(sd.bill_date) as bill_date, 
                        sd.bill_no, 
                        im.item_name, 
                        'Computer' as cat, 
                        SUM(sd.quantity) as qty, 
                        AVG(sd.amount) as price 
                      FROM stock_details sd 
                      JOIN items_master im ON sd.stock_item_id = im.id 
                      WHERE sd.vendor_id = ? 
                      GROUP BY sd.bill_no, im.id
                      ORDER BY MAX(sd.bill_date) DESC";
        } elseif ($category === 'Furniture') {
            $query = "SELECT 
                        MAX(fs.bill_date) as bill_date, 
                        fs.bill_no, 
                        fi.item_name, 
                        'Furniture' as cat, 
                        SUM(fs.total_qty) as qty, 
                        AVG(fs.unit_price) as price 
                      FROM furniture_stock fs 
                      JOIN furniture_items fi ON fs.furniture_item_id = fi.id 
                      WHERE fs.vendor_id = ? 
                      GROUP BY fs.bill_no, fi.id
                      ORDER BY MAX(fs.bill_date) DESC";
        } else { // Electrical
            $query = "SELECT 
                        MAX(es.bill_date) as bill_date, 
                        es.bill_no, 
                        ei.item_name, 
                        'Electrical' as cat, 
                        SUM(es.total_qty) as qty, 
                        AVG(es.unit_price) as price 
                      FROM electrical_stock es 
                      JOIN electrical_items ei ON es.electrical_item_id = ei.id 
                      WHERE es.vendor_id = ? 
                      GROUP BY es.bill_no, ei.id
                      ORDER BY MAX(es.bill_date) DESC";
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $total_spend = 0;
        foreach ($history as $item) {
            $total_spend += ($item['qty'] * $item['price']);
        }

        $results[] = [
            'info' => $vendor,
            'history' => $history,
            'stats' => [
                'count' => count($history),
                'spend' => $total_spend
            ]
        ];
    }
    return $results;
}

$categories = [
    'Computer' => getCategoryData($conn, 'Computer'),
    'Furniture' => getCategoryData($conn, 'Furniture'),
    'Electrical' => getCategoryData($conn, 'Electricals')
];

ob_start();
?>

<style>
    .fw-800 { font-weight: 800 !important; letter-spacing: -0.5px; }
    .fw-600 { font-weight: 600 !important; }
    .text-xxs { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06rem; }
    
    /* Tabs Styling */
    .nav-pills .nav-link { color: #64748b; font-weight: 600; border-radius: 12px; padding: 12px 24px; border: 1px solid transparent; transition: all 0.2s; }
    .nav-pills .nav-link.active { background: #0d6efd; color: white; box-shadow: 0 4px 12px rgba(7, 17, 110, 0.2); }
    .nav-pills .nav-link:hover:not(.active) { background: #f1f5f9; border-color: #e2e8f0; }

    /* Accordion Styling */
    .accordion-item { border: 1px solid #e2e8f0 !important; border-radius: 16px !important; margin-bottom: 1rem; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
    .accordion-button { background: white !important; padding: 1.25rem 1.5rem; }
    .accordion-button:not(.collapsed) { border-bottom: 1px solid #f1f5f9; box-shadow: none; background: #fafafa !important; }
    .accordion-button::after { background-size: 0.9rem; }
    
    /* Sticky Contact Bar */
    .vendor-info-bar {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    /* Scrollable Container */
    .table-scroll-container {
        max-height: 480px;
        overflow-y: auto;
        scroll-snap-type: y proximity;
        scroll-padding-top: 45px;
    }

    /* Professional Table Styling */
    .custom-vendor-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .custom-vendor-table thead th { 
        position: sticky;
        top: 0;
        z-index: 5;
        background-color: #f1f5f9; 
        color: #475569; 
        font-size: 0.7rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        letter-spacing: 0.05em; 
        padding: 0.85rem 1rem; 
        border-bottom: 2px solid #cbd5e1; 
    }

    .custom-vendor-table tbody tr { 
        scroll-snap-align: start; 
        scroll-snap-stop: normal;
        transition: background-color 0.15s ease-in-out; 
    }
    .custom-vendor-table tbody tr:hover { background-color: #f1f5f9; }
    .custom-vendor-table tbody td { 
        padding: 0.9rem 1rem; 
        border-bottom: 1px solid #f1f5f9; 
        font-size: 0.875rem; 
    }
    .custom-vendor-table tfoot td {
        background-color: #f8fafc;
        border-top: 2px solid #cbd5e1;
        padding: 0.9rem 1rem;
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-800 text-dark mb-0">Vendor Directory</h4>
            <p class="text-muted small mb-0">Comprehensive vendor data and service history organized for quick access.</p>
        </div>
        <a href="view_vendors.php" class="btn btn-light rounded-pill px-4 fw-bold border shadow-sm">
            <i class="bi bi-arrow-left me-2"></i>Back to Registry
        </a>
    </div>

    <!-- Category Tabs -->
    <ul class="nav nav-pills mb-4 gap-2" id="sectorTabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-computer">Computer</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-furniture">Furniture</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-electrical">Electrical</button>
        </li>
    </ul>

    <div class="tab-content">
        <?php $firstTab = true; foreach ($categories as $catName => $vendorList): ?>
            <div class="tab-pane fade <?= $firstTab ? 'show active' : '' ?>" id="tab-<?= strtolower($catName) ?>">
                
                <div class="accordion" id="acc-<?= strtolower($catName) ?>">
                    <?php if (empty($vendorList)): ?>
                        <div class="text-center py-5 bg-white rounded-4 border">
                            <i class="bi bi-inbox text-muted fs-1 opacity-50"></i>
                            <p class="text-muted mb-0 mt-2">No vendors found in this category.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($vendorList as $index => $data): 
                            $vendor = $data['info'];
                            $history = $data['history'];
                            $stats = $data['stats'];
                            $accId = "collapse-" . strtolower($catName) . "-" . $vendor['id'];
                            $targetTableId = "table-" . strtolower($catName) . "-" . $vendor['id'];
                        ?>
                            <div class="accordion-item shadow-sm">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accId ?>">
                                        <div class="row align-items-center w-100 me-3">
                                            <div class="col">
                                                <div class="fw-800 text-dark fs-6"><?= htmlspecialchars($vendor['vendor_name']) ?></div>
                                                <div class="text-muted text-xxs text-uppercase mt-1">
                                                    <i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($vendor['phone_number'] ?: 'No Phone') ?>
                                                </div>
                                            </div>
                                            <div class="col-auto text-end">
                                                <span class="d-block text-xxs text-muted text-uppercase">Total Spend</span>
                                                <span class="fw-800 text-primary fs-6">₹<?= number_format($stats['spend'], 2) ?></span>
                                            </div>
                                            <div class="col-auto text-end border-start ps-3 ms-3">
                                                <span class="d-block text-xxs text-muted text-uppercase">Supplies</span>
                                                <span class="fw-800 text-dark fs-6"><?= $stats['count'] ?></span>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="<?= $accId ?>" class="accordion-collapse collapse" data-bs-parent="#acc-<?= strtolower($catName) ?>">
                                    <div class="accordion-body p-0">
                                        <!-- Sticky Email, Address & Action Toolbar -->
                                        <div class="p-3 vendor-info-bar d-flex flex-wrap justify-content-between align-items-center gap-2">
                                            <div class="row small text-muted g-2 flex-grow-1">
                                                <div class="col-md-4">
                                                    <i class="bi bi-envelope me-2 text-secondary"></i><?= htmlspecialchars($vendor['email'] ?: 'No Email Specified') ?>
                                                </div>
                                                <div class="col-md-8">
                                                    <i class="bi bi-geo-alt me-2 text-secondary"></i><?= htmlspecialchars($vendor['address'] ?: 'No Address Specified') ?>
                                                </div>
                                            </div>
                                            <!-- Export & Print Actions -->
                                            <div class="d-flex gap-2">
                                                <button 
                                                    type="button" 
                                                    class="btn btn-sm btn-outline-secondary rounded-pill px-3 btn-print-vendor"
                                                    data-container-id="<?= $accId ?>"
                                                    data-vendor-name="<?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES) ?>"
                                                    data-phone="<?= htmlspecialchars($vendor['phone_number'] ?? 'N/A', ENT_QUOTES) ?>"
                                                    data-email="<?= htmlspecialchars($vendor['email'] ?? 'N/A', ENT_QUOTES) ?>"
                                                    data-address="<?= htmlspecialchars(preg_replace('/\s+/', ' ', $vendor['address'] ?? 'N/A'), ENT_QUOTES) ?>">
                                                    <i class="bi bi-printer me-1"></i> Print / PDF
                                                </button>
                                                <button onclick="exportTableToCSV('<?= $targetTableId ?>', '<?= preg_replace('/[^a-zA-Z0-9]/', '_', $vendor['vendor_name']) ?>_history.csv')" class="btn btn-sm btn-outline-success rounded-pill px-3">
                                                    <i class="bi bi-file-earmark-excel me-1"></i> Excel
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Scrollable Table Container -->
                                        <div class="table-scroll-container">
                                            <table id="<?= $targetTableId ?>" class="custom-vendor-table align-middle">
                                                <thead>
                                                    <tr>
                                                        <th class="ps-4">Transaction Date</th>
                                                        <th>Bill Reference</th>
                                                        <th>Item Supplied</th>
                                                        <th class="text-center">Quantity</th>
                                                        <th class="text-end">Unit Price</th>
                                                        <th class="text-end pe-4">Total Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($history)): ?>
                                                        <tr>
                                                            <td colspan="6" class="text-center py-4 text-muted">
                                                                <i class="bi bi-folder-x me-2"></i>No historical transaction records.
                                                            </td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php foreach ($history as $row): 
                                                            $line_total = $row['qty'] * $row['price'];
                                                            $date_formatted = date('M d, Y', strtotime($row['bill_date']));
                                                        ?>
                                                            <tr>
                                                                <td class="ps-4">
                                                                    <span class="fw-semibold text-dark"><?= $date_formatted ?></span>
                                                                </td>
                                                                <td>
                                                                    <span class="fw-bold text-dark">#<?= htmlspecialchars($row['bill_no']) ?></span>
                                                                </td>
                                                                <td>
                                                                    <span class="fw-600 text-dark"><?= htmlspecialchars($row['item_name']) ?></span>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="fw-bold text-dark"><?= $row['qty'] ?></span>
                                                                </td>
                                                                <td class="text-end fw-bold text-dark">
                                                                    ₹<?= number_format($row['price'], 2) ?>
                                                                </td>
                                                                <td class="text-end pe-4 fw-800 text-dark">
                                                                    ₹<?= number_format($line_total, 2) ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                                <?php if (!empty($history)): ?>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="5" class="ps-4 fw-bold text-dark">Grand Total:</td>
                                                        <td class="text-end pe-4 fw-800 text-primary fs-6">₹<?= number_format($stats['spend'], 2) ?></td>
                                                    </tr>
                                                </tfoot>
                                                <?php endif; ?>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        <?php $firstTab = false; endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Event delegation for print buttons
    document.querySelectorAll('.btn-print-vendor').forEach(button => {
        button.addEventListener('click', function (e) {
            e.stopPropagation();
            const containerId = this.getAttribute('data-container-id');
            const vendorName = this.getAttribute('data-vendor-name');
            const phone = this.getAttribute('data-phone');
            const email = this.getAttribute('data-email');
            const address = this.getAttribute('data-address');

            const container = document.getElementById(containerId);
            const tableHTML = container.querySelector('table').outerHTML;

            const printWindow = window.open('', '', 'height=700,width=900');
            printWindow.document.write('<html><head><title>Vendor Statement - ' + vendorName + '</title>');
            printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; color: #333; }');
            printWindow.document.write('.header-box { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 15px; }');
            printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px 12px; font-size: 13px; }');
            printWindow.document.write('th { background-color: #f8f9fa; text-transform: uppercase; font-size: 11px; }');
            printWindow.document.write('.text-end { text-align: right; }');
            printWindow.document.write('.text-center { text-align: center; }');
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            
            // Header Info
            printWindow.document.write('<div class="header-box">');
            printWindow.document.write('<h2>' + vendorName + '</h2>');
            printWindow.document.write('<p class="mb-1"><strong>Phone:</strong> ' + phone + ' | <strong>Email:</strong> ' + email + '</p>');
            printWindow.document.write('<p class="mb-0"><strong>Address:</strong> ' + address + '</p>');
            printWindow.document.write('</div>');
            
            printWindow.document.write('<h5>Supply & Transaction Details Statement</h5>');
            printWindow.document.write(tableHTML);
            
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 500);
        });
    });
});

// CSV/Excel Export Function fixed for column alignment with colspan
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    let csv = [];
    const rows = table.querySelectorAll("tr");

    for (let i = 0; i < rows.length; i++) {
        let row = [];
        const cols = rows[i].querySelectorAll("td, th");
        
        for (let j = 0; j < cols.length; j++) {
            let col = cols[j];
            let text = col.innerText.replace(/₹/g, '').replace(/,/g, '').trim();
            let colspan = col.colSpan || 1;
            
            row.push('"' + text + '"');
            
            // Fill empty CSV cells for spanned columns to keep total aligned under Total Amount
            for (let k = 1; k < colspan; k++) {
                row.push('""');
            }
        }
        csv.push(row.join(","));
    }

    const csvFile = new Blob([csv.join("\n")], { type: "text/csv" });
    const downloadLink = document.createElement("a");
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>

<?php 
$content = ob_get_clean();
include "../vendors/vendorlayout.php"; 
?>