<?php
/**
 * Sigma SMS A2P — Test Panel Login
 * Separate login for test users
 */
session_start();

// If already logged in as test user, redirect to test dashboard
if (isset($_SESSION['test_user_id'])) {
    header('Location: test_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Test credentials
    if ($username === 'test123' && $password === 'test123') {
        session_regenerate_id(true);
        $_SESSION['test_user_id'] = 'test123';
        $_SESSION['test_username'] = 'test123';
        $_SESSION['test_login_time'] = time();
        header('Location: test_dashboard.php');
        exit;
    } else {
        $error = 'Invalid test credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Panel Login — Sigma SMS A2P</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.min.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .test-login-card {
            background: rgba(255,255,255,.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3rem 2.5rem;
            box-shadow: 0 25px 60px rgba(0,0,0,.3);
            max-width: 420px;
            width: 100%;
            animation: slideUp .6s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .test-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 30px rgba(102,126,234,.4);
        }
        .test-title {
            text-align: center;
            font-size: 1.8rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: .5rem;
        }
        .test-subtitle {
            text-align: center;
            color: #718096;
            font-size: .9rem;
            margin-bottom: 2rem;
        }
        .form-control {
            padding: .75rem 1rem;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            transition: all .2s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,.1);
        }
        .btn-test {
            width: 100%;
            padding: .75rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            transition: transform .2s;
        }
        .btn-test:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102,126,234,.4);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .test-info {
            background: #f7fafc;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: .85rem;
            color: #4a5568;
        }
    </style>
</head>
<body>
    <div class="test-login-card">
        <div class="test-icon">🧪</div>
        <h1 class="test-title">Test Panel</h1>
        <p class="test-subtitle">SMS Testing Environment</p>

        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="ri-error-warning-line me-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" name="username" placeholder="test123" required autofocus>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" placeholder="test123" required>
            </div>

            <button type="submit" class="btn btn-test">
                <i class="ri-login-circle-line me-2"></i>Enter Test Panel
            </button>
        </form>

        <div class="test-info">
            <strong><i class="ri-information-line me-1"></i>Test Credentials:</strong><br>
            Username: <code>test123</code><br>
            Password: <code>test123</code>
        </div>
    </div>
</body>
</html>
