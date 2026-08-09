<?php
// check-status.php
header('Content-Type: application/json');

$maintenanceFile = __DIR__ . '/maintenance.json';

if (!file_exists($maintenanceFile)) {
    echo json_encode(['is_planned' => false, 'active' => false, 'seconds_left' => 0]);
    exit();
}

$data = json_decode(file_get_contents($maintenanceFile), true);
$data['active'] = (time() >= $data['start_time']);
$data['seconds_left'] = $data['is_planned'] ? ($data['start_time'] - time()) : 0;

echo json_encode($data);
exit();