<?php
require_once __DIR__ . "/../config/db.php";
session_start();

header('Content-Type: application/json');

// 1. SESSION & ROLE SECURITY
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;
$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing Asset ID.']);
    exit();
}

// 2. DIVISIONAL SECURITY CHECK
if ($user_role !== 'SuperAdmin') {
    $auth_check = $conn->prepare("
        SELECT ea.id 
        FROM electrical_assets ea
        JOIN electrical_stock s ON ea.stock_id = s.id
        JOIN units u ON s.unit_id = u.id
        WHERE ea.id = ? AND u.division_id = ?
    ");
    $auth_check->bind_param("ii", $id, $user_division);
    $auth_check->execute();
    if ($auth_check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: Asset division mismatch.']);
        exit();
    }
}

// 3. ACTION DISPATCHER
// --- VERIFY ASSET ---
if ($action === 'verify') {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("UPDATE electrical_assets SET last_verified_date = ? WHERE id = ?");
    $stmt->bind_param("si", $today, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'new_date' => date('d/m/y')]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update verification date.']);
    }
} 

// --- SOFT DELETE (Archive) ---
elseif ($action === 'soft_delete') {
    $now = date('Y-m-d H:i:s');
    // set deleted_at. This hides it from the Registry but keeps the row in DB.
    $stmt = $conn->prepare("UPDATE electrical_assets SET deleted_at = ? WHERE id = ?");
    $stmt->bind_param("si", $now, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Asset archived. History preserved.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error during archiving.']);
    }
}

// --- HARD DELETE (Permanent) ---
elseif ($action === 'hard_delete') {
    // remove the row. This allows the stock quantity to "re-open" in the tagging queue.
    $stmt = $conn->prepare("DELETE FROM electrical_assets WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Asset permanently removed. Item returned to queue.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete record.']);
    }
}

// --- EDIT TAG ID ---
elseif ($action === 'edit_tag') {
    $new_tag = strtoupper(trim($_POST['tag'] ?? ''));

    if (empty($new_tag)) {
        echo json_encode(['success' => false, 'message' => 'Tag ID cannot be empty.']);
        exit();
    }
    
    // Duplicate check: Ignore soft-deleted assets and the current record itself
    $check = $conn->prepare("SELECT id FROM electrical_assets WHERE asset_tag = ? AND id != ? AND deleted_at IS NULL");
    $check->bind_param("si", $new_tag, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Conflict: This Tag ID is already assigned to an active asset.']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE electrical_assets SET asset_tag = ? WHERE id = ?");
    $stmt->bind_param("si", $new_tag, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed.']);
    }
} 

// --- LIFECYCLE MANAGEMENT ---
elseif ($action === 'lifecycle') {
    $type = $_POST['type'] ?? ''; 
    $remarks = trim($_POST['remarks'] ?? '');
    
    $status_map = [
        'return'  => 'Available',
        'repair'  => 'Damaged',
        'dispose' => 'Disposed'
    ];

    if (!isset($status_map[$type])) {
        echo json_encode(['success' => false, 'message' => 'Invalid lifecycle transition.']);
        exit();
    }

    $new_status = $status_map[$type];

    $stmt = $conn->prepare("UPDATE electrical_assets SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Status update failed.']);
    }
}

// --- FALLBACK ---
else {
    echo json_encode(['success' => false, 'message' => 'Unknown request action: ' . $action]);
}

$conn->close();
?>