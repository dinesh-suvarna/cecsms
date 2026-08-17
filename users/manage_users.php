<?php
require_once "../includes/session.php";
require_once "../includes/role_admin.php";
require_once "../includes/security_headers.php";
require_once __DIR__ . "/../config/db.php";

/* -----------------------------
   Page title
------------------------------ */
$page_title = "User Management";

/* -----------------------------
   Initialize messages
------------------------------ */
$success = "";
$error   = "";

/* -----------------------------
   Current user info
------------------------------ */
$currentRole        = $_SESSION['role'] ?? '';
$currentUserId      = $_SESSION['user_id'] ?? 0;
$currentInstitution = $_SESSION['institution_id'] ?? null;
$currentDivision    = $_SESSION['division_id'] ?? null;

/* -----------------------------
   Generate CSRF token if missing
------------------------------ */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/* ================= DELETE (SOFT) USER ================= */
if(isset($_GET['delete'], $_GET['csrf']) && hash_equals($csrf_token, $_GET['csrf'])){
    $id = intval($_GET['delete']);
    $check = $conn->prepare("SELECT role FROM users WHERE id=?");
    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result();

    if($result->num_rows === 1){
        if($id != $currentUserId){
            $stmt = $conn->prepare("UPDATE users SET status = 'Inactive' WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }
    }
    header("Location: manage_users.php?msg=deactivated");
    exit();
}

/* ================= REACTIVATE USER ================= */
if(isset($_GET['activate'], $_GET['csrf']) && hash_equals($csrf_token, $_GET['csrf'])){
    $id = intval($_GET['activate']);
    $stmt = $conn->prepare("UPDATE users SET status = 'Active' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: manage_users.php?msg=activated");
    exit();
}

/* ================= ADD / UPDATE USER ================= */
if(isset($_POST['save_user'])){
    if(!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])){
        die("Invalid CSRF token");
    }

    $id = $_POST['id'] ?? '';
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role'] ?? '');
    $status = trim($_POST['status']);
    $division_id = $_POST['division_id'] ?? null;
    $division_id = ($division_id === '' || $division_id == 0) ? null : intval($division_id);
    $institution_id = null;

    if($currentRole === 'SuperAdmin'){
        if($role !== 'SuperAdmin'){
            $institution_id = intval($_POST['institution_id'] ?? 0);
            $institution_id = ($institution_id === 0) ? null : $institution_id;
        }
    } else {
        $role = 'Staff';
        $institution_id = $currentInstitution;
        $division_id = $currentDivision;
    }

    if(empty($username)) $error = "Username is required.";

    if(empty($error)){
        if(empty($id)){
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username,password,role,status,institution_id,division_id) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssssii",$username,$hashed,$role,$status,$institution_id,$division_id);
            $stmt->execute();
        } else {
            if(!empty($password)){
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username=?, password=?, role=?, status=?, institution_id=?, division_id=? WHERE id=?");
                $stmt->bind_param("ssssiii",$username,$hashed,$role,$status,$institution_id,$division_id,$id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, role=?, status=?, institution_id=?, division_id=? WHERE id=?");
                $stmt->bind_param("sssiii",$username,$role,$status,$institution_id,$division_id,$id);
            }
            $stmt->execute();
        }
        header("Location: manage_users.php");
        exit();
    }
}

/* ================= FETCH USERS ================= */
$query = "SELECT users.*, institutions.institution_name, divisions.division_name
          FROM users
          LEFT JOIN institutions ON users.institution_id = institutions.id
          LEFT JOIN divisions ON users.division_id = divisions.id";

if($currentRole === 'Admin'){
    $query .= " WHERE users.institution_id = $currentInstitution AND users.division_id = $currentDivision";
}

// First sort by Role Priority, then by most recently created
$query .= " ORDER BY FIELD(role, 'SuperAdmin', 'Admin', 'Staff') ASC, users.id DESC";

$result = $conn->query($query);

$institutionsArr = [];
$instResult = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name ASC");
while($row = $instResult->fetch_assoc()) {
    $institutionsArr[] = $row;
}

$divisionsArr = [];
$divResult = $conn->query("SELECT id, institution_id, division_name FROM divisions ORDER BY division_name ASC");
while($row = $divResult->fetch_assoc()) {
    $divisionsArr[] = $row;
}

ob_start();
?>

<style>
:root {
    --inst-navy: #173f63;
    --inst-navy-dark: #102f4a;
    --inst-blue: #2d638e;
    --inst-green: #3f765f;
    --inst-red: #9b4d4d;
    --inst-bg: #f4f6f8;
    --inst-panel: #ffffff;
    --inst-soft: #f7f9fb;
    --inst-border: #d9e0e7;
    --inst-border-dark: #c6d0da;
    --inst-text: #20384d;
    --inst-muted: #6d7d8c;
    --inst-shadow: 0 1px 2px rgba(18,45,70,.06);
}

body {
    background-color: var(--inst-bg);
}

.user-management-page {
    max-width: 1500px;
    margin: 0 auto;
    padding: 26px 30px 38px;
}

.um-page-header {
    padding-bottom: 18px;
    margin-bottom: 22px;
    border-bottom: 1px solid var(--inst-border);
}

.um-page-header h3 {
    color: var(--inst-navy-dark) !important;
    font-size: 1.22rem;
    font-weight: 650 !important;
}

.um-page-header p {
    color: var(--inst-muted) !important;
}

.btn-institutional {
    background: var(--inst-navy);
    border: 1px solid var(--inst-navy);
    color: #fff;
    border-radius: 5px !important;
    font-size: .82rem;
    font-weight: 600;
    padding: 9px 16px;
    box-shadow: none !important;
}

.btn-institutional:hover,
.btn-institutional:focus {
    background: var(--inst-navy-dark);
    border-color: var(--inst-navy-dark);
    color: #fff;
}

.user-card {
    border: 1px solid var(--inst-border) !important;
    border-radius: 6px !important;
    background: var(--inst-panel);
    box-shadow: var(--inst-shadow) !important;
    transition: border-color .18s ease, box-shadow .18s ease;
    overflow: hidden;
}

.user-card:hover {
    transform: none !important;
    border-color: var(--inst-border-dark) !important;
    box-shadow: 0 4px 12px rgba(18,45,70,.08) !important;
}

.user-card .card-body {
    padding: 20px !important;
}

.avatar-institutional {
    min-width: 43px;
    width: auto;
    height: 32px;
    padding: 0 9px;
    background: #edf3f8;
    color: var(--inst-navy);
    border: 1px solid #dbe5ed;
    border-radius: 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: .78rem;
    letter-spacing: .02em;
    white-space: nowrap;
}

.role-pill {
    font-size: .65rem;
    font-weight: 700;
    padding: 5px 8px;
    border-radius: 3px !important;
    text-transform: uppercase;
    letter-spacing: .03em;
    border: 1px solid transparent;
}

.role-standard {
    background: #0d6efd;
    color: #fff;
    border-color: #dbe5ed;
}

.role-super {
    background: #263b4d !important;
    color: #fff !important;
}

.user-name {
    color: var(--inst-text) !important;
    font-size: .98rem;
    font-weight: 650 !important;
}

.status-line {
    padding-bottom: 14px;
    border-bottom: 1px solid #e9edf1;
}

.status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    display: inline-block;
}

.status-active {
    background: var(--inst-green) !important;
}

.status-inactive {
    background: #9aa6b1 !important;
}

.user-meta {
    color: var(--inst-muted) !important;
    font-size: .76rem;
}

.user-meta i {
    width: 17px;
    color: #8292a1;
}

/* Institution name - keep on a single line */
.institution-name {
    display: block;
    width: 100%;
    white-space: nowrap;
    overflow: visible;
    font-size: .70rem;
    line-height: 1.35;
}

/* Division name - allow up to 2 lines */
.division-name {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    overflow: hidden;
    line-height: 1.4;
    font-size: .70rem;
    max-height: 2.8em;
    word-break: normal;
    overflow-wrap: break-word;
}

.global-badge {
    background: #f0f3f6;
    color: var(--inst-navy-dark);
    border: 1px solid var(--inst-border);
    border-radius: 3px;
    padding: 4px 7px;
    font-size: .65rem;
    font-weight: 700;
}

.user-card .card-footer {
    background: #fafbfc !important;
    border-top: 1px solid var(--inst-border) !important;
    padding: 12px 20px !important;
}

.btn-edit-user {
    background: #fff;
    color: var(--inst-navy);
    border: 1px solid var(--inst-border-dark);
    border-radius: 4px !important;
    font-size: .75rem;
    font-weight: 600;
}

.btn-edit-user:hover {
    background: var(--inst-navy);
    color: #fff;
    border-color: #aebbc7;
}

.btn-account {
    border-radius: 4px !important;
    font-size: .78rem;
    width: 34px;
}

.modal-content {
    border: 1px solid var(--inst-border) !important;
    border-radius: 6px !important;
    box-shadow: 0 12px 35px rgba(18,45,70,.14) !important;
}

.modal-header {
    border-bottom: 1px solid var(--inst-border) !important;
    padding: 18px 22px !important;
}

.modal-header h4 {
    color: var(--inst-navy-dark) !important;
    font-size: 1.05rem;
    margin: 0;
}

.modal-body {
    padding: 22px !important;
}

.modal-footer {
    border-top: 1px solid var(--inst-border) !important;
    padding: 14px 22px !important;
    background: #fafbfc;
}

.form-label {
    color: #526679 !important;
    font-size: .67rem !important;
    letter-spacing: .045em;
}

.form-control,
.form-select {
    border: 1px solid var(--inst-border-dark) !important;
    border-radius: 4px !important;
    background: #fff !important;
    color: var(--inst-text) !important;
    box-shadow: none !important;
    font-size: .84rem;
}

.form-control-lg {
    min-height: 42px;
    font-size: .85rem;
}

.form-control:focus,
.form-select:focus {
    border-color: var(--inst-blue) !important;
    box-shadow: 0 0 0 2px rgba(45,99,142,.10) !important;
}

.form-control::placeholder {
    color: #9aa6b1;
}

/* Custom dropdown fields */
.select-wrapper {
    position: relative;
}

.select-wrapper .form-select {
    padding-right: 38px;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}

.select-wrapper .select-arrow {
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: #718096;
    font-size: .75rem;
    pointer-events: none;
    transition: color .15s ease;
}

.select-wrapper:focus-within .select-arrow {
    color: #0d6efd;
}

.btn-save-user {
    background: var(--inst-navy);
    border: 1px solid var(--inst-navy);
    color: #fff;
    border-radius: 4px !important;
    font-size: .82rem;
    font-weight: 650;
}

.btn-save-user:hover {
    background: var(--inst-navy-dark);
    border-color: var(--inst-navy-dark);
    color: #fff;
}

.tooltip-inner {
    font-size: .7rem;
    border-radius: 3px;
}

@media (max-width: 991.98px) {
    .user-management-page {
        padding: 20px;
    }
}

@media (max-width: 575.98px) {
    .user-management-page {
        padding: 17px 12px 25px;
    }

    .um-page-header h3 {
        font-size: 1.1rem;
    }

    .um-page-header .btn-institutional {
        width: 100%;
    }

    .user-card .card-body {
        padding: 17px !important;
    }

    .user-card .card-footer {
        padding: 11px 17px !important;
    }
}

[data-bs-theme="dark"] {
    --inst-bg: #101a24;
    --inst-panel: #172534;
    --inst-soft: #1b2b3a;
    --inst-border: #2d3e4e;
    --inst-border-dark: #3d5162;
    --inst-text: #edf3f7;
    --inst-muted: #9aabb9;
    --inst-navy: #8eafc9;
    --inst-navy-dark: #dce8f0;
}

[data-bs-theme="dark"] .user-card,
[data-bs-theme="dark"] .modal-content {
    background: var(--inst-panel);
}

[data-bs-theme="dark"] .user-card .card-footer,
[data-bs-theme="dark"] .modal-footer {
    background: #142230 !important;
    border-color: var(--inst-border) !important;
}

[data-bs-theme="dark"] .avatar-institutional {
    background: #203445;
    border-color: #33495a;
    color: #b8d0e2;
}

[data-bs-theme="dark"] .role-standard,
[data-bs-theme="dark"] .global-badge {
    background: #203445;
    border-color: #354b5d;
    color: #c9d8e3;
}

[data-bs-theme="dark"] .btn-edit-user,
[data-bs-theme="dark"] .form-control,
[data-bs-theme="dark"] .form-select {
    background: #172534 !important;
    color: var(--inst-text) !important;
    border-color: var(--inst-border-dark) !important;
}
</style>

<div class="user-management-page">
    <div class="um-page-header">
        <div class="row align-items-center g-3">
            <div class="col-md-7">
                <h3 class="mb-1">User Management</h3>
                <p class="small mb-0">Manage system access levels and institutional access</p>
            </div>
            <div class="col-md-5 text-md-end">
                <button class="btn btn-institutional" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
                    <i class="bi bi-person-plus me-2"></i>Create User
                </button>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <?php while($row = $result->fetch_assoc()):
            // Remove the "CEC" prefix from the username
            $avatarText = preg_replace('/^CEC/i', '', $row['username']);

            // Convert to uppercase
            $avatarText = strtoupper($avatarText);

            // Fallback if username is only "CEC"
            if (empty($avatarText)) {
                $avatarText = strtoupper(substr($row['username'], 0, 1));
            }

            $isSuper = ($row['role'] === 'SuperAdmin');
        ?>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card h-100 user-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="avatar-institutional"><?= htmlspecialchars($avatarText) ?></div>

                        <span class="role-pill <?= $isSuper ? 'role-super' : 'role-standard' ?>">
                            <?= $row['role'] ?>
                        </span>
                    </div>

                    <h5 class="user-name mb-1 text-truncate"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="<?= htmlspecialchars($row['username']) ?>">
                        <?= htmlspecialchars($row['username']) ?>
                    </h5>

                    <div class="d-flex align-items-center status-line mb-3">
                        <span class="status-dot <?= $row['status'] == 'Active' ? 'status-active' : 'status-inactive' ?> me-2"></span>
                        <span class="user-meta fw-semibold"><?= $row['status'] ?></span>
                    </div>

                    <?php if($isSuper): ?>
                        <div class="d-flex align-items-center user-meta py-1">
                            <i class="bi bi-shield-check me-2"></i>
                            <span class="global-badge">
                                <i class="bi bi-crown-fill me-1"></i>Global Infrastructure
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-start user-meta py-1">
                            <i class="bi bi-building me-2 flex-shrink-0"></i>
                            <span class="institution-name"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                title="<?= htmlspecialchars($row['institution_name'] ?? 'Unassigned') ?>">
                                <?= htmlspecialchars($row['institution_name'] ?? 'Unassigned') ?>
                            </span>
                        </div>

                        <div class="d-flex align-items-start user-meta py-1">
                            <i class="bi bi-layers me-2 flex-shrink-0"></i>

                            <?php
                            if (!empty($row['division_name'])) {
                                $formatted = ucwords(strtolower($row['division_name']));
                                $divName = preg_replace_callback('/\b(And|Or|Of|In|For|With|At|To|The)\b/i', function($matches) {
                                    return strtolower($matches[0]);
                                }, $formatted);
                                $divName = ucfirst($divName);
                            } else {
                                $divName = 'General Pool';
                            }
                            ?>

                            <span class="division-name"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                title="<?= htmlspecialchars($divName) ?>">
                                <?= htmlspecialchars($divName) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer d-flex gap-2">
                    <button class="btn btn-edit-user btn-sm flex-grow-1"
                            onclick="editUser(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>', '<?= $row['role'] ?>', '<?= $row['status'] ?>', '<?= $row['institution_id'] ?>', '<?= $row['division_id'] ?>')">
                        Edit Account
                    </button>

                    <?php if($row['id'] != $currentUserId): ?>
                        <button class="btn <?= $row['status'] == 'Active' ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-sm btn-account"
                                onclick="<?= $row['status'] == 'Active' ? 'confirmDeactivate' : 'confirmReactivate' ?>(<?= $row['id'] ?>, '<?= $csrf_token ?>')">
                            <i class="bi <?= $row['status'] == 'Active' ? 'bi-person-x' : 'bi-person-check' ?>"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
ob_start();
?>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="fw-bold">User Profile</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="id" id="user_id">

                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase">Username</label>
                        <input type="text" name="username" id="username" class="form-control form-control-lg" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase">Security Password</label>
                        <input type="password" name="password" class="form-control form-control-lg" placeholder="••••••••">
                    </div>

                    <?php if($currentRole === 'SuperAdmin'): ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase">Institution</label>
                            <div class="select-wrapper">
                                <select name="institution_id" id="institution_id" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach($institutionsArr as $inst): ?>
                                        <option value="<?= $inst['id'] ?>">
                                            <?= htmlspecialchars($inst['institution_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <i class="bi bi-chevron-down select-arrow"></i>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase">Division</label>
                            <div class="select-wrapper">
                                <select name="division_id" id="division_id" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach($divisionsArr as $div): ?>
                                        <option value="<?= $div['id'] ?>" data-institution="<?= $div['institution_id'] ?>">
                                            <?= htmlspecialchars(ucwords(strtolower($div['division_name']))) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <i class="bi bi-chevron-down select-arrow"></i>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase">System Role</label>
                        <div class="select-wrapper">
                            <select name="role" id="role" class="form-select">
                                <option value="Admin">Administrator</option>
                                <option value="Staff">Regular Staff</option>
                                <option value="SuperAdmin">Super Administrator</option>
                            </select>

                            <i class="bi bi-chevron-down select-arrow"></i>
                        </div>
                         
                    </div>
                    <?php endif; ?>

                    <div class="mb-0">
                        <label class="form-label fw-bold text-uppercase">Account Status</label>
                        <div class="select-wrapper">
                            <select name="status" id="status" class="form-select">
                                <option value="Active">Active / Enabled</option>
                                <option value="Inactive">Disabled / Locked</option>
                            </select>

                            <i class="bi bi-chevron-down select-arrow"></i>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="save_user" class="btn btn-save-user w-100 py-2">
                        Save Profile Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function editUser(id, username, role, status, institution_id, division_id) {
    document.getElementById('user_id').value = id;
    document.getElementById('username').value = username;
    document.getElementById('status').value = status;

    let roleSelect = document.getElementById('role');
    if (roleSelect) {
        roleSelect.value = role;
        roleSelect.disabled = (role === 'SuperAdmin');
    }

    let instSelect = document.getElementById('institution_id');
    let divSelect = document.getElementById('division_id');

    if (instSelect) {
        instSelect.value = (role === 'SuperAdmin') ? '' : institution_id;
        instSelect.disabled = (role === 'SuperAdmin');
    }

    if (divSelect) {
        if (role === 'SuperAdmin') {
            divSelect.value = '';
            divSelect.disabled = true;
        } else {
            divSelect.disabled = false;
            if (instSelect) filterDivisions(institution_id);
            divSelect.value = division_id;
        }
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('userModal')).show();
}

function filterDivisions(institutionId) {
    let ds = document.getElementById('division_id');
    if(!ds) return;
    ds.querySelectorAll('option').forEach(opt => {
        if(!opt.value) return;
        opt.style.display = (opt.dataset.institution == institutionId) ? 'block' : 'none';
    });
}

function resetForm(){
    document.getElementById('user_id').value = '';
    document.getElementById('username').value = '';
    let rs = document.getElementById('role');
    if(rs){ rs.value = 'Admin'; rs.disabled = false; }
}

document.getElementById('institution_id')?.addEventListener('change', function(){
    filterDivisions(this.value);
    document.getElementById('division_id').value = '';
});

function confirmDeactivate(userId, csrf) {
    Swal.fire({
        title: 'Lock Account?',
        text: "This user will lose all system access.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#9b4d4d',
        confirmButtonText: 'Yes, Lock User'
    }).then((r) => { if (r.isConfirmed) window.location.href = `?delete=${userId}&csrf=${csrf}`; });
}

function confirmReactivate(userId, csrf) {
    Swal.fire({
        title: 'Unlock Account?',
        text: "Restore login access for this user.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3f765f',
        confirmButtonText: 'Yes, Unlock'
    }).then((r) => { if (r.isConfirmed) window.location.href = `?activate=${userId}&csrf=${csrf}`; });
}
</script>

<?php
$extra_html = ob_get_clean();
include "../admin/adminlayout.php";
?>