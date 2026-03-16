<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Generate token once per session */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* Return token for forms */
function csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

/* Verify token */
function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) &&
           is_string($token) &&
           hash_equals($_SESSION['csrf_token'], $token);
}

/* Optional: Regenerate token manually */
function regenerate_csrf() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}