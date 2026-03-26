<?php 
include "../config/db.php"; 
include "../includes/functions.php"; 
session_start();

// Set this for sidebar active state
$current_page = 'add_components.php'; 

// --- 1. BACKEND PROCESSING LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_component'])) {
    $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $spec = mysqli_real_escape_string($conn, $_POST['specification']);
    $qty = (int)$_POST['quantity'];

    $sql = "INSERT INTO component_stock (item_name, category, specification, total_quantity) 
            VALUES ('$item_name', '$category', '$spec', '$qty')";

    if ($conn->query($sql)) {
        notify('success', "Stock Updated: $qty units of $item_name added successfully."); 
    } else {
        notify('danger', "Database Error: " . $conn->error);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

ob_start();
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-11">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-body py-4 px-4 d-flex align-items-center justify-content-between bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box me-3 p-3 rounded-4" style="background: rgba(16, 185, 129, 0.1);">
                            <i class="bi bi-cpu-fill fs-3" style="color: #10b981;"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-1" style="letter-spacing: -0.02rem;">Components & ICs</h4>
                            <p class="text-muted small mb-0">Manage micro-inventory and electronic modules</p>
                        </div>
                    </div>
                    <a href="view_components.php" class="btn text-white px-4 rounded-pill shadow-sm fw-bold" style="background-color: #10b981; border: none;">
                        <i class="bi bi-list-ul me-2"></i> View Inventory
                    </a>
                </div>
            </div>

            <form id="componentForm" action="<?= $_SERVER['PHP_SELF']; ?>" method="POST">
                <div class="card border-0 shadow-sm rounded-4 p-5 bg-white">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase" style="font-size: 11px;">Item Name</label>
                            <input type="text" name="item_name" class="form-control form-control-lg border-light-subtle rounded-3" placeholder="e.g. Arduino Uno R3 / Resistor" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase" style="font-size: 11px;">Category</label>
                            <select name="category" class="form-select form-select-lg border-light-subtle rounded-3">
                                <option value="Microcontrollers">Microcontrollers</option>
                                <option value="Passives">Passives (Resistors/Caps)</option>
                                <option value="Semiconductors">Semiconductors (ICs/Transistors)</option>
                                <option value="Modules">Sensors & Modules</option>
                                <option value="Connectors">Wires & Connectors</option>
                                <option value="Other">Other Components</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label small fw-bold text-muted text-uppercase" style="font-size: 11px;">Technical Specifications / Value</label>
                            <input type="text" name="specification" class="form-control form-control-lg border-light-subtle rounded-3" placeholder="e.g. 10k Ohm, 1/4W, 5V Logic">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted text-uppercase" style="font-size: 11px;">Quantity</label>
                            <div class="input-group input-group-lg">
                                <input type="number" name="quantity" class="form-control border-light-subtle rounded-start-3" min="1" value="1" required>
                                <span class="input-group-text bg-light border-light-subtle rounded-end-3 text-muted">pcs</span>
                            </div>
                        </div>

                        <div class="col-12 mt-5 pt-4 border-top d-flex justify-content-end gap-3">
                            <button type="reset" class="btn btn-light px-4 border rounded-pill text-muted fw-semibold">Clear</button>
                            <button type="submit" name="add_component" class="btn text-white px-5 rounded-pill shadow-sm fw-bold btn-emerald-submit">
                                <i class="bi bi-plus-circle me-2"></i> Add to Stock
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Exact emerald colors from your screenshot */
:root {
    --emerald-600: #10b981;
    --emerald-700: #059669;
}

.form-control-lg, .form-select-lg {
    font-size: 1rem;
    padding: 0.75rem 1rem;
    background-color: #fcfdfe;
}

.form-control:focus, .form-select:focus {
    border-color: var(--emerald-600) !important;
    box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.1) !important;
    background-color: #fff;
}

.btn-emerald-submit {
    background-color: var(--emerald-600);
    border: none;
    transition: all 0.3s ease;
}

.btn-emerald-submit:hover {
    background-color: var(--emerald-700);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
}

/* Sidebar logic: Ensuring exact placement in your nav bar */
.nav-link.active {
    background-color: var(--emerald-600) !important;
    color: white !important;
    border-radius: 0 50px 50px 0; /* Matches your screenshot highlight */
}

</style>

<script>
// Dynamic placeholder logic
document.querySelector('select[name="category"]').addEventListener('change', function() {
    let specInput = document.querySelector('input[name="specification"]');
    switch(this.value) {
        case 'Passives': 
            specInput.placeholder = "e.g. 10k Ohm, 1/4W, 5% Tolerance"; 
            break;
        case 'Microcontrollers': 
            specInput.placeholder = "e.g. R3 Revision, 5V Logic, ATmega328P"; 
            break;
        case 'Modules': 
            specInput.placeholder = "e.g. I2C Interface, 3.3V-5V Input"; 
            break;
        default: 
            specInput.placeholder = "e.g. Technical details, voltage, or part number";
    }
});
</script>

<?php
$content = ob_get_clean();
include "stocklayout.php";
include "../includes/notify.php"; 
?>