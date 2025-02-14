<?php
session_start();
require_once 'config/database.php';

// Secret token that only administrators should know
define('ADMIN_SECRET_TOKEN', 'safehand');

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify the secret token
    if (!isset($_POST['secret_token']) || $_POST['secret_token'] !== ADMIN_SECRET_TOKEN) {
        $error = "Invalid secret token. Admin registration not allowed.";
    } else {
        $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
        $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validation
        if (empty($firstname) || empty($lastname) || empty($email) || empty($password)) {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            try {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Email already registered.";
                } else {
                    // Create admin account
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (firstname, lastname, email, password, role) VALUES (?, ?, ?, ?, 'admin')");
                    $stmt->execute([$firstname, $lastname, $email, $password_hash]);
                    
                    $success = "Admin account created successfully! You can now login.";
                }
            } catch(PDOException $e) {
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration - SafeHand</title>
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

        .register-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
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
            border-color: #4f46e5;
            outline: none;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }

        .success-message {
            background: #dcfce7;
            color: #15803d;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }

        .register-button {
            background: #4f46e5;
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

        .register-button:hover {
            background: #4338ca;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .login-link a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Admin Registration</h1>
            <p>Create a new administrator account</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="firstname">First Name</label>
                <input type="text" id="firstname" name="firstname" required>
            </div>

            <div class="form-group">
                <label for="lastname">Last Name</label>
                <input type="text" id="lastname" name="lastname" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="form-group">
                <label for="secret_token">Admin Secret Token</label>
                <input type="password" id="secret_token" name="secret_token" required>
            </div>

            <button type="submit" class="register-button">Create Admin Account</button>

            <div class="login-link">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </form>
    </div>
</body>
</html> 