<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";
require_once "../includes/session.php";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

/* ===================== COUNTS ===================== */

// Institutions
if($role == 'SuperAdmin'){
    $inst_query = "SELECT COUNT(*) as total FROM institutions WHERE status='Active'";
    $inst_result = $conn->query($inst_query);
    $total_institutions = $inst_result->fetch_assoc()['total'];
} else {
    $total_institutions = 1;
}

// Divisions
if($role == 'SuperAdmin'){
    $div_query = "SELECT COUNT(*) as total FROM divisions WHERE status='Active'";
    $div_result = $conn->query($div_query);
} else {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM divisions
        WHERE status='Active'
        AND institution_id=?
    ");
    $stmt->bind_param("i", $user_institution_id);
    $stmt->execute();
    $div_result = $stmt->get_result();
}
$total_divisions = $div_result->fetch_assoc()['total'];

// Units
if($role == 'SuperAdmin'){
    $unit_query = "SELECT COUNT(*) as total FROM units WHERE status='Active'";
    $unit_result = $conn->query($unit_query);
} else {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM units u
        JOIN divisions d ON u.division_id=d.id
        WHERE u.status='Active'
        AND d.institution_id=?
    ");
    $stmt->bind_param("i", $user_institution_id);
    $stmt->execute();
    $unit_result = $stmt->get_result();
}
$total_units = $unit_result->fetch_assoc()['total'];


/* ===================== MASTER STOCK ===================== */

// 1. Get TOTAL Physical Quantity from stock_details
if ($role == 'SuperAdmin') {
    $total_q = "SELECT SUM(quantity) as total FROM stock_details";
    $total_res = $conn->query($total_q);
} else {
    $stmt = $conn->prepare("
        SELECT SUM(sd.quantity) as total
        FROM stock_details sd
        JOIN items_master im ON sd.stock_item_id = im.id
        WHERE im.institution_id = ?
    ");
    $stmt->bind_param("i", $user_institution_id);
    $stmt->execute();
    $total_res = $stmt->get_result();
}
$total_assets = $total_res->fetch_assoc()['total'] ?? 0;


// 2. Get TOTAL Dispatched Quantity from dispatch_details
if ($role == 'SuperAdmin') {
    $disp_q = "SELECT SUM(quantity - IFNULL(returned_quantity, 0)) as total FROM dispatch_details";
    $disp_res = $conn->query($disp_q);
} else {
    $stmt = $conn->prepare("
        SELECT SUM(dd.quantity - IFNULL(dd.returned_quantity, 0)) as total
        FROM dispatch_details dd
        JOIN dispatch_master dm ON dd.dispatch_id = dm.id
        WHERE dm.institution_id = ?
    ");
    $stmt->bind_param("i", $user_institution_id);
    $stmt->execute();
    $disp_res = $stmt->get_result();
}
$dispatched_assets = $disp_res->fetch_assoc()['total'] ?? 0;


// 3. Calculate Percentage
$avail_percent = ($total_assets > 0)
    ? round(($dispatched_assets / $total_assets) * 100)
    : 0;
?>

<?php ob_start(); ?>

<style>

/* =========================================================
   MASTER DASHBOARD
   Institutional ERP Theme
========================================================= */

:root {
    --master-navy: #173f63;
    --master-navy-dark: #102f4a;
    --master-blue: #0d6efd;

    --master-bg: #f5f7fa;
    --master-card: #ffffff;

    --master-border: #dce3e9;
    --master-border-light: #e9eef2;

    --master-text: #20384d;
    --master-muted: #71808e;

    --master-green: #3f765f;
    --master-purple: #6f5b95;

    --master-shadow:
        0 1px 3px rgba(20, 45, 70, .06);

    --master-shadow-hover:
        0 8px 20px rgba(20, 45, 70, .10);
}


/* =========================================================
   PAGE
========================================================= */

.master-dashboard {
    max-width: 1500px;
    margin: 0 auto;
    padding: 26px 30px 40px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.master-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    padding-bottom: 20px;
    margin-bottom: 24px;

    border-bottom: 1px solid var(--master-border);
}

.master-header-title {
    margin: 0;

    color: var(--master-navy-dark);

    font-size: 1.25rem;
    font-weight: 700;
    letter-spacing: -.01em;
}

.master-header-subtitle {
    margin: 5px 0 0;

    color: var(--master-muted);

    font-size: .78rem;
}


/* =========================================================
   SYSTEM STATUS
========================================================= */

.master-system-status {
    display: inline-flex;
    align-items: center;
    gap: 7px;

    padding: 7px 11px;

    border: 1px solid #dbe5ed;
    border-radius: 4px;

    background: #fff;

    color: var(--master-muted);

    font-size: .68rem;
    font-weight: 600;
}

.master-status-dot {
    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: var(--master-green);

    box-shadow: 0 0 0 3px rgba(63,118,95,.10);
}


/* =========================================================
   KPI GRID
========================================================= */

.master-kpi-grid {
    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 18px;

    margin-bottom: 30px;
}


/* =========================================================
   KPI CARD
========================================================= */

.master-kpi-card {
    position: relative;

    min-height: 178px;

    padding: 21px;

    background: var(--master-card);

    border: 1px solid var(--master-border);

    border-radius: 6px;

    box-shadow: var(--master-shadow);

    overflow: hidden;

    transition:
        border-color .18s ease,
        box-shadow .18s ease,
        transform .18s ease;
}

.master-kpi-card:hover {
    transform: translateY(-2px);

    border-color: #c8d3dc;

    box-shadow: var(--master-shadow-hover);
}


/* top accent */

.master-kpi-card::before {
    content: "";

    position: absolute;

    top: 0;
    left: 0;

    width: 100%;
    height: 3px;

    background: var(--kpi-accent);
}


/* =========================================================
   KPI TOP
========================================================= */

.master-kpi-top {
    display: flex;

    align-items: flex-start;
    justify-content: space-between;
}


/* =========================================================
   KPI ICON
========================================================= */

.master-kpi-icon {
    width: 43px;
    height: 43px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 5px;

    background: var(--kpi-soft);

    color: var(--kpi-accent);

    font-size: 1.15rem;
}


/* =========================================================
   KPI TAG
========================================================= */

.master-kpi-tag {
    padding: 4px 8px;

    border: 1px solid var(--kpi-border);

    border-radius: 3px;

    background: var(--kpi-soft);

    color: var(--kpi-accent);

    font-size: .61rem;
    font-weight: 700;

    text-transform: uppercase;
    letter-spacing: .04em;
}


/* =========================================================
   KPI CONTENT
========================================================= */

.master-kpi-content {
    margin-top: 20px;
}

.master-kpi-label {
    color: var(--master-muted);

    font-size: .69rem;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .055em;
}

.master-kpi-value {
    margin-top: 3px;

    color: var(--master-text);

    font-size: 1.9rem;

    line-height: 1.1;

    font-weight: 750;

    letter-spacing: -.025em;
}


/* =========================================================
   KPI FOOTER
========================================================= */

.master-kpi-footer {
    display: flex;

    align-items: center;

    gap: 7px;

    margin-top: 13px;

    padding-top: 11px;

    border-top: 1px solid var(--master-border-light);

    color: var(--master-muted);

    font-size: .69rem;
}

.master-kpi-footer i {
    color: var(--kpi-accent);
}


/* =========================================================
   KPI COLORS
========================================================= */

.kpi-institution {
    --kpi-accent: #2d638e;
    --kpi-soft: #edf4f8;
    --kpi-border: #d9e7ef;
}

.kpi-division {
    --kpi-accent: #6f5b95;
    --kpi-soft: #f1eef7;
    --kpi-border: #e0d9ed;
}

.kpi-unit {
    --kpi-accent: #647585;
    --kpi-soft: #f0f3f5;
    --kpi-border: #dce2e6;
}


/* =========================================================
   MASTER DATA SECTION
========================================================= */

.master-section {
    background: var(--master-card);

    border: 1px solid var(--master-border);

    border-radius: 6px;

    box-shadow: var(--master-shadow);

    overflow: hidden;
}


/* =========================================================
   SECTION HEADER
========================================================= */

.master-section-header {
    display: flex;

    align-items: center;
    justify-content: space-between;

    padding: 17px 20px;

    border-bottom: 1px solid var(--master-border);

    background: #fafbfc;
}

.master-section-heading {
    display: flex;

    align-items: center;

    gap: 11px;
}

.master-section-icon {
    width: 34px;
    height: 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 4px;

    background: #edf3f8;

    color: var(--master-navy);

    font-size: .95rem;
}

.master-section-title {
    margin: 0;

    color: var(--master-text);

    font-size: .88rem;

    font-weight: 700;
}

.master-section-description {
    margin: 2px 0 0;

    color: var(--master-muted);

    font-size: .68rem;
}


/* =========================================================
   QUICK ACTIONS
========================================================= */

.master-actions {
    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 14px;

    padding: 20px;
}


.master-action {
    display: flex;

    align-items: center;

    gap: 13px;

    padding: 14px 15px;

    background: #fff;

    border: 1px solid var(--master-border);

    border-radius: 5px;

    color: var(--master-text);

    text-decoration: none !important;

    transition:
        border-color .18s ease,
        background .18s ease,
        transform .18s ease;
}

.master-action:hover {
    background: #f8fafc;

    border-color: #b9c8d4;

    color: var(--master-navy);

    transform: translateY(-1px);
}


/* =========================================================
   ACTION ICON
========================================================= */

.master-action-icon {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 4px;

    background: #edf3f8;

    color: var(--master-navy);

    font-size: 1rem;
}


/* =========================================================
   ACTION CONTENT
========================================================= */

.master-action-content {
    min-width: 0;
}

.master-action-title {
    display: block;

    color: var(--master-text);

    font-size: .77rem;

    font-weight: 700;
}

.master-action-subtitle {
    display: block;

    margin-top: 2px;

    color: var(--master-muted);

    font-size: .65rem;
}

.master-action-arrow {
    margin-left: auto;

    color: #a2adb7;

    font-size: .75rem;

    transition: transform .18s ease;
}

.master-action:hover .master-action-arrow {
    transform: translateX(3px);

    color: var(--master-navy);
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 991.98px) {

    .master-dashboard {
        padding: 22px 20px 32px;
    }

    .master-kpi-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .master-actions {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

}


@media (max-width: 767.98px) {

    .master-header {
        align-items: flex-start;

        flex-direction: column;

        gap: 13px;
    }

    .master-system-status {
        align-self: flex-start;
    }

    .master-kpi-grid {
        grid-template-columns: 1fr;

        gap: 14px;
    }

    .master-actions {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 575.98px) {

    .master-dashboard {
        padding: 18px 12px 25px;
    }

    .master-header-title {
        font-size: 1.1rem;
    }

    .master-kpi-card {
        min-height: 165px;

        padding: 18px;
    }

    .master-kpi-value {
        font-size: 1.7rem;
    }

    .master-section-header {
        padding: 15px;
    }

    .master-actions {
        padding: 14px;
    }

}


/* =========================================================
   DARK MODE
========================================================= */

[data-bs-theme="dark"] {

    --master-bg: #101a24;
    --master-card: #172534;

    --master-border: #2d3e4e;
    --master-border-light: #253746;

    --master-text: #edf3f7;
    --master-muted: #9aabb9;

    --master-navy: #8eafc9;
    --master-navy-dark: #dce8f0;
}


[data-bs-theme="dark"] .master-kpi-card,
[data-bs-theme="dark"] .master-section {
    background: var(--master-card);
}


[data-bs-theme="dark"] .master-section-header {
    background: #142230;
}


[data-bs-theme="dark"] .master-system-status,
[data-bs-theme="dark"] .master-action {
    background: #172534;
    border-color: var(--master-border);
}


[data-bs-theme="dark"] .master-action:hover {
    background: #1b2b3a;
}


[data-bs-theme="dark"] .master-kpi-icon,
[data-bs-theme="dark"] .master-section-icon,
[data-bs-theme="dark"] .master-action-icon {
    background: #203445;
}


[data-bs-theme="dark"] .master-kpi-tag {
    background: #203445;
}

</style>


<!-- =====================================================
     MASTER DASHBOARD
===================================================== -->

<div class="master-dashboard">

    <!-- PAGE HEADER -->
    <div class="master-header">

        <div>
            <h3 class="master-header-title">
                Master Dashboard
            </h3>

            <p class="master-header-subtitle">
                Overview of institutional master data and system configuration
            </p>
        </div>

        <div class="master-system-status">
            <span class="master-status-dot"></span>
            System Active
        </div>

    </div>


    <!-- =================================================
         KPI CARDS
    ================================================== -->

    <div class="master-kpi-grid">


        <!-- INSTITUTIONS -->
        <div class="master-kpi-card kpi-institution">

            <div class="master-kpi-top">

                <div class="master-kpi-icon">
                    <i class="bi bi-building-fill"></i>
                </div>

                <span class="master-kpi-tag">
                    Institutions
                </span>

            </div>

            <div class="master-kpi-content">

                <div class="master-kpi-label">
                    Active Institutions
                </div>

                <div class="master-kpi-value">
                    <?= inr($total_institutions) ?>
                </div>

            </div>

            <div class="master-kpi-footer">

                <i class="bi bi-check-circle-fill"></i>

                <span>
                    Registered institutional locations
                </span>

            </div>

            <a href="institutions.php"
               class="stretched-link"
               aria-label="Manage institutions"></a>

        </div>


        <!-- DIVISIONS -->
        <div class="master-kpi-card kpi-division">

            <div class="master-kpi-top">

                <div class="master-kpi-icon">
                    <i class="bi bi-diagram-3-fill"></i>
                </div>

                <span class="master-kpi-tag">
                    Departments
                </span>

            </div>

            <div class="master-kpi-content">

                <div class="master-kpi-label">
                    Active Departments
                </div>

                <div class="master-kpi-value">
                    <?= inr($total_divisions) ?>
                </div>

            </div>

            <div class="master-kpi-footer">

                <i class="bi bi-check-circle-fill"></i>

                <span>
                    Functional areas configured
                </span>

            </div>

            <a href="divisions.php"
               class="stretched-link"
               aria-label="Manage departments"></a>

        </div>


        <!-- UNITS -->
        <div class="master-kpi-card kpi-unit">

            <div class="master-kpi-top">

                <div class="master-kpi-icon">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                </div>

                <span class="master-kpi-tag">
                    Facilities
                </span>

            </div>

            <div class="master-kpi-content">

                <div class="master-kpi-label">
                    Labs & Facilities
                </div>

                <div class="master-kpi-value">
                    <?= inr($total_units) ?>
                </div>

            </div>

            <div class="master-kpi-footer">

                <i class="bi bi-check-circle-fill"></i>

                <span>
                    Operational locations tracked
                </span>

            </div>

            <a href="units.php"
               class="stretched-link"
               aria-label="Manage labs and facilities"></a>

        </div>

    </div>


    <!-- =================================================
         MASTER DATA SETUP
    ================================================== -->

    <div class="master-section">

        <div class="master-section-header">

            <div class="master-section-heading">

                <div class="master-section-icon">
                    <i class="bi bi-sliders"></i>
                </div>

                <div>

                    <h6 class="master-section-title">
                        Master Data Setup
                    </h6>

                    <p class="master-section-description">
                        Configure the core institutional structure
                    </p>

                </div>

            </div>

        </div>


        <div class="master-actions">


            <!-- ADD INSTITUTION -->
            <a href="institutions.php"
               class="master-action">

                <div class="master-action-icon">
                    <i class="bi bi-building-add"></i>
                </div>

                <div class="master-action-content">

                    <span class="master-action-title">
                        Add Institution
                    </span>

                    <span class="master-action-subtitle">
                        Register a new institution
                    </span>

                </div>

                <i class="bi bi-chevron-right master-action-arrow"></i>

            </a>


            <!-- ADD DEPARTMENT -->
            <a href="divisions.php"
               class="master-action">

                <div class="master-action-icon">
                    <i class="bi bi-diagram-3"></i>
                </div>

                <div class="master-action-content">

                    <span class="master-action-title">
                        Add Department
                    </span>

                    <span class="master-action-subtitle">
                        Create an institutional department
                    </span>

                </div>

                <i class="bi bi-chevron-right master-action-arrow"></i>

            </a>


            <!-- ADD FACILITY -->
            <a href="units.php"
               class="master-action">

                <div class="master-action-icon">
                    <i class="bi bi-grid-3x3-gap"></i>
                </div>

                <div class="master-action-content">

                    <span class="master-action-title">
                        Add Lab / Facility
                    </span>

                    <span class="master-action-subtitle">
                        Register a lab or operational location
                    </span>

                </div>

                <i class="bi bi-chevron-right master-action-arrow"></i>

            </a>


        </div>

    </div>

</div>


<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>