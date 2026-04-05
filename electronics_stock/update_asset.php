<?php
include "../config/db.php";
session_start();

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

/* 🔐 Division Security Check */
if ($user_role !== 'SuperAdmin') {
    $auth_check = $conn->prepare("
        SELECT ea.id 
        FROM electronics_assets ea
        JOIN electronics_stock s ON ea.stock_id = s.id
        JOIN units u ON s.unit_id = u.id
        WHERE ea.id = ? AND u.division_id = ?
    ");
    $auth_check->bind_param("ii", $id, $user_division);
    $auth_check->execute();

    if ($auth_check->get_result()->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Access Denied: Not your division'
        ]);
        exit();
    }
}

/* ✅ VERIFY */
if ($action === 'verify') {
    $today = date('Y-m-d');

    $stmt = $conn->prepare("
        UPDATE electronics_assets 
        SET last_verified_date = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("si", $today, $id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'new_date' => date('d/m/y')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Verification failed'
        ]);
    }
}

/* ✏️ EDIT TAG */
elseif ($action === 'edit_tag') {

    $new_tag = strtoupper(trim($_POST['tag'] ?? ''));

    if (empty($new_tag)) {
        echo json_encode([
            'success' => false,
            'message' => 'Tag cannot be empty'
        ]);
        exit();
    }

    // Duplicate check
    $check = $conn->prepare("
        SELECT id FROM electronics_assets 
        WHERE asset_tag = ? AND id != ?
    ");
    $check->bind_param("si", $new_tag, $id);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Tag already exists'
        ]);
        exit();
    }

    $stmt = $conn->prepare("
        UPDATE electronics_assets 
        SET asset_tag = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("si", $new_tag, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Update failed'
        ]);
    }
}

else {
    echo json_encode([
        'success' => false,
        'message' => 'Unknown action'
    ]);
}
?>