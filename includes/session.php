<?php

/* =========================================================
   START SESSION
========================================================= */

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


/* =========================================================
   SESSION FIXATION / HIJACK PROTECTION
   Bind session to IP + User Agent
========================================================= */

if (!isset($_SESSION['ip_address'])) {

    $_SESSION['ip_address'] =
        $_SERVER['REMOTE_ADDR'] ?? '';
}

if (!isset($_SESSION['user_agent'])) {

    $_SESSION['user_agent'] =
        $_SERVER['HTTP_USER_AGENT'] ?? '';
}

if (
    $_SESSION['ip_address'] !==
        ($_SERVER['REMOTE_ADDR'] ?? '') ||

    $_SESSION['user_agent'] !==
        ($_SERVER['HTTP_USER_AGENT'] ?? '')
) {

    /*
       Remove active session before destroying PHP session
    */

    if (
        isset($_SESSION['user_id']) &&
        isset($conn)
    ) {

        $sid = session_id();

        $stmt = $conn->prepare(
            "DELETE FROM active_sessions
             WHERE session_id = ?"
        );

        if ($stmt) {

            $stmt->bind_param("s", $sid);
            $stmt->execute();
            $stmt->close();
        }
    }

    $_SESSION = [];

    session_unset();

    session_destroy();

    header(
        "Location:../admin/login.php?security=1"
    );

    exit();
}


/* =========================================================
   ABSOLUTE SESSION ROTATION
   Every 30 minutes
========================================================= */

$absolute_timeout = 1800;

if (!isset($_SESSION['created'])) {

    $_SESSION['created'] = time();

} elseif (
    time() - $_SESSION['created'] >
    $absolute_timeout
) {

    /*
       IMPORTANT:
       Store the OLD session ID before
       regenerating the session.
    */

    $old_session_id = session_id();

    /*
       Generate a new session ID.
    */

    session_regenerate_id(true);

    $new_session_id = session_id();

    $_SESSION['created'] = time();

    /*
       Update ONLY the active session that
       belongs to this browser/session.

       Do NOT update by user_id because
       the same user may have multiple
       active sessions.
    */

    if (
        isset($_SESSION['user_id']) &&
        isset($conn)
    ) {

        $stmt = $conn->prepare(
            "UPDATE active_sessions
             SET session_id = ?,
                 last_activity = NOW()
             WHERE session_id = ?"
        );

        if ($stmt) {

            $stmt->bind_param(
                "ss",
                $new_session_id,
                $old_session_id
            );

            $stmt->execute();

            $stmt->close();
        }
    }
}


/* =========================================================
   INACTIVITY TIMEOUT
   30 MINUTES
========================================================= */

$inactivity_timeout = 1800;

if (isset($_SESSION['last_activity'])) {

    if (
        time() - $_SESSION['last_activity'] >
        $inactivity_timeout
    ) {

        /*
           Remove this specific active session
        */

        if (
            isset($_SESSION['user_id']) &&
            isset($conn)
        ) {

            $sid = session_id();

            $stmt = $conn->prepare(
                "DELETE FROM active_sessions
                 WHERE session_id = ?"
            );

            if ($stmt) {

                $stmt->bind_param("s", $sid);
                $stmt->execute();
                $stmt->close();
            }
        }

        $_SESSION = [];

        session_unset();

        session_destroy();

        header(
            "Location: login.php?timeout=1"
        );

        exit();
    }
}


/* =========================================================
   UPDATE NORMAL USER ACTIVITY
========================================================= */

$_SESSION['last_activity'] = time();


/* =========================================================
   UPDATE USERS.LAST_ACTIVITY
========================================================= */

if (
    isset($_SESSION['user_id']) &&
    isset($conn)
) {

    $uid = intval($_SESSION['user_id']);

    $stmt = $conn->prepare(
        "UPDATE users
         SET last_activity = NOW()
         WHERE id = ?"
    );

    if ($stmt) {

        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();
    }
}

?>