<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    // Check user role and redirect accordingly
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'] ?? 'user';
        
        // Redirect based on role
        if ($user['role'] === 'admin') {
            header("Location: admin.php");
        } else {
            header("Location: dashboard.php");
        }
        exit;
    } else {
        $error = "Invalid email or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SafeHand</title>
    <link rel="stylesheet" type="text/css" href="./assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: flex;
        }

        .login-info {
            background: linear-gradient(135deg, #007bff, #00bcd4);
            color: white;
            padding: 40px;
            width: 40%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-info h1 {
            font-size: 2.5em;
            margin-bottom: 20px;
        }

        .login-info p {
            font-size: 1.1em;
            opacity: 0.9;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .login-form-container {
            padding: 40px;
            width: 60%;
        }

        .login-form-container h2 {
            color: #333;
            margin-bottom: 30px;
            font-size: 1.8em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            border-color: #007bff;
            outline: none;
        }

        .error-message {
            background: #fff3f3;
            color: #dc3545;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .forgot-password a {
            color: #007bff;
            text-decoration: none;
            font-size: 0.9em;
        }

        .login-button {
            background: #007bff;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 6px;
            width: 100%;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .login-button:hover {
            background: #0056b3;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .register-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
        }

        .back-to-home {
            position: absolute;
            top: 20px;
            left: 20px;
            color: #333;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }

            .login-info, .login-form-container {
                width: 100%;
            }

            .login-info {
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-to-home">← Back to Home</a>

    <div class="login-container">
        <div class="login-info">
            <h1>Welcome Back!</h1>
            <p>Log in to your SafeHand account to manage your lost items, search for found items, and connect with the community.</p>
        </div>

        <div class="login-form-container">
            <h2>Sign In</h2>
            
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="remember-forgot">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <div class="forgot-password">
                        <a href="reset-password.php">Forgot password?</a>
                    </div>
                </div>

                <button type="submit" class="login-button">Sign In</button>

                <div class="register-link">
                    Don't have an account? <a href="register.php">Create one now</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>