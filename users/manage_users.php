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
        --emerald-600: #059669;
        --emerald-700: #047857;
        --emerald-50: #ecfdf5;
        --emerald-100: #d1fae5;
    }
    body { background-color: #f9fafb; }
    .user-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid #e5e7eb; background: #fff; }
    .user-card:hover { transform: translateY(-4px); border-color: var(--emerald-100); box-shadow: 0 12px 24px -8px rgba(5, 150, 105, 0.15) !important; }
    .avatar-emerald { width: 48px; height: 48px; background: var(--emerald-50); color: var(--emerald-700); display: flex; align-items: center; justify-content: center; border-radius: 12px; font-weight: 700; font-size: 1.25rem; }
    .role-pill { font-size: 0.75rem; font-weight: 600; padding: 4px 12px; border-radius: 8px; }
    .btn-emerald { background-color: var(--emerald-600); color: white; border: none; }
    .btn-emerald:hover { background-color: var(--emerald-700); color: white; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .bg-emerald-subtle { background-color: var(--emerald-50); color: var(--emerald-700); }
    .global-badge { background: #1e293b; color: #f8fafc; border-radius: 6px; padding: 2px 8px; font-size: 0.7rem; }
</style>

<div class="container-fluid py-2">
    <div class="row align-items-center mb-4 g-3">
        <div class="col-md-6">
            <h3 class="fw-bold text-dark mb-1">User Management</h3>
            <p class="text-muted small mb-0">Manage system access levels and institutional access</p>
        </div>
        <div class="col-md-6 text-md-end">
            <button class="btn btn-emerald px-4 py-2 rounded-3 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
                <i class="bi bi-person-plus me-2"></i> Create User
            </button>
        </div>
    </div>

    <div class="row g-4">
        <?php while($row = $result->fetch_assoc()): 
            $initial = strtoupper(substr($row['username'], 0, 1));
            $isSuper = ($row['role'] === 'SuperAdmin');
        ?>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card h-100 shadow-sm rounded-4 user-card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="avatar-emerald"><?= $initial ?></div>
                        <span class="role-pill <?= $isSuper ? 'bg-dark text-white' : 'bg-emerald-subtle' ?>">
                            <?= $row['role'] ?>
                        </span>
                    </div>

                    <h5 class="fw-bold text-dark mb-1 text-truncate"><?= htmlspecialchars($row['username']) ?></h5>
                    <div class="d-flex align-items-center mb-4">
                        <span class="status-dot <?= $row['status'] == 'Active' ? 'bg-success' : 'bg-secondary' ?> me-2"></span>
                        <span class="text-muted small fw-medium"><?= $row['status'] ?></span>
                    </div>

                    <div class="space-y-2">
                        <?php if($isSuper): ?>
                            <div class="d-flex align-items-center text-dark small fw-semibold py-1">
                                <i class="bi bi-shield-check text-emerald-600 me-2"></i>
                                <span class="global-badge"><i class="bi bi-crown-fill me-1"></i> Global Infrastructure</span>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center text-muted small py-1">
                                <i class="bi bi-building me-2"></i>
                                <span class="text-truncate"><?= htmlspecialchars($row['institution_name'] ?? 'Unassigned') ?></span>
                            </div>
                            <div class="d-flex align-items-center text-muted small py-1">
                                <i class="bi bi-layers me-2"></i>
                                <span class="text-truncate"><?= htmlspecialchars($row['division_name'] ?? 'General Pool') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-footer bg-light/50 border-0 p-4 pt-0 d-flex gap-2">
                    <button class="btn btn-white btn-sm flex-grow-1 rounded-3 border fw-semibold" 
                            onclick="editUser(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>', '<?= $row['role'] ?>', '<?= $row['status'] ?>', '<?= $row['institution_id'] ?>', '<?= $row['division_id'] ?>')">
                        Edit Account
                    </button>
                    
                    <?php if($row['id'] != $currentUserId): ?>
                        <button class="btn <?= $row['status'] == 'Active' ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-sm px-2 rounded-3" 
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
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4 pb-0">
                <h4 class="fw-bold text-dark">User Profile</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <input type="hidden" name="id" id="user_id">

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold text-uppercase">Username</label>
                    <input type="text" name="username" id="username" class="form-control form-control-lg border-2 bg-light focus-emerald" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold text-uppercase">Security Password</label>
                    <input type="password" name="password" class="form-control form-control-lg border-2 bg-light shadow-none" placeholder="••••••••">
                </div>

                <?php if($currentRole === 'SuperAdmin'): ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold text-uppercase">Institution</label>
                        <select name="institution_id" id="institution_id" class="form-select border-2 bg-light shadow-none">
                            <option value="">Select</option>
                            <?php foreach($institutionsArr as $inst): ?>
                                <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['institution_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold text-uppercase">Division</label>
                        <select name="division_id" id="division_id" class="form-select border-2 bg-light shadow-none">
                            <option value="">Select</option>
                            <?php foreach($divisionsArr as $div): ?>
                                <option value="<?= $div['id'] ?>" data-institution="<?= $div['institution_id'] ?>">
                                    <?= htmlspecialchars($div['division_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold text-uppercase">System Role</label>
                    <select name="role" id="role" class="form-select border-2 bg-light shadow-none">
                        <option value="Admin">Administrator</option>
                        <option value="Staff">Regular Staff</option>
                        <option value="SuperAdmin">Super Administrator</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="mb-0">
                    <label class="form-label text-muted small fw-bold text-uppercase">Account Status</label>
                    <select name="status" id="status" class="form-select border-2 bg-light shadow-none">
                        <option value="Active">Active / Enabled</option>
                        <option value="Inactive">Disabled / Locked</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="save_user" class="btn btn-emerald w-100 py-2 fw-bold rounded-3 shadow-sm">Save Profile Changes</button>
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
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, Lock User'
    }).then((r) => { if (r.isConfirmed) window.location.href = `?delete=${userId}&csrf=${csrf}`; });
}

function confirmReactivate(userId, csrf) {
    Swal.fire({
        title: 'Unlock Account?',
        text: "Restore login access for this user.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#059669',
        confirmButtonText: 'Yes, Unlock'
    }).then((r) => { if (r.isConfirmed) window.location.href = `?activate=${userId}&csrf=${csrf}`; });
}
</script>

<?php
$extra_html = ob_get_clean();
include "../admin/adminlayout.php";
?>