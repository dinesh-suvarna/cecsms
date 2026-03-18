<?php
session_start();
if (!isset($_SESSION["user_id"])) { exit("Access Denied"); }

include "../config/db.php";

// 1. SET FILTERS FROM URL
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : null;
$from = isset($_GET['from']) ? $_GET['from'] : null;
$to = isset($_GET['to']) ? $_GET['to'] : null;

// 2. BUILD DYNAMIC SQL
$conditions = [];
$params = [];
$types = "";

if ($vendor_id) {
    $conditions[] = "s.vendor_id = ?";
    $params[] = $vendor_id;
    $types .= "i";
}

if ($from && $to) {
    $conditions[] = "s.service_date BETWEEN ? AND ?";
    $params[] = $from;
    $params[] = $to;
    $types .= "ss";
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$query = "SELECT s.service_date, v.vendor_name, s.item_name, s.service_type, s.bill_number, s.amount, s.bill_status 
          FROM services s 
          JOIN vendors v ON s.vendor_id = v.id 
          $whereClause 
          ORDER BY s.service_date DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// 3. GENERATE EXCEL FILE
$filename = "Service_Report_" . date('Y-m-d_His') . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

echo "Date\tVendor\tItem Details\tService Type\tBill Ref\tAmount\tStatus\n";

while ($row = $result->fetch_assoc()) {
    echo $row['service_date'] . "\t" . 
         $row['vendor_name'] . "\t" . 
         $row['item_name'] . "\t" . 
         $row['service_type'] . "\t" . 
         $row['bill_number'] . "\t" . 
         $row['amount'] . "\t" . 
         $row['bill_status'] . "\n";
}

$stmt->close();
$conn->close();
exit();
?>