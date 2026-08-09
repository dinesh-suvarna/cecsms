<?php
// admin/start-maintenance.php
$maintenanceFile = dirname(__DIR__) . '/maintenance.json';

// Trigger maintenance window to execute exactly 5 minutes from run time
$data = [
    'is_planned' => true,
    'start_time' => time() + (5 * 60) 
];

file_put_contents($maintenanceFile, json_encode($data));

echo "<h3>Deployment Complete</h3>";
echo "StockFlow users are now tracking a 5-minute shutdown timer in the background.";