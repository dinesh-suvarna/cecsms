<?php
require_once "../includes/session.php";
require_once "../includes/security_headers.php";
require_once "../includes/csrf.php";
require_once __DIR__ . "/../config/db.php";

/* Destroy previous session if exists */
if (isset($_SESSION["user_id"])) {
    $_SESSION = [];
    session_unset();
    session_destroy();
    session_start();
}

$error = "";

/* Timeout message */
if (isset($_GET['timeout'])) {
    $error = "Session expired due to inactivity. Please login again.";
}

/* Security message */
if (isset($_GET['security'])) {
    $error = "Your session was terminated for security reasons. Please login again.";
}

/* LOGIN PROCESS */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        die("CSRF validation failed!");
    }

    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare(
        "SELECT * FROM users WHERE username=? AND status='Active'"
    );

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password"])) {

            /* =====================================================
               SESSION CREATION
            ===================================================== */

            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["division_id"] = $user["division_id"];
            $_SESSION["institution_id"] = $user["institution_id"];

            $_SESSION["last_activity"] = time();
            $_SESSION["created"] = time();

            /* Bind session to current IP/User-Agent */
            $_SESSION["ip_address"] = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION["user_agent"] = $_SERVER['HTTP_USER_AGENT'] ?? '';

            /* =====================================================
               PREPARE LOGIN LOG DATA
            ===================================================== */

            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

            /* Detect OS */
            $os = "Unknown OS";

            if (preg_match('/windows|win32/i', $ua)) {
                $os = 'Windows';
            } elseif (preg_match('/android/i', $ua)) {
                $os = 'Android';
            } elseif (preg_match('/iphone|ipad/i', $ua)) {
                $os = 'iOS';
            } elseif (preg_match('/macintosh|mac os x/i', $ua)) {
                $os = 'macOS';
            } elseif (preg_match('/linux/i', $ua)) {
                $os = 'Linux';
            }

            /* Detect Browser */
            $browser = "Unknown Browser";

            if (preg_match('/edg/i', $ua)) {
                $browser = 'Edge';
            } elseif (
                preg_match('/brave/i', $ua) ||
                (
                    isset($_SERVER['HTTP_SEC_CH_UA']) &&
                    strpos($_SERVER['HTTP_SEC_CH_UA'], 'Brave') !== false
                )
            ) {
                $browser = 'Brave';
            } elseif (preg_match('/firefox/i', $ua)) {
                $browser = 'Firefox';
            } elseif (preg_match('/opr|opera/i', $ua)) {
                $browser = 'Opera';
            } elseif (preg_match('/chrome/i', $ua)) {
                $browser = 'Chrome';
            } elseif (preg_match('/safari/i', $ua)) {
                $browser = 'Safari';
            } elseif (preg_match('/msie|trident/i', $ua)) {
                $browser = 'IE';
            }

            /* =====================================================
               FETCH LOCATION
            ===================================================== */

            $city = "Local";
            $country = "Localhost";
            $cCode = "";

            if ($ip !== '127.0.0.1' && $ip !== '::1' && !empty($ip)) {

                $details = @json_decode(
                    file_get_contents(
                        "http://ip-api.com/json/" . urlencode($ip)
                    )
                );

                if ($details && $details->status === 'success') {

                    $city = $details->city ?? "Unknown City";
                    $country = $details->country ?? "Unknown Country";
                    $cCode = strtolower($details->countryCode ?? "");
                }
            }

            /* =====================================================
               SAVE LOGIN HISTORY
            ===================================================== */

            $log_query = "
                INSERT INTO login_logs
                (
                    user_id,
                    ip_address,
                    user_agent,
                    browser,
                    os,
                    city,
                    country,
                    country_code
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $log_stmt = $conn->prepare($log_query);

            $log_stmt->bind_param(
                "isssssss",
                $user['id'],
                $ip,
                $ua,
                $browser,
                $os,
                $city,
                $country,
                $cCode
            );
            $log_stmt->execute();
            $_SESSION["login_log_id"] = $conn->insert_id;
            $log_stmt->close();

            /* =====================================================
               REGISTER ACTIVE SESSION
            ===================================================== */

            $session_id = session_id();

            $active_query = "
                INSERT INTO active_sessions
                (
                    user_id,
                    session_id,
                    ip_address,
                    user_agent,
                    browser,
                    os,
                    last_activity
                )
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
                    ip_address = VALUES(ip_address),
                    user_agent = VALUES(user_agent),
                    browser = VALUES(browser),
                    os = VALUES(os),
                    last_activity = NOW()
            ";

            $active_stmt = $conn->prepare($active_query);

            $active_stmt->bind_param(
                "isssss",
                $user['id'],
                $session_id,
                $ip,
                $ua,
                $browser,
                $os
            );

            $active_stmt->execute();
            $active_stmt->close();

            /* =====================================================
               LOGIN SUCCESS
            ===================================================== */

            header("Location: ../index.php");
            exit();

        } else {

            $error = "Invalid Password!";
        }

    } else {

        $error = "User not found or inactive!";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <title>
        Institutional Resource Manager | Secure Login
    </title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <style>

        :root {
            --brand-primary: #6366f1;
            --brand-dark: #0f172a;
            --bg-subtle: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body {
            min-height: 100vh;
            padding: 20px;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
        }

        .login-container {
            width: 100%;
            max-width: 1100px;
            min-height: 650px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            border-radius: 24px;
        }

        .login-sidebar {
            flex: 1.1;
            background: linear-gradient(
                135deg,
                var(--brand-dark) 0%,
                #1e293b 100%
            );
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
            position: relative;
        }

        .login-sidebar::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url(
                'https://www.transparenttextures.com/patterns/cubes.png'
            );
            opacity: 0.05;
        }

        .sidebar-content {
            position: relative;
            z-index: 1;
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            padding: 15px 20px;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0,0,0,.12);
        }

        .custom-logo {
            max-width: 420px;
            width: 100%;
            height: auto;
        }

        .asset-icon-box {
            background-color: rgba(99,102,241,.15);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            flex-shrink: 0;
        }

        .asset-icon-box i {
            font-size: 1.3rem;
            color: var(--brand-primary);
        }

        .login-form-area {
            flex: 1;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: white;
        }

        .form-header h2 {
            font-weight: 700;
            letter-spacing: -.03em;
        }

        .form-label {
            font-weight: 600;
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .025em;
            color: var(--text-muted);
        }

        .input-group {
            background: var(--bg-subtle);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            transition: all .2s ease;
        }

        .input-group:focus-within {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 4px rgba(99,102,241,.1);
            background: #fff;
        }

        .form-control {
            background: transparent;
            border: none;
            padding: 14px;
            font-size: .95rem;
        }

        .form-control:focus {
            box-shadow: none;
            background: transparent;
        }

        .input-group-text {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding-left: 18px;
        }

        .btn-login {
            background: var(--brand-dark);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-weight: 600;
            margin-top: 10px;
            transition: all .2s;
        }

        .btn-login:hover {
            background: #000;
            transform: translateY(-1px);
            box-shadow: 0 10px 20px -5px rgba(0,0,0,.2);
        }

        #loginLoader {
            position: fixed;
            inset: 0;
            background: rgba(255,255,255,.85);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        @media(max-width:992px) {

            .login-container {
                flex-direction: column;
                min-height: auto;
                max-height: none;
            }

            .login-sidebar {
                display: none;
            }

            .login-form-area {
                padding: 30px;
            }

            .custom-logo {
                max-width: 300px;
                margin: 0 auto;
            }

            .brand-logo {
                text-align: center;
            }
        }

        @media(max-width:576px) {

            body {
                padding: 10px;
            }

            .login-form-area {
                padding: 20px;
            }

            .custom-logo {
                max-width: 220px;
            }
        }

    </style>

</head>

<body>

<div id="loginLoader">

    <div class="text-center">

        <div
            class="spinner-border text-primary"
            style="width:3rem;height:3rem;"
        ></div>

        <p class="mt-3 fw-semibold text-dark">
            Accessing Institutional Portal...
        </p>

    </div>

</div>

<div class="login-container">

    <div class="login-sidebar">

        <div class="sidebar-content">

            <div class="brand-logo">

                <img
                    src="../admin/assets/logoc.png"
                    alt="Institution Logo"
                    class="custom-logo"
                >

            </div>

            <h2 class="display-6 fw-bold">

                Institutional <br>

                <span style="color:var(--brand-primary)">
                    Resource Manager.
                </span>

            </h2>

            <p class="mt-3 opacity-75 lh-lg">
                The centralized framework for tracking high-value assets
                across campus departments—from computing labs to electrical
                infrastructure.
            </p>

            <div class="mt-5">

                <div class="d-flex align-items-start mb-4">

                    <div class="asset-icon-box me-3">
                        <i class="bi bi-pc-display"></i>
                    </div>

                    <div>

                        <h6 class="mb-0 fw-bold text-white">
                            IT Infrastructure
                        </h6>

                        <small class="text-white-50">
                            Computer labs, workstations, networking devices,
                            and servers under inventory.
                        </small>

                    </div>

                </div>

                <div class="d-flex align-items-start mb-4">

                    <div class="asset-icon-box me-3">
                        <i class="bi bi-layout-text-window-reverse"></i>
                    </div>

                    <div>

                        <h6 class="mb-0 fw-bold text-white">
                            Furniture & Fixtures
                        </h6>

                        <small class="text-white-50">
                            Furniture assets in computer labs, classrooms,
                            and office spaces.
                        </small>

                    </div>

                </div>

                <div class="d-flex align-items-start mb-4">

                    <div class="asset-icon-box me-3">
                        <i class="bi bi-plugin"></i>
                    </div>

                    <div>

                        <h6 class="mb-0 fw-bold text-white">
                            Electrical Systems
                        </h6>

                        <small class="text-white-50">
                            Fans and lighting equipment recorded as
                            electrical assets.
                        </small>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <div class="login-form-area">

        <div class="form-header mb-4">

            <h2>Portal Login</h2>

            <p>
                Secure access for authorized administrative staff.
            </p>

        </div>

        <?php if($error): ?>

            <div
                class="alert alert-danger d-flex align-items-center mb-4 py-3"
                style="
                    border-radius:12px;
                    border:none;
                    background:#fee2e2;
                    color:#b91c1c;
                "
            >

                <i class="bi bi-shield-lock-fill me-2"></i>

                <div class="fw-medium">
                    <?= htmlspecialchars($error) ?>
                </div>

            </div>

        <?php endif; ?>

        <form method="POST" id="loginForm">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= csrf_token() ?>"
            >

            <div class="mb-3">

                <label class="form-label">
                    Admin Username
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-person-circle"></i>
                    </span>

                    <input
                        type="text"
                        name="username"
                        class="form-control"
                        placeholder="Institutional ID"
                        required
                        autofocus
                    >

                </div>

            </div>

            <div class="mb-4">

                <div class="d-flex justify-content-between">

                    <label class="form-label">
                        Password
                    </label>

                </div>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-key-fill"></i>
                    </span>

                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control"
                        placeholder="••••••••"
                        required
                    >

                    <span
                        class="input-group-text"
                        id="togglePassword"
                        style="cursor:pointer"
                    >
                        <i class="bi bi-eye"></i>
                    </span>

                </div>

            </div>

            <button
                type="submit"
                id="loginBtn"
                class="btn btn-login w-100"
            >

                <span id="btnText">
                    Log In to Dashboard
                </span>

            </button>

        </form>

        <footer class="mt-auto pt-5 text-center">

            <p
                class="text-muted mb-0"
                style="
                    font-size:.75rem;
                    letter-spacing:.05em;
                "
            >
                &copy; <?= date("Y") ?>
                CECSMS - INSTITUTIONAL ASSET MANAGEMENT
            </p>

        </footer>

    </div>

</div>

<script>

const form = document.getElementById("loginForm");
const loader = document.getElementById("loginLoader");
const btnText = document.getElementById("btnText");
const loginBtn = document.getElementById("loginBtn");

form.addEventListener("submit", function() {

    loader.style.display = "flex";

    loginBtn.disabled = true;

    btnText.innerHTML = "Validating...";

});

const toggle = document.getElementById("togglePassword");
const password = document.getElementById("password");

toggle.addEventListener("click", function() {

    const type =
        password.type === "password"
            ? "text"
            : "password";

    password.type = type;

    this.innerHTML =
        type === "password"
            ? '<i class="bi bi-eye"></i>'
            : '<i class="bi bi-eye-slash"></i>';

});

</script>

</body>
</html>