<?php
if (session_status() === PHP_SESSION_NONE) {

    // Force secure cookies in production (recommended)
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,  // TRUE if using HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}

/* ======================================================
   SESSION FIXATION PROTECTION (Bind to IP + User Agent)
====================================================== */

if (!isset($_SESSION['ip_address'])) {
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
}

if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
}

if (
    ($_SESSION['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) ||
    ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? ''))
) {
    session_unset();
    session_destroy();
    header("Location:../admin/login.php?security=1");
    exit();
}

/* ======================================================
   ABSOLUTE SESSION ROTATION (Every 30 minutes)
====================================================== */

$absolute_timeout = 1800;

if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > $absolute_timeout) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

/* ======================================================
   INACTIVITY TIMEOUT (30 minutes)
====================================================== */

$inactivity_timeout = 1800;

if (isset($_SESSION['last_activity'])) {

    if (time() - $_SESSION['last_activity'] > $inactivity_timeout) {

        $_SESSION = [];
        session_unset();
        session_destroy();

        header("Location: login.php?timeout=1");
        exit();
    }
}

/* Update last activity */
$_SESSION['last_activity'] = time();