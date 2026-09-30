<?php
require_once __DIR__ . "/../config/db.php";
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
 * If the user is an Admin, verify that the asset actually belongs 
 * to a unit within their division before allowing any changes.
 */
if ($user_role !== 'SuperAdmin') {
    $auth_check = $conn->prepare("
        SELECT fa.id 
        FROM furniture_assets fa
        JOIN furniture_stock s ON fa.stock_id = s.id
        JOIN units u ON s.unit_id = u.id
        WHERE fa.id = ? AND u.division_id = ?
    ");
    $auth_check->bind_param("ii", $id, $user_division);
    $auth_check->execute();
    if ($auth_check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: This asset does not belong to your division.']);
        exit();
    }
}

// --- VERIFY ACTION ---
if ($action === 'verify') {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("UPDATE furniture_assets SET last_verified_date = ? WHERE id = ?");
    $stmt->bind_param("si", $today, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'new_date' => date('d/m/y')]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update verification date']);
    }
} 
// --- DELETE ACTION ---
elseif ($action === 'delete') {
    try {
        // 1. Get the stock_id associated with this asset before deleting it
        $stock_lookup = $conn->prepare("SELECT stock_id FROM furniture_assets WHERE id = ?");
        $stock_lookup->bind_param("i", $id);
        $stock_lookup->execute();
        $stock_data = $stock_lookup->get_result()->fetch_assoc();
        $stock_id = $stock_data['stock_id'] ?? null;

        // 2. Delete the asset record
        $stmt = $conn->prepare("DELETE FROM furniture_assets WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // 3. Automatically decrease BOTH total_qty and available_qty in furniture_stock
            if ($stock_id) {
                $update_stock = $conn->prepare("UPDATE furniture_stock SET total_qty = GREATEST(0, total_qty - 1), available_qty = GREATEST(0, available_qty - 1) WHERE id = ?");
                $update_stock->bind_param("i", $stock_id);
                $update_stock->execute();
            }

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: Could not delete asset.']);
        }
    } catch (mysqli_sql_exception $e) {
        // catch foreign key constraints or database relation errors
        echo json_encode([
            'success' => false, 
            'message' => 'Constraint Error: Cannot delete this asset because it is linked to active logs or records.'
        ]);
    }
    exit();
}
// --- EDIT TAG ACTION ---
elseif ($action === 'edit_tag') {
    $new_tag = strtoupper(trim($_POST['tag'] ?? ''));

    if (empty($new_tag)) {
        echo json_encode(['success' => false, 'message' => 'Tag ID cannot be empty']);
        exit();
    }
    
    // Duplicate check
    $check = $conn->prepare("SELECT id FROM furniture_assets WHERE asset_tag = ? AND id != ?");
    $check->bind_param("si", $new_tag, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'This Tag ID is already in use.']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE furniture_assets SET asset_tag = ? WHERE id = ?");
    $stmt->bind_param("si", $new_tag, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update Asset Tag']);
    }
} 

// --- LIFECYCLE ACTION (Return, Repair, Decommission) ---
elseif ($action === 'lifecycle') {
    $type = $_POST['type'] ?? ''; // 'return', 'repair', or 'dispose'
    
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

    $stmt = $conn->prepare("UPDATE furniture_assets SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update asset status']);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
?>