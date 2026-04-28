<?php
require_once __DIR__ . "/../config/db.php";

header('Content-Type: application/json');

$response = [
    "divisions" => [],
    "units"     => []
];

/* If institution_id is provided, return divisions + units for first division */
if(isset($_GET['institution_id'])){
    $institution_id = (int)$_GET['institution_id'];

    if($institution_id > 0){
        // Fetch divisions
        $divisions = $conn->query("
            SELECT id, division_name 
            FROM divisions 
            WHERE institution_id=$institution_id AND status='Active'
            ORDER BY division_name ASC
        ");

        while($row = $divisions->fetch_assoc()){
            $response['divisions'][] = [
                "id" => $row['id'],
                "name" => $row['division_name']
            ];
        }

        // Fetch units for first division (if any)
        if(!empty($response['divisions'])){
            $first_division_id = $response['divisions'][0]['id'];
            $units = $conn->query("
                SELECT id, unit_name
                FROM units
                WHERE division_id=$first_division_id AND status='Active'
                ORDER BY unit_name ASC
            ");
            while($row = $units->fetch_assoc()){
                $response['units'][] = [
                    "id" => $row['id'],
                    "name" => $row['unit_name']
                ];
            }
        }
    }
}

/* If division_id is provided (user changed division), return only units */
if(isset($_GET['division_id'])){
    $division_id = (int)$_GET['division_id'];
    $response['units'] = []; // reset

    if($division_id > 0){
        $units = $conn->query("
            SELECT id, unit_name
            FROM units
            WHERE division_id=$division_id AND status='Active'
            ORDER BY unit_name ASC
        ");
        while($row = $units->fetch_assoc()){
            $response['units'][] = [
                "id" => $row['id'],
                "name" => $row['unit_name']
            ];
        }
    }
}

echo json_encode($response);