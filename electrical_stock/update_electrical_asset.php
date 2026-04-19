<?php
include "../config/db.php";
session_start();

// Ensure the response is always JSON
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;
$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

/**
 * SECURITY CHECK: 
 * Verify the electrical asset belongs to the Admin's division.
 */
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
        echo json_encode(['success' => false, 'message' => 'Access Denied: This electrical asset does not belong to your division.']);
        exit();
    }
}

// --- VERIFY ACTION ---
if ($action === 'verify') {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("UPDATE electrical_assets SET last_verified_date = ? WHERE id = ?");
    $stmt->bind_param("si", $today, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'new_date' => date('d/m/y')]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update verification date']);
    }
} 
// --- DELETE ACTION ---
elseif ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM electrical_assets WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: Could not delete electrical asset.']);
    }
}
// --- EDIT TAG ACTION ---
elseif ($action === 'edit_tag') {
    $new_tag = strtoupper(trim($_POST['tag'] ?? ''));

    if (empty($new_tag)) {
        echo json_encode(['success' => false, 'message' => 'Tag ID cannot be empty']);
        exit();
    }
    
    // Duplicate check for electrical assets
    $check = $conn->prepare("SELECT id FROM electrical_assets WHERE asset_tag = ? AND id != ?");
    $check->bind_param("si", $new_tag, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'This Tag ID is already in use for another electrical asset.']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE electrical_assets SET asset_tag = ? WHERE id = ?");
    $stmt->bind_param("si", $new_tag, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update Asset Tag']);
    }
} 

// --- LIFECYCLE ACTION ---
elseif ($action === 'lifecycle') {
    $type = $_POST['type'] ?? ''; 
    
    $status_map = [
        'return'  => 'Available',
        'repair'  => 'Damaged',
        'dispose' => 'Disposed'
    ];

    if (!isset($status_map[$type])) {
        echo json_encode(['success' => false, 'message' => 'Invalid lifecycle action type']);
        exit();
    }

    $new_status = $status_map[$type];

    $stmt = $conn->prepare("UPDATE electrical_assets SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update electrical asset status']);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
?>