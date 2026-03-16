<?php
include "../config/db.php";
ob_start();

/* ---------------- UPDATE MODEL ---------------- */
if(isset($_POST['update_model'])){

    $model_id = (int)$_POST['model_id'];
    $model_name = $conn->real_escape_string($_POST['model_name']);
    $processor = $conn->real_escape_string($_POST['processor']);
    $ram = $conn->real_escape_string($_POST['ram']);
    $storage_size = $conn->real_escape_string($_POST['storage_size']);
    $storage_type = $conn->real_escape_string($_POST['storage_type']);

    $conn->query("
        UPDATE item_models 
        SET model_name='$model_name',
            processor='$processor',
            ram='$ram',
            storage_size='$storage_size',
            storage_type='$storage_type'
        WHERE id=$model_id
    ");

    echo "<script>
        alert('Model updated successfully');
        window.location='view_stock_specifications.php';
    </script>";
}

/* ---------------- DELETE MODEL ---------------- */
if(isset($_GET['delete_model'])){

    $model_id = (int)$_GET['delete_model'];

    // Check if model has stock
    $check = $conn->query("
        SELECT COUNT(*) as total 
        FROM stock_details 
        WHERE model_id = $model_id
    ");

    $rowCheck = $check->fetch_assoc();

    if($rowCheck['total'] > 0){

        echo "<div class='alert alert-danger'>
                Cannot delete model. PCs exist under this model.
              </div>";

    } else {

        $conn->query("DELETE FROM item_models WHERE id = $model_id");

        echo "<script>
        alert('Model deleted successfully');
        window.location='view_stock_specifications.php';
        </script>";

    }
}


/* ---------------- FETCH DATA ---------------- */

$result = $conn->query("
SELECT 
    i.item_name,
    m.id AS model_id,
    m.model_name,
    m.processor,
    m.ram,
    m.storage_type,
    m.storage_size,
    (
        SELECT COUNT(*) 
        FROM stock_details sd 
        WHERE sd.model_id = m.id
    ) AS pc_count
FROM item_models m
JOIN items_master i ON i.id = m.item_id
WHERE m.status='Active'
ORDER BY i.item_name, m.model_name
");

if(!$result){
    die("Query Error: " . $conn->error);
}

?>

<div class="container-fluid mt-4">

<h4 class="mb-3">
<i class="bi bi-pc-display"></i> View PC Models
</h4>

<!-- SEARCH -->
<input type="text" 
id="searchInput"
class="form-control mb-3"
placeholder="Search serial number, model, processor...">

<div class="accordion" id="itemAccordion">

<?php
$currentItem = "";
$currentModel = "";
$itemIndex = 0;
$modelIndex = 0;

$serialCounter = 1;
while($row = $result->fetch_assoc()){

/* -------- NEW ITEM -------- */
if($currentItem != $row['item_name']){

    if($currentItem != ""){
        echo "</tbody></table></div></div></div>";
        echo "</div></div></div>";
    }

    $itemIndex++;
    $currentItem = $row['item_name'];
    $currentModel = "";
?>

<div class="accordion-item">

<h2 class="accordion-header">
<button class="accordion-button collapsed fw-bold"
data-bs-toggle="collapse"
data-bs-target="#item<?= $itemIndex ?>">
<?= $currentItem ?>
</button>
</h2>

<div id="item<?= $itemIndex ?>"
class="accordion-collapse collapse"
data-bs-parent="#itemAccordion">

<div class="accordion-body">

<?php }


/* -------- NEW MODEL -------- */
if($currentModel != $row['model_name']){

    if($currentModel != ""){
        echo "</tbody></table></div></div></div>";
    }
    $serialCounter = 1;

    $modelIndex++;
    $currentModel = $row['model_name'];
    $storage = trim($row['storage_size'].' '.$row['storage_type']);

    // COUNT PCs FOR THIS MODEL
    $pcCount = $row['pc_count'];

    
?>




<div class="card mb-3">

<div class="card-header p-0">

<div class="d-flex justify-content-between align-items-center px-3 py-2">


<!-- COLLAPSIBLE BUTTON -->
<button class="accordion-button collapsed shadow-none flex-grow-1 me-2"
type="button"
data-bs-toggle="collapse"
data-bs-target="#model<?= $itemIndex ?>_<?= $modelIndex ?>">

<?= $currentModel ?>

<span class="text-muted ms-2 small">
(<?= $row['processor'] ?: '-' ?> |
<?= $row['ram'] ?: '-' ?> |
<?= $storage ?: '-' ?>)
</span>

<span class="badge bg-primary ms-3">
<?= $pcCount ?> PCs
</span>

</button>

<!-- ACTION BUTTONS -->
<div class="d-flex gap-2">

<button 
class="btn btn-sm btn-warning editBtn"
data-id="<?= $row['model_id'] ?>"
data-model="<?= htmlspecialchars($row['model_name']) ?>"
data-processor="<?= htmlspecialchars($row['processor']) ?>"
data-ram="<?= htmlspecialchars($row['ram']) ?>"
data-storage_size="<?= htmlspecialchars($row['storage_size']) ?>"
data-storage_type="<?= htmlspecialchars($row['storage_type']) ?>"
title="Edit Model"
data-bs-toggle="modal"
data-bs-target="#editModelModal">

<i class="bi bi-pencil-square"></i>
</button>


<?php if($pcCount == 0) { ?>

<a href="?delete_model=<?= $row['model_id'] ?>"
class="btn btn-sm btn-danger"
title="Delete Model"
onclick="return confirm('Delete this model?')">
<i class="bi bi-trash"></i>
</a>

<?php } else { ?>

<span 
class="d-inline-block" 
tabindex="0" 
data-bs-toggle="tooltip" 
data-bs-placement="top" 
title="Cannot delete this model because PCs exist under it">

<button class="btn btn-sm btn-outline-secondary" disabled>
<i class="bi bi-trash"></i>
</button>

</span>


<?php } ?>

</div>



</div>
</div>

<div id="model<?= $itemIndex ?>_<?= $modelIndex ?>"
class="collapse">

<div class="card-body p-2">

<table class="table table-sm table-bordered modelTable">
<thead class="table-light">
<tr>
<th>Sl.No</th>
<th>Processor</th>
<th>RAM</th>
<th>Storage</th>
</tr>
</thead>
<tbody>

<?php } ?>

<tr>
<td><?= $serialCounter++ ?></td>
<td><?= $row['processor'] ?: '-' ?></td>
<td><?= $row['ram'] ?: '-' ?></td>
<td><?= trim($row['storage_size'].' '.$row['storage_type']) ?: '-' ?></td>

</tr>


<?php } ?>

</tbody>
</table>
</div>
</div>
</div>

</div>
</div>
</div>

</div>
</div>

<!-- SEARCH + TOOLTIP + EDIT MODAL SCRIPT -->
<script>
document.addEventListener("DOMContentLoaded", function () {

    /* ---------- SEARCH ---------- */
    document.getElementById("searchInput").addEventListener("keyup", function(){

        let value = this.value.toLowerCase();
        let rows = document.querySelectorAll(".modelTable tbody tr");

        rows.forEach(row => {

            let match = row.innerText.toLowerCase().includes(value);
            row.style.display = match ? "" : "none";

            if(match){

                let collapseDiv = row.closest(".collapse");
                if(collapseDiv){
                    new bootstrap.Collapse(collapseDiv, { show: true });
                }

                let parentItem = row.closest(".accordion-collapse");
                if(parentItem){
                    new bootstrap.Collapse(parentItem, { show: true });
                }
            }

        });

    });

    /* ---------- TOOLTIP ---------- */
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    /* ---------- EDIT MODAL ---------- */
    document.querySelectorAll(".editBtn").forEach(button => {

        button.addEventListener("click", function(){

            document.getElementById("edit_model_id").value = this.dataset.id;
            document.getElementById("edit_model_name").value = this.dataset.model;
            document.getElementById("edit_processor").value = this.dataset.processor;
            document.getElementById("edit_ram").value = this.dataset.ram;
            document.getElementById("edit_storage_size").value = this.dataset.storage_size;
            document.getElementById("edit_storage_type").value = this.dataset.storage_type;

        });

    });

});
</script>



<!-- EDIT MODEL MODAL -->
<div class="modal fade" id="editModelModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">

<form method="POST">

<div class="modal-header bg-warning">
<h5 class="modal-title">Edit Model</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

<input type="hidden" name="model_id" id="edit_model_id">

<div class="mb-2">
<label>Model Name</label>
<input type="text" name="model_name" id="edit_model_name" class="form-control" required>
</div>

<div class="mb-2">
<label>Processor</label>
<input type="text" name="processor" id="edit_processor" class="form-control">
</div>

<div class="mb-2">
<label>RAM</label>
<input type="text" name="ram" id="edit_ram" class="form-control">
</div>

<div class="row">
<div class="col">
<label>Storage Size</label>
<input type="text" name="storage_size" id="edit_storage_size" class="form-control">
</div>
<div class="col">
<label>Storage Type</label>
<input type="text" name="storage_type" id="edit_storage_type" class="form-control">
</div>
</div>

</div>

<div class="modal-footer">
<button type="submit" name="update_model" class="btn btn-success">
Update Model
</button>
</div>

</form>

</div>
</div>
</div>



<?php
$content = ob_get_clean();
include "masterlayout.php";
?>
