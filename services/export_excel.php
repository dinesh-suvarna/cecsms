<?php
include "../include/db.php";

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=services.csv");

$output = fopen("php://output", "w");

// ✅ Proper Header Row
fputcsv($output, [
    'SL',
    'Date',
    'Item',
    'Type',
    'Vendor',
    'Bill No',
    'Bill Date',
    'Amount'
]);

// ✅ Use JOIN to get vendor_name
$query = "
    SELECT services.date,
           services.item_name,
           services.service_type,
           vendors.vendor_name,
           services.bill_number,
           services.service_date,
           services.amount
    FROM services
    JOIN vendors ON services.vendor_id = vendors.id
    ORDER BY services.service_date DESC
";

$result = $conn->query($query);

$sl = 1;

while($row = $result->fetch_assoc()){
    fputcsv($output, [
        $sl++,
        $row['date'],
        $row['item_name'],
        $row['service_type'],
        $row['vendor_name'],
        $row['bill_number'],
        $row['service_date'],
        $row['amount']
    ]);
}

fclose($output);
exit;
?>