<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dash.php");
    exit();
}


$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                header("Location: dash.php");
                exit();
            } else {
                $error = "Invalid email or password.";
            }
        } catch(PDOException $e) {
            $error = "An error occurred. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeFind - Lost & Found System</title>
    <link rel="stylesheet" type="text/css" href="./assets/css/style.css">
    <style>
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #007bff, #00bcd4);
            color: white;
            padding: 100px 0;
            text-align: center;
        }

        .hero h1 {
            font-size: 3.5em;
            margin-bottom: 20px;
        }

        .hero p {
            font-size: 1.2em;
            max-width: 600px;
            margin: 0 auto 30px;
        }

        /* Navigation */
        .main-nav {
            background: white;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5em;
            font-weight: bold;
            color: #007bff;
            text-decoration: none;
        }

        .nav-links a {
            color: #333;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 15px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .nav-links a.login {
            background: #007bff;
            color: white;
        }

        .nav-links a.register {
            border: 2px solid #007bff;
            color: #007bff;
        }

        .nav-links a:hover {
            opacity: 0.8;
        }

        /* Features Section */
        .features {
            padding: 80px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .features h2 {
            text-align: center;
            font-size: 2.5em;
            margin-bottom: 50px;
            color: #333;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }

        .feature-card img {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
        }

        .feature-card h3 {
            color: #333;
            margin-bottom: 15px;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
        }

        /* Call to Action */
        .cta {
            background: #f8f9fa;
            padding: 80px 20px;
            text-align: center;
        }

        .cta h2 {
            font-size: 2.5em;
            margin-bottom: 20px;
            color: #333;
        }

        .cta p {
            color: #666;
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .cta-btn {
            padding: 15px 30px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .cta-btn.primary {
            background: #007bff;
            color: white;
        }

        .cta-btn.secondary {
            background: white;
            color: #007bff;
            border: 2px solid #007bff;
        }

        .cta-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        /* Footer */
        footer {
            background: #333;
            color: white;
            padding: 40px 20px;
            text-align: center;
        }

        footer p {
            margin: 0;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <nav class="main-nav">
        <div class="nav-container">
            <a href="index.php" class="logo">SafeFind</a>
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#about">About</a>
                <a href="login.php" class="login">Login</a>
                <a href="register.php" class="register">Register</a>
            </div>
        </div>
    </nav>

    <section class="hero">
        <h1>Welcome to SafeFind</h1>
        <p>Your trusted platform for reporting and finding lost items. We help reunite people with their valuable possessions.</p>
    </section>

    <section class="features" id="features">
        <h2>Why Choose SafeFind?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <h3>Easy Reporting</h3>
                <p>Report lost items quickly and easily with our user-friendly interface. Include detailed descriptions and images to increase chances of recovery.</p>
            </div>
            <div class="feature-card">
                <h3>Smart Search</h3>
                <p>Our advanced search system helps you find matching items efficiently, using categories, locations, and unique identifiers.</p>
            </div>
            <div class="feature-card">
                <h3>Secure Platform</h3>
                <p>Your information is safe with us. We ensure secure communication between item finders and owners.</p>
            </div>
        </div>
    </section>

    <section class="cta">
        <h2>Ready to Find What You've Lost?</h2>
        <p>Join thousands of users who have successfully recovered their lost items through SafeFind. Start your search today!</p>
        <div class="cta-buttons">
            <a href="register.php" class="cta-btn primary">Get Started</a>
            <a href="login.php" class="cta-btn secondary">Sign In</a>
        </div>
    </section>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> SafeFind. All rights reserved.</p>
    </footer>
</body>
</html> 