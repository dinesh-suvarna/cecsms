<?php
include "../config/db.php";

ob_start(); 

/* ---------------- FETCH CATEGORY ---------------- */

$categories = $conn->query("
    SELECT DISTINCT category 
    FROM items_master 
    WHERE status='Active'
");


/* ---------------- ADD MODEL ---------------- */
if(isset($_POST['add_model'])){

    $item_id      = $_POST['item_id'];
    $model_name = trim(strtoupper($_POST['model_name']));
    $processor    = $_POST['processor'];
    $ram          = $_POST['ram'];
    $storage_type = $_POST['storage_type'];
    $storage_size = $_POST['storage_size'];

    /* CHECK IF MODEL ALREADY EXISTS */
    $check = $conn->prepare("
        SELECT id 
        FROM item_models
        WHERE item_id=? AND model_name=?
    ");

    $check->bind_param("is", $item_id, $model_name);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){

        echo "<div class='alert alert-warning'>
        Model already exists for this item.
        </div>";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO item_models
            (item_id, model_name, processor, ram, storage_type, storage_size)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("isssss",
            $item_id,
            $model_name,
            $processor,
            $ram,
            $storage_type,
            $storage_size
        );

        $stmt->execute();

        echo "<div class='alert alert-success'>
        Model Added Successfully
        </div>";
    }
}

/* FETCH ITEMS */
$items = $conn->query("
    SELECT id, item_name, category
    FROM items_master 
    WHERE status='Active' 
    AND category='Computer'
");





/* FETCH MODELS */
$models = $conn->query("
    SELECT m.id, m.item_id, m.model_name, 
           m.processor, m.ram, m.storage_type, m.storage_size,
           i.item_name
    FROM item_models m
    JOIN items_master i ON i.id = m.item_id
    ORDER BY m.model_name
");


/* FETCH STOCK */
$stock = $conn->query("
    SELECT
    sd.id,
    im.item_name,
    m.model_name,
    COALESCE(sd.serial_number,'-') AS serial_number,
    COALESCE(m.processor,'-') AS processor,
    COALESCE(m.ram,'-') AS ram,
    CONCAT(
        COALESCE(m.storage_size,''),
        ' ',
        COALESCE(m.storage_type,'')
    ) AS storage
    FROM stock_details sd
    JOIN items_master im ON im.id = sd.stock_item_id
    LEFT JOIN item_models m ON m.id = sd.model_id
    ORDER BY sd.id DESC
");
?>

<div class="container-fluid mt-4">

<div class="row g-4">

<!-- ADD MODEL -->
<div class="col-lg-4">
<div class="card p-3">

<h5><i class="bi bi-cpu"></i> Add Model</h5>

<form method="POST">

<select name="item_id" class="form-select mb-2" required>
<option value="">Select Item</option>
<?php
$items->data_seek(0);
while($row=$items->fetch_assoc()){
 echo "<option value='{$row['id']}'>{$row['item_name']}</option>";
}
?>
</select>

<input type="text" name="model_name" class="form-control mb-2" placeholder="Model Name" required>
<input type="text" name="processor" class="form-control mb-2" placeholder="Processor">
<input type="text" name="ram" class="form-control mb-2" placeholder="RAM">

<select name="storage_type" class="form-select mb-2">
<option value="">Storage Type</option>
<option>HDD</option>
<option>SSD</option>
<option>NVMe</option>
</select>

<input type="text" name="storage_size" class="form-control mb-2" placeholder="Storage Size">

<button name="add_model" class="btn btn-primary w-100">
Add Model
</button>

</form>

</div>
</div>

</div>
</div>


<script>

const categorySelect = document.getElementById('categorySelect');
const itemSelect = document.getElementById('itemSelect');
const modelSelect = document.getElementById('modelSelect');

/* CATEGORY → ITEM FILTER */
categorySelect.addEventListener('change', function(){

let category = this.value;

for(let option of itemSelect.options){

if(option.dataset.category === category || option.value === ""){
 option.style.display = "block";
}else{
 option.style.display = "none";
}

}

itemSelect.value="";
modelSelect.value="";
});


/* ITEM → MODEL FILTER */
itemSelect.addEventListener('change', function(){

let item = this.value;

for(let option of modelSelect.options){

if(option.dataset.item === item || option.value === ""){
 option.style.display = "block";
}else{
 option.style.display = "none";
}

}

modelSelect.value="";
});

document.querySelector("form").addEventListener("submit", function(){
    this.querySelector("button").disabled = true;
});

</script>

<?php
$content = ob_get_clean(); 

include "masterlayout.php"; 
?>