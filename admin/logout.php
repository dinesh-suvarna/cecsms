<?php

require_once "../config/db.php";
require_once "../includes/session.php";


/* =========================================================
   RECORD LOGOUT TIME
   ---------------------------------------------------------
   Updates the exact login history record belonging
   to this browser/session.
========================================================= */

if (
    isset($_SESSION['login_log_id']) &&
    isset($conn)
) {

    $login_log_id = intval(
        $_SESSION['login_log_id']
    );

    $stmt = $conn->prepare("
        UPDATE login_logs
        SET logout_time = NOW()
        WHERE id = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $login_log_id
        );

        $stmt->execute();

        $stmt->close();
    }
}


/* =========================================================
   REMOVE ACTIVE SESSION
========================================================= */

if (
    isset($_SESSION['user_id']) &&
    isset($conn)
) {

    $session_id = session_id();

    $stmt = $conn->prepare(
        "DELETE FROM active_sessions
         WHERE session_id = ?"
    );

    if ($stmt) {

        $stmt->bind_param(
            "s",
            $session_id
        );

        $stmt->execute();

        $stmt->close();
    }
}


/* =========================================================
   UNSET SESSION
========================================================= */

$_SESSION = [];


/* =========================================================
   DELETE SESSION COOKIE
========================================================= */

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}


/* =========================================================
   DESTROY SESSION
========================================================= */

session_destroy();


/* =========================================================
   PREVENT CACHING
========================================================= */

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

header(
    "Cache-Control: post-check=0, pre-check=0",
    false
);

header("Pragma: no-cache");

header("Expires: 0");


/* =========================================================
   REDIRECT
========================================================= */

header("Location: login.php");

exit();