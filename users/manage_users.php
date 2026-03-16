<?php
require_once "../includes/session.php";
require_once "../includes/role_admin.php";
require_once "../includes/security_headers.php";
require_once "../config/db.php";

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

/* ================= DELETE USER ================= */
if(isset($_GET['delete'], $_GET['csrf']) && hash_equals($csrf_token, $_GET['csrf'])){

    $id = intval($_GET['delete']);

    $check = $conn->prepare("SELECT role, institution_id, division_id FROM users WHERE id=?");
    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result();

    if($result->num_rows === 1){

        $target = $result->fetch_assoc();

        // Prevent deleting yourself
        if($id == $currentUserId){
            header("Location: manage_users.php");
            exit();
        }

        // SuperAdmin can delete anyone
        if($currentRole === 'SuperAdmin'){
            $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }

        // Admin can delete only Staff in same institution + division
        elseif($currentRole === 'Admin'){
            if(
                $target['role'] === 'Staff' &&
                $target['institution_id'] == $currentInstitution &&
                $target['division_id'] == $currentDivision
            ){
                $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
            }
        }
    }

    header("Location: manage_users.php");
    exit();
}

/* ================= ADD / UPDATE USER ================= */
if(isset($_POST['save_user'])){

    // Verify CSRF
    if(!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])){
        die("Invalid CSRF token");
    }

    $id        = $_POST['id'] ?? '';
    $username  = trim($_POST['username']);
    $password  = trim($_POST['password']);
    $role      = trim($_POST['role'] ?? '');
    $status    = trim($_POST['status']);
    $division_id = $_POST['division_id'] ?? null;
    $division_id = ($division_id === '' || $division_id == 0) ? null : intval($division_id);
    $institution_id = null;

    /* Lock existing SuperAdmin on update */
    if(!empty($id)){
        $checkRole = $conn->prepare("SELECT role FROM users WHERE id=?");
        $checkRole->bind_param("i", $id);
        $checkRole->execute();
        $existingUser = $checkRole->get_result()->fetch_assoc();

        if($existingUser && $existingUser['role'] === 'SuperAdmin'){
            $role = 'SuperAdmin';
            $institution_id = null;
            $division_id = null;
        }
    }

    /* Role & Scope Control */
    if($currentRole === 'SuperAdmin'){
        if($role === 'SuperAdmin'){
            $institution_id = null;
            $division_id = null;
        } else {
            $institution_id = intval($_POST['institution_id'] ?? 0);
            $institution_id = ($institution_id === 0) ? null : $institution_id;
        }
    } elseif($currentRole === 'Admin') {
        $role = 'Staff';
        $institution_id = $currentInstitution;
        $division_id = $currentDivision;
    } else {
        header("Location: manage_users.php");
        exit();
    }

    /* Validate username */
    if(empty($username)){
        $error = "Username is required.";
    }

    /* Check duplicate username */
    $check = $conn->prepare("SELECT id FROM users WHERE username=? AND id!=?");
    $check->bind_param("si", $username, $id);
    $check->execute();
    $check->store_result();
    if($check->num_rows > 0){
        $error = "Username already exists!";
    }

    /* INSERT OR UPDATE */
    if(empty($error)){
        if(empty($id)){
            if(empty($password)){
                $error = "Password is required for new user.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username,password,role,status,institution_id,division_id) VALUES (?,?,?,?,?,?)");
                $stmt->bind_param("ssssii",$username,$hashed,$role,$status,$institution_id,$division_id);
                $stmt->execute();
                header("Location: manage_users.php");
                exit();
            }
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
            header("Location: manage_users.php");
            exit();
        }
    }
}

/* ================= FETCH USERS ================= */
if($currentRole === 'SuperAdmin'){
    $result = $conn->query("
        SELECT users.*, 
               institutions.institution_name,
               divisions.division_name
        FROM users
        LEFT JOIN institutions ON users.institution_id = institutions.id
        LEFT JOIN divisions ON users.division_id = divisions.id
        ORDER BY users.id DESC
    ");
} elseif($currentRole === 'Admin'){
    $stmt = $conn->prepare("
        SELECT users.*, 
               institutions.institution_name,
               divisions.division_name
        FROM users
        LEFT JOIN institutions ON users.institution_id = institutions.id
        LEFT JOIN divisions ON users.division_id = divisions.id
        WHERE users.institution_id = ?
        AND users.division_id = ?
        ORDER BY users.id DESC
    ");
    $stmt->bind_param("ii", $currentInstitution, $currentDivision);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $stmt = $conn->prepare("
        SELECT users.*, 
               institutions.institution_name,
               divisions.division_name
        FROM users
        LEFT JOIN institutions ON users.institution_id = institutions.id
        LEFT JOIN divisions ON users.division_id = divisions.id
        WHERE users.id = ?
    ");
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
}

/* ================= FETCH INSTITUTIONS & DIVISIONS ================= */
$institutionsArr = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name ASC")->fetch_all(MYSQLI_ASSOC);
$divisionsArr    = $conn->query("SELECT id, division_name, institution_id FROM divisions WHERE status='Active' ORDER BY division_name ASC")->fetch_all(MYSQLI_ASSOC);

/* =================== HTML / Modal =================== */
ob_start();
?>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body">

        <div class="d-flex justify-content-between mb-3">
            <h5 class="fw-semibold">All Users</h5>
            <button class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#userModal"
                onclick="resetForm()">
                <i class="bi bi-plus-lg"></i> Add User
            </button>
        </div>

        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Sl.No</th>
                    <th>Username</th>
                    <th>Institution</th>
                    <th>Division</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th width="150">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $i=1; while($row=$result->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= htmlspecialchars($row['institution_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['division_name'] ?? '-') ?></td>

                    <td>
                        <?php
                            $badgeClass = [
                                'SuperAdmin' => 'bg-dark',
                                'Admin'      => 'bg-danger',
                                'Staff'      => 'bg-primary'
                            ][$row['role']] ?? 'bg-secondary';
                        ?>
                        <span class="badge <?= $badgeClass ?>">
                            <?= htmlspecialchars($row['role']) ?>
                        </span>
                    </td>

                    <td>
                        <span class="badge <?= $row['status']=='Active' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= htmlspecialchars($row['status']) ?>
                        </span>
                    </td>

                    <td><?= $row['created_at'] ?></td>

                    <td>
                        <button class="btn btn-sm btn-warning"
                            onclick="editUser(
                                <?= $row['id'] ?>,
                                '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>',
                                '<?= $row['role'] ?>',
                                '<?= $row['status'] ?>',
                                '<?= $row['institution_id'] ?>',
                                '<?= $row['division_id'] ?>'
                            )"
                            data-bs-toggle="modal"
                            data-bs-target="#userModal">
                            <i class="bi bi-pencil"></i>
                        </button>

                        <?php if($row['id'] != $currentUserId): ?>
                        <a href="?delete=<?= $row['id'] ?>&csrf=<?= $csrf_token ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Delete this user?')">
                            <i class="bi bi-trash"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    </div>
</div>

<!-- USER MODAL -->
<div class="modal fade" id="userModal">
  <div class="modal-dialog">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add / Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" name="id" id="user_id">

                <div class="mb-3">
                    <label>Username</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Password (Leave blank to keep same)</label>
                    <input type="password" name="password" class="form-control">
                </div>

                <?php if($currentRole === 'SuperAdmin'): ?>
                <div class="mb-3">
                    <label>Institution</label>
                    <select name="institution_id" id="institution_id" class="form-select">
                        <?php foreach($institutionsArr as $inst): ?>
                            <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['institution_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label>Division</label>
                    <select name="division_id" id="division_id" class="form-select">
                        <option value="">Select Division</option>
                        <?php foreach($divisionsArr as $div): ?>
                            <option value="<?= $div['id'] ?>" data-institution="<?= $div['institution_id'] ?>">
                                <?= htmlspecialchars($div['division_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label>Role</label>
                    <select name="role" id="role" class="form-select">
                        <option value="Admin">Admin</option>
                        <option value="Staff">Staff</option>
                        <option value="SuperAdmin">SuperAdmin</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label>Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

            </div>

            <div class="modal-footer">
                <button type="submit" name="save_user" class="btn btn-primary">Save</button>
            </div>
        </div>
    </form>
  </div>
</div>

<script>
function editUser(id, username, role, status, institution_id, division_id){

    document.getElementById('user_id').value = id;
    document.getElementById('username').value = username;
    document.getElementById('status').value = status;

    let roleSelect = document.getElementById('role');
    let institutionSelect = document.getElementById('institution_id');
    let divisionSelect = document.getElementById('division_id');

    if(roleSelect){
        roleSelect.value = role;
        roleSelect.disabled = (role === 'SuperAdmin');
    }

    if(institutionSelect){
        if(role === 'SuperAdmin'){
            institutionSelect.value = '';
            institutionSelect.disabled = true;
        } else {
            institutionSelect.value = institution_id;
            institutionSelect.disabled = false;
        }
    }

    if(divisionSelect){
        if(role === 'SuperAdmin'){
            divisionSelect.value = '';
            divisionSelect.disabled = true; 
        } else {
            divisionSelect.value = division_id;
            divisionSelect.disabled = false;
        }
    }
}

function resetForm(){
    document.getElementById('user_id').value = '';
    document.getElementById('username').value = '';
    document.getElementById('status').value = 'Active';

    let roleSelect = document.getElementById('role');
    let institutionSelect = document.getElementById('institution_id');
    let divisionSelect = document.getElementById('division_id');

    if(roleSelect){
        roleSelect.value = 'Admin';
        roleSelect.disabled = false;
    }
    if(institutionSelect){
        institutionSelect.value = '';
        institutionSelect.disabled = false;
    }
    if(divisionSelect){
        divisionSelect.value = '';
        divisionSelect.disabled = false;
    }
}

// Filter divisions by institution dynamically
document.getElementById('institution_id')?.addEventListener('change', function(){
    let institutionId = this.value;
    let divisionSelect = document.getElementById('division_id');
    let options = divisionSelect.querySelectorAll('option');

    options.forEach(option => {
        if(!option.value) return;
        option.style.display = (option.dataset.institution === institutionId) ? 'block' : 'none';
    });
    divisionSelect.value = '';
});
</script>

<?php
$content = ob_get_clean();
include "../admin/adminlayout.php";
?>