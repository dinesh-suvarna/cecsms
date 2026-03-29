<?php 
include "../config/db.php"; 
include "../includes/functions.php"; 
session_start();

$current_page = 'add_components.php'; 
// Get division ID from session
$notif_division_id = $_SESSION['division_id'] ?? 0;

// Fetch Vendors from your existing table
$vendor_res = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name ASC");

// --- 1. BACKEND PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_component'])) {
    $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $spec = mysqli_real_escape_string($conn, $_POST['specification']);
    $qty = (int)$_POST['quantity'];
    $price = (float)$_POST['unit_price'];
    $vendor_id = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : "NULL";

    // Logic: Insert including the division_id
    $sql = "INSERT INTO component_stock (division_id, item_name, category, specification, total_quantity, unit_price, vendor_id) 
            VALUES ($notif_division_id, '$item_name', '$category', '$spec', '$qty', '$price', $vendor_id)";

    if ($conn->query($sql)) {
        notify('success', "Stock Updated: $item_name added successfully."); 
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
                            <h4 class="fw-bold mb-1">Components Inventory</h4>
                            <p class="text-muted small mb-0">Manage micro-controllers, sensors, and electronic parts</p>
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
                            <label class="form-label small fw-bold text-muted text-uppercase">Item Name</label>
                            <input type="text" name="item_name" class="form-control form-control-lg border-light-subtle rounded-3" placeholder="e.g. Arduino Uno R3 / L298N" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Category</label>
                            <select name="category" id="categorySelect" class="form-select form-select-lg border-light-subtle rounded-3" required>
                                <option value="">-- Select Category --</option>
                                <option value="Microcontrollers">Microcontrollers (Arduino, ESP32)</option>
                                <option value="Modules">Sensors & Modules (Ultrasonic, LCD)</option>
                                <option value="Semiconductors">ICs & Drivers (L298N, 74HC595)</option>
                                <option value="Connectors">Wires & Breadboards</option>
                                <option value="Motors">Servos & DC Motors</option>
                                <option value="Passives">Resistors & Capacitors</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted text-uppercase">Technical Specifications</label>
                            <input type="text" name="specification" id="specInput" class="form-control form-control-lg border-light-subtle rounded-3" placeholder="Select a category to see hints...">
                            <div id="specHint" class="form-text small text-emerald-600 mt-1"></div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Quantity</label>
                            <input type="number" name="quantity" class="form-control form-control-lg border-light-subtle rounded-3" min="1" value="1" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Unit Price (Amount)</label>
                            <input type="number" step="0.01" name="unit_price" class="form-control form-control-lg border-light-subtle rounded-3" placeholder="0.00" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Vendor</label>
                            <select name="vendor_id" class="form-select form-select-lg border-light-subtle rounded-3">
                                <option value="">Select Vendor...</option>
                                <?php while($v = $vendor_res->fetch_assoc()): ?>
                                    <option value="<?= $v['id'] ?>"><?= $v['vendor_name'] ?></option>
                                <?php endwhile; ?>
                            </select>
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
:root { --emerald: #10b981; --emerald-dark: #059669; }
.form-control-lg, .form-select-lg { font-size: 0.95rem; padding: 0.75rem 1rem; background-color: #fcfdfe; }
.form-control:focus, .form-select:focus { border-color: var(--emerald) !important; box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.1) !important; }
.btn-emerald-submit { background-color: var(--emerald); border: none; transition: all 0.3s ease; }
.btn-emerald-submit:hover { background-color: var(--emerald-dark); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }
.text-emerald-600 { color: var(--emerald-dark); font-weight: 500; }
</style>

<script>
const categorySpecs = {
    "Microcontrollers": "e.g. ATmega328P, 5V Logic, 14 Digital/6 Analog Pins",
    "Modules": "e.g. HC-SR04, 2cm-400cm, 5V / 16x2 LCD, I2C Address 0x27",
    "Semiconductors": "e.g. Dual H-Bridge, 2A Peak, 5-35V DC / 8-bit Shift Register",
    "Connectors": "e.g. 40-pin M-M Ribbon, 20cm / 830 Point MB-102 Breadboard",
    "Motors": "e.g. SG90 9g, 1.6kg/cm Torque, 180 Degree / 300RPM DC Gear Motor",
    "Passives": "e.g. 10k Ohm, 1/4W, 5% / 100uF 25V Electrolytic"
};

const categorySelect = document.getElementById('categorySelect');
const specInput = document.getElementById('specInput');
const specHint = document.getElementById('specHint');

categorySelect.addEventListener('change', function() {
    const selected = this.value;
    if (categorySpecs[selected]) {
        specInput.placeholder = categorySpecs[selected];
        specHint.innerHTML = "Suggested format: " + categorySpecs[selected];
        specInput.classList.add('bg-light');
        setTimeout(() => specInput.classList.remove('bg-light'), 300);
    } else {
        specInput.placeholder = "Enter technical details...";
        specHint.innerHTML = "";
    }
});
</script>

<?php
$content = ob_get_clean();

// Check role for layout
if (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin') {
    include "stocklayout.php";
} else {
    include "../divisions/divisionslayout.php";
}
include "../includes/notify.php"; 
?>