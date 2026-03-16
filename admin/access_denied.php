<?php
require_once "../includes/session.php";
require_once "../includes/security_headers.php";

http_response_code(403);

// Get role safely
$role = $_SESSION['role'] ?? 'Unknown';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Access Denied</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            font-family: 'Poppins', sans-serif;
            overflow: hidden;
        }

        .card {
            border-radius: 15px;
            animation: fadeIn 0.6s ease-in-out;
        }

        .error-code {
            font-size: 90px;
            font-weight: 700;
            color: #dc3545;
            animation: pulse 1.5s infinite;
        }

        .role-badge {
            font-size: 14px;
            padding: 6px 12px;
            border-radius: 20px;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .countdown {
            font-size: 14px;
            color: #6c757d;
        }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow text-center p-5" style="max-width: 500px;">

        <div class="error-code">403</div>

        <h4 class="mb-3">Access Denied</h4>

        <p class="text-muted">
            You do not have permission to access this page.
        </p>

        <!-- Role Display -->
        <div class="mb-3">
            <span class="badge bg-secondary role-badge">
                Logged in as: <?= htmlspecialchars($role) ?>
            </span>
        </div>

        <div class="mt-3">
            <a href="admin_dashboard.php" class="btn btn-primary me-2">
                Go to Dashboard
            </a>
            <a href="logout.php" class="btn btn-outline-danger">
                Logout
            </a>
        </div>

        <!-- Auto redirect countdown -->
        <div class="countdown mt-3">
            Redirecting to dashboard in <span id="timer">5</span> seconds...
        </div>

    </div>
</div>

<script>
    let timeLeft = 5;
    const timer = document.getElementById('timer');

    const countdown = setInterval(() => {
        timeLeft--;
        timer.textContent = timeLeft;

        if (timeLeft <= 0) {
            clearInterval(countdown);
            window.location.href = "admin_dashboard.php";
        }
    }, 1000);
</script>

</body>
</html>