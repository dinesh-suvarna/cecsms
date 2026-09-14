<?php

require_once __DIR__ . "/../config/db.php";

/*
|--------------------------------------------------------------------------
| Start existing PHP session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {

    $isSecure = (
        !empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}


/*
|--------------------------------------------------------------------------
| Verify logged-in user
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    http_response_code(401);

    header("Content-Type: application/json");

    echo json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Update active session
|--------------------------------------------------------------------------
*/

$session_id = session_id();

$stmt = $conn->prepare("
    UPDATE active_sessions
    SET last_activity = NOW()
    WHERE session_id = ?
");

if (!$stmt) {

    http_response_code(500);

    header("Content-Type: application/json");

    echo json_encode([
        "success" => false,
        "message" => "Database error"
    ]);

    exit;
}

$stmt->bind_param("s", $session_id);
$stmt->execute();

$updated = $stmt->affected_rows;

$stmt->close();


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

header("Content-Type: application/json");

echo json_encode([
    "success" => true,
    "updated" => $updated
]);

exit;