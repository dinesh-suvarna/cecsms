<?php
require_once __DIR__ . "/../config/db.php";

if(isset($_POST['institution_id'])){

    $institution_id = intval($_POST['institution_id']);

    $stmt = $conn->prepare("
        SELECT id, division_name 
        FROM divisions 
        WHERE institution_id = ? 
        AND status = 'Active'
        ORDER BY division_name
    ");
    $stmt->bind_param("i", $institution_id);
    $stmt->execute();
    $result = $stmt->get_result();

    echo '<option value="">Select Division</option>';

    while($row = $result->fetch_assoc()){
        echo '<option value="'. $row['id'] .'">'
             . htmlspecialchars($row['division_name']) .
             '</option>';
    }

    $stmt->close();
}
?>