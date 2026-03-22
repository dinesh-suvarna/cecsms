<?php
include "../config/db.php";
$page_title = "View Stock Details";
$page_icon  = "bi-clipboard-data";

/* ================== FETCH STOCK WITH DISPATCHED QUANTITY ================== */
$query = "
SELECT 
    sd.id,
    sd.quantity AS total_quantity,
    im.item_name,
    sd.serial_number,
    sd.bill_no,
    sd.bill_date,
    sd.po_number,
    v.vendor_name,
    sd.amount,
    sd.status,
    im.stock_type,
    /* Correlated subquery for accurate math */
    IFNULL((SELECT SUM(quantity - IFNULL(returned_quantity,0)) 
            FROM dispatch_details 
            WHERE stock_detail_id = sd.id), 0) AS dispatched_qty,

    (
        SELECT dd.dispatch_id 
        FROM dispatch_details dd
        WHERE dd.stock_detail_id = sd.id
        ORDER BY dd.dispatch_id DESC
        LIMIT 1
    ) AS last_dispatch_id

FROM stock_details sd
LEFT JOIN items_master im ON sd.stock_item_id = im.id
LEFT JOIN vendors v ON sd.vendor_id = v.id
ORDER BY im.item_name ASC, sd.id DESC
";

$result = $conn->query($query);

/* ================== GROUP STOCK BY ITEM ================== */
$grouped = [];
while($row = $result->fetch_assoc()){
    $grouped[$row['item_name']][] = $row;
}

ob_start();
?>

<style>
.table thead th {
    position: sticky;
    top: 0;
    background: #ffffff;
    z-index: 2;
}
.highlight-match {
    background-color: #ffe69c;
    padding: 2px 4px;
    border-radius: 4px;
    font-weight: 600;
}

.card {transition: 0.2s ease-in-out;}
.card:hover {box-shadow: 0 0.5rem 1rem rgba(0,0,0,.08);}
.dispatched-row {background-color: #fdf2f2; border-left: 3px solid #dc3545;}
.group-header {cursor: pointer; background: #ffffff;}
.group-header:hover {background: #f8f9fa;}
.search-box {max-width: 250px;}
.table td, .table th {white-space: nowrap; font-size: 13px;}
.table td.serial-col {max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;}
.table td.date-col {font-size: 12px; color: #6c757d;}
.badge {font-size: 11px; padding: 5px 8px;}
</style>

<div class="container-fluid mt-4">
<div class="card border-0 shadow-sm rounded-4">
<div class="card-body">

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="fw-semibold mb-0">Stock Details</h5>

    <div class="d-flex gap-2">
        <select id="statusFilter" class="form-select form-select-sm shadow-sm">
            <option value="all">All</option>
            <option value="available">Available</option>
            <option value="partial">Partially Dispatched</option>
            <option value="dispatched">Dispatched</option>
        </select>

        <input type="text" id="searchInput" 
               class="form-control form-control-sm search-box shadow-sm"
               placeholder="Search item...">
    </div>
</div>

<div class="table-responsive">
<table class="table table-sm table-hover align-middle mb-0">
<thead class="border-bottom">
<tr>
    <th>Sl.No</th>
    <th>Item</th>
    <th>Serial No / Remaining</th>
    <th>Quantity</th>
    <th>Bill No</th>
    <th>Bill Date</th>
    <th>PO</th>
    <th>Vendor</th>
    <th>Amount</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>
<tbody>

<?php

$sl = 1;

foreach($grouped as $item_name => $stocks){
    $sl = 1;
    $group_id = "group_" . md5($item_name);

    $totalQty = 0;
    $totalRemaining = 0;

    foreach($stocks as $s){
        if($s['stock_type'] === 'serial'){
            $totalQty += 1;
            // Serial items rely on the status column
            if($s['status'] !== 'dispatched'){
                $totalRemaining += 1;
            }
        } else {
            // Bulk items rely on the quantity math
            $t = (int)$s['total_quantity'];
            $totalQty += $t;
            
            $rem = $t - (int)$s['dispatched_qty'];
            $totalRemaining += max(0, $rem); // Prevent negative numbers
        }
    }
    
    echo "
    <tr class='group-header border-top' data-group='$group_id'>
        <td colspan='11' class='py-2'>
            <div class='d-flex justify-content-between align-items-center'>
                <div>
                    <i class='bi bi-chevron-right me-2 toggle-icon text-muted'></i>
                    <strong>".htmlspecialchars($item_name)."</strong>
                </div>
                <span class='badge bg-light text-dark border'>
                    $totalQty Units ($totalRemaining Remaining)
                </span>
            </div>
        </td>
    </tr>
    ";
    // Sort $stocks: remaining first, dispatched last
    usort($stocks, function($a, $b) {
        $remainingA = ((int)$a['total_quantity'] - (int)$a['dispatched_qty']);
        $remainingB = ((int)$b['total_quantity'] - (int)$b['dispatched_qty']);

        // Serial items: if dispatched, consider remaining=0
        if($a['stock_type'] === 'serial' && $a['status'] === 'dispatched') $remainingA = 0;
        if($b['stock_type'] === 'serial' && $b['status'] === 'dispatched') $remainingB = 0;

        // Sort descending: remaining first
        return $remainingB <=> $remainingA;
    });

    foreach($stocks as $row){

    $row_class = ($row['stock_type'] === 'serial' && $row['status'] === 'dispatched') ? "dispatched-row" : "";

    
    // 1. Calculate the raw difference
$calcRemaining = (int)$row['total_quantity'] - (int)$row['dispatched_qty'];

// 2. Apply rules: Serial items are binary (1 or 0), Bulk items use the math but floor at 0
if($row['stock_type'] === 'serial') {
    $remainingQty = ($row['status'] === 'dispatched') ? 0 : 1;
} else {
    $remainingQty = max(0, $calcRemaining); 
}

    /* ===== DYNAMIC STATUS FOR FILTER ===== */
    if($row['stock_type'] === 'non_serial'){
        $dispatchedQty = (int)$row['dispatched_qty'];

        if($remainingQty == 0){
            $dynamicStatus = "dispatched";
        } elseif($dispatchedQty > 0){
            $dynamicStatus = "partial";
        } else {
            $dynamicStatus = "available";
        }
    } else {
        $dynamicStatus = $row['status'];
    }
    echo "<tr id='row-".(int)$row['id']."' class='stock-row $row_class group-row $group_id' 
      data-status='$dynamicStatus' 
      style='display:none;'>";

    // Empty first column (for group alignment)
    echo "<td class='text-muted small'>$sl</td>";
    $sl++;
    echo"<td></td>";

   
/* ================= SERIAL / NON-SERIAL DISPLAY ================= */

$displayCell = "";
$statusBadge  = "";
$stockId      = (int)$row['id'];

if($row['stock_type'] === 'serial'){

    // SERIAL ITEM → show serial number
    $serial = htmlspecialchars($row['serial_number']);
    $displayCell = "<span class='fw-semibold text-dark'>$serial</span>";

    // Status for serial
    if($row['status'] === 'dispatched'){
        $statusBadge = "<a href='dispatch_report.php?stock_id=$stockId&dispatch_id={$row['last_dispatch_id']}' 
                            class='badge bg-danger text-decoration-none'>
                            <i class='bi bi-truck me-1'></i> Dispatched
                        </a>";
    } else {
        $statusBadge = "<span class='badge bg-success'>
                            <i class='bi bi-check-circle me-1'></i> Available
                        </span>";
    }

} else {

    
    // NON-SERIAL ITEM → use the safe remainingQty calculated at the start of the loop
$dispatchedQty = (int)$row['dispatched_qty'];

$displayCell = "<span class='fw-semibold text-primary'>
                    $remainingQty Remaining
                </span>";

    $displayCell = "<span class='fw-semibold text-primary'>
                        $remainingQty Remaining
                    </span>";

    // Status for bulk
    if($dispatchedQty > 0 && $remainingQty > 0){
        $statusBadge = "<a href='dispatch_report.php?stock_id=$stockId' 
                            class='badge bg-warning text-dark text-decoration-none'>
                            Partially Dispatched ($dispatchedQty)
                        </a>";
    } elseif($remainingQty == 0){
        $statusBadge = "<a href='dispatch_report.php?stock_id=$stockId' 
                            class='badge bg-danger text-decoration-none'>
                            Fully Dispatched ($dispatchedQty)
                        </a>";
    } else {
        $statusBadge = "<span class='badge bg-success'>
                            <i class='bi bi-check-circle me-1'></i> Available
                        </span>";
    }
}


echo "<td class='serial-col'>$displayCell</td>";
    /* ================= OTHER COLUMNS ================= */
    echo "<td>".htmlspecialchars($row['total_quantity'])."</td>";
    echo "<td>".htmlspecialchars($row['bill_no'])."</td>";
    echo "<td class='date-col'>".htmlspecialchars($row['bill_date'])."</td>";
    echo "<td>".htmlspecialchars($row['po_number'])."</td>";
    echo "<td>".htmlspecialchars($row['vendor_name'])."</td>";
    echo "<td>₹ ".number_format((float)$row['amount'],2)."</td>";

    echo "<td>$statusBadge</td>";

    echo "<td>";

/* EDIT BUTTON */
echo "<a href='edit_stock.php?id=".htmlspecialchars($row['id'])."' 
        class='btn btn-sm btn-outline-primary me-1'
        title='Edit'>
        <i class='bi bi-pencil-square'></i>
      </a>";

/* E-WASTE BUTTON (Condition Based) */
if($dynamicStatus === 'dispatched'){
    echo "<a href='#'
            class='btn btn-sm btn-outline-success'
            title='Move to E-Waste'>
            <i class='bi bi-recycle'></i>
          </a>";
}

echo "</td>";

    
    echo "</tr>";
    }
}
?>

</tbody>
</table>
</div>

</div>
</div>
</div>

<script>
// AUTO-EXPAND GROUP ON PAGE LOAD (If ID is passed in URL)
// 1. HELPER: Function to expand a specific group and force visibility
function expandGroup(groupId) {
    const header = document.querySelector(`.group-header[data-group="${groupId}"]`);
    const rows = document.querySelectorAll('.' + groupId);
    
    if (header) {
        header.style.display = "table-row";
        const icon = header.querySelector('.toggle-icon');
        if (icon) icon.classList.replace('bi-chevron-right', 'bi-chevron-down');
    }

    rows.forEach(row => {
        // Use setProperty with 'important' to override the default 'display:none'
        row.style.setProperty("display", "table-row", "important");
    });
}

// 2. MAIN LOAD LOGIC
window.addEventListener('load', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('highlight_id');

    if (highlightId) {
        // Wait 300ms to ensure the table is fully rendered
        setTimeout(() => {
            const targetRow = document.getElementById('row-' + highlightId);
            
            if (targetRow) {
                // Find the group class (e.g., group_eb8...)
                const groupClass = Array.from(targetRow.classList).find(c => 
                    c.startsWith('group_') && c !== 'group-row'
                );
                
                if (groupClass) {
                    // Force the accordion/group open
                    expandGroup(groupClass);

                    // Scroll the specific row into view
                    targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Visual Highlight
                    targetRow.style.setProperty("background-color", "#fff3cd", "important");
                    targetRow.style.outline = "2px solid #ffc107";
                    
                    // Fade out highlight after 3 seconds
                    setTimeout(() => {
                        targetRow.style.transition = 'all 2s ease';
                        targetRow.style.backgroundColor = '';
                        targetRow.style.outline = "none";
                    }, 3000);
                }
            }
        }, 300); 
    }
});
// FILTER
document.getElementById('statusFilter').addEventListener('change', function() {
    let value = this.value;
    document.querySelectorAll('.stock-row').forEach(row => {
        row.style.display = (value === 'all' || row.dataset.status === value) ? "table-row" : "none";
    });
    document.querySelectorAll('.group-header').forEach(header => {
        header.querySelector('.toggle-icon').classList.replace('bi-chevron-right','bi-chevron-down');
    });
});


// SEARCH (Search inside rows like serial, vendor, bill, etc.)
document.getElementById('searchInput').addEventListener('keyup', function(){
    let filter = this.value.toLowerCase().trim();

    // Remove old highlights
    document.querySelectorAll('.highlight-match').forEach(el => {
        el.outerHTML = el.innerHTML;
    });

    let firstMatch = null;

    // If search box is empty → collapse everything
    if(filter === ""){
        document.querySelectorAll('.stock-row').forEach(row => {
            row.style.display = "none";
        });

        document.querySelectorAll('.group-header').forEach(header => {
            header.style.display = "table-row";
            header.querySelector('.toggle-icon')
                  .classList.remove('bi-chevron-down');
            header.querySelector('.toggle-icon')
                  .classList.add('bi-chevron-right');
        });

        return;
    }

    document.querySelectorAll('.group-header').forEach(header => {

        let group = header.getAttribute('data-group');
        let rows = document.querySelectorAll('.' + group);
        let matchFound = false;

        rows.forEach(row => {
            let rowText = row.innerText.toLowerCase();

            if(rowText.includes(filter)){
                row.style.display = "table-row";
                matchFound = true;

                highlightText(row, filter);

                if(!firstMatch){
                    firstMatch = row;
                }

            } else {
                row.style.display = "none";
            }
        });

        if(matchFound){
            header.style.display = "table-row";
            header.querySelector('.toggle-icon')
                  .classList.remove('bi-chevron-right');
            header.querySelector('.toggle-icon')
                  .classList.add('bi-chevron-down');
        } else {
            header.style.display = "none";
        }
    });

    // Auto scroll to first match
    if(firstMatch){
        firstMatch.scrollIntoView({
            behavior: "smooth",
            block: "center"
        });
    }
});

/* ================= HIGHLIGHT FUNCTION ================= */
function highlightText(element, text) {
    let regex = new RegExp(text, "gi");

    element.querySelectorAll("td").forEach(td => {

        td.childNodes.forEach(node => {

            if (node.nodeType === 3) { // TEXT NODE ONLY

                let content = node.nodeValue;
                if (regex.test(content)) {

                    let span = document.createElement("span");
                    span.innerHTML = content.replace(regex,
                        match => `<span class="highlight-match">${match}</span>`
                    );

                    td.replaceChild(span, node);
                }
            }

        });

    });
}



// GROUP EXPAND / COLLAPSE
document.querySelectorAll('.group-header').forEach(header => {
    header.addEventListener('click', function(){
        let group = this.getAttribute('data-group');
        let rows = document.querySelectorAll('.' + group);
        let icon = this.querySelector('.toggle-icon');

        rows.forEach(row => {
            if(row.style.display === "none"){
                row.style.display = "table-row";
                icon.classList.remove('bi-chevron-right');
                icon.classList.add('bi-chevron-down');
            } else {
                row.style.display = "none";
                icon.classList.remove('bi-chevron-down');
                icon.classList.add('bi-chevron-right');
            }
        });
    });
});


</script>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>