<?php 
require_once __DIR__ . "/../config/db.php";
include "../includes/functions.php"; 
session_start();

$current_page = 'add_components.php'; 
$page_title = "Add Components";
$page_icon  = "bi-cpu";

$notif_division_id = $_SESSION['division_id'] ?? 0;

// Fetch Vendors
$vendor_res = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name ASC");

// --- BACKEND PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_component'])) {
    $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $spec = mysqli_real_escape_string($conn, $_POST['specification']);
    $qty = (int)$_POST['quantity'];
    $price = (float)$_POST['unit_price'];
    $vendor_id = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : "NULL";

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

<style>
:root {
    --erp-navy: #123b63;
    --erp-navy-dark: #0b2942;
    --erp-blue: #2b628f;
    --erp-green: #3f755e;
    --erp-amber: #9a6b22;
    --erp-bg: #f3f5f7;
    --erp-panel: #ffffff;
    --erp-panel-soft: #f7f9fb;
    --erp-border: #d9e0e7;
    --erp-border-dark: #c6d0da;
    --erp-text: #20384d;
    --erp-text-soft: #526679;
    --erp-muted: #718191;
    --erp-shadow: 0 1px 3px rgba(20,45,70,.05);
}

.container-fluid {
    max-width: 1440px;
    padding: 24px 28px 36px;
}

.dash-card {
    border: 1px solid var(--erp-border) !important;
    border-radius: 6px !important;
    background: var(--erp-panel);
    box-shadow: var(--erp-shadow) !important;
}

.extra-small { font-size: .72rem; }

.form-label-erp {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--erp-text-soft);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.35rem;
}

.form-control-erp, .form-select-erp {
    font-size: 0.85rem;
    border-radius: 5px;
    border: 1px solid var(--erp-border);
    padding: 0.55rem 0.75rem;
    color: var(--erp-text);
    background-color: #ffffff;
    transition: all 0.15s ease-in-out;
}

.form-control-erp:focus, .form-select-erp:focus {
    border-color: var(--erp-blue) !important;
    box-shadow: 0 0 0 3px rgba(43, 98, 143, 0.15) !important;
}

.btn-erp-primary {
    background-color: var(--erp-navy);
    color: #ffffff;
    border: none;
    font-weight: 600;
    font-size: 0.82rem;
    padding: 0.5rem 1.25rem;
    border-radius: 4px;
    transition: background-color 0.15s ease;
}

.btn-erp-primary:hover {
    background-color: var(--erp-navy-dark);
    color: #ffffff;
}

.btn-erp-secondary {
    background-color: #f1f5f9;
    color: var(--erp-text-soft);
    border: 1px solid var(--erp-border);
    font-weight: 600;
    font-size: 0.82rem;
    padding: 0.5rem 1.25rem;
    border-radius: 4px;
}

.btn-erp-secondary:hover {
    background-color: #e2e8f0;
    color: var(--erp-text);
}

.icon-box {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    background: #edf3f8;
    color: var(--erp-blue);
    border: 1px solid rgba(18,59,99,.08);
}
</style>

<div class="container-fluid py-0">
    <!-- Header Section -->
    <div class="d-flex flex-wrap align-items-center justify-content-between pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="icon-box">
                <i class="bi bi-cpu fs-5"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1" style="color: var(--erp-navy-dark); font-size: 1.25rem;">
                    Components Inventory
                </h4>
                <p class="text-muted small mb-0">Register microcontrollers, sensors, modules, and electronic parts.</p>
            </div>
        </div>
        <div>
            <a href="view_components.php" class="btn btn-erp-secondary">
                <i class="bi bi-list-ul me-1"></i> View Inventory Registry
            </a>
        </div>
    </div>

    <!-- Form Card -->
    <div>
        <div class="col-lg-12">
            <div class="card dash-card p-4">
                <form id="componentForm" action="<?= $_SERVER['PHP_SELF']; ?>" method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-erp">Item Name</label>
                            <input type="text" name="item_name" class="form-control form-control-erp" placeholder="e.g. Arduino Uno R3 / L298N Driver" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-erp">Category</label>
                            <select name="category" id="categorySelect" class="form-select form-select-erp" required>
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
                            <label class="form-label-erp">Technical Specifications</label>
                            <input type="text" name="specification" id="specInput" class="form-control form-control-erp" placeholder="Select a category to see hints...">
                            <div id="specHint" class="extra-small mt-1" style="color: var(--erp-blue);"></div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label-erp">Quantity</label>
                            <input type="number" name="quantity" class="form-control form-control-erp" min="1" value="1" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label-erp">Unit Price (₹)</label>
                            <input type="number" step="0.01" name="unit_price" class="form-control form-control-erp" placeholder="0.00" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-erp">Vendor</label>
                            <select name="vendor_id" class="form-select form-select-erp">
                                <option value="">Select Vendor...</option>
                                <?php while($v = $vendor_res->fetch_assoc()): ?>
                                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vendor_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                            <button type="reset" class="btn btn-erp-secondary">Clear</button>
                            <button type="submit" name="add_component" class="btn btn-erp-primary">
                                <i class="bi bi-plus-circle me-1"></i> Add to Stock
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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
    } else {
        specInput.placeholder = "Enter technical details...";
        specHint.innerHTML = "";
    }
});
</script>

<?php
$content = ob_get_clean();

if (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin') {
    include "../stock/stocklayout.php";
} else {
    include "../divisions/divisionslayout.php";
}
include "../includes/notify.php"; 
?>