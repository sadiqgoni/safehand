<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
    $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate current password if trying to change password
    if (!empty($new_password) || !empty($confirm_password)) {
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters long";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
    }
    
    if (empty($errors)) {
        try {
            if (!empty($new_password)) {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET firstname = ?, lastname = ?, phone_number = ?, password = ? WHERE id = ?");
                $stmt->execute([$firstname, $lastname, $phone, $password_hash, $_SESSION['user_id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET firstname = ?, lastname = ?, phone_number = ? WHERE id = ?");
                $stmt->execute([$firstname, $lastname, $phone, $_SESSION['user_id']]);
            }
            
            $_SESSION['success_message'] = "Profile updated successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch(PDOException $e) {
            $errors[] = "Failed to update profile. Please try again.";
        }
    }
}

// Get user statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_items,
    SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost_items,
    SUM(CASE WHEN status = 'found' THEN 1 ELSE 0 END) as found_items,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_items
    FROM lost_items WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

// Get flash messages
$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - SafeHand</title>
    <link rel="stylesheet" type="text/css" href="./assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
         :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --secondary-color: #06b6d4;
            --accent-color: #f97316;
            --success-color: #22c55e;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --background-color: #f8fafc;
            --card-background: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
        }

        body {
            background: var(--background-color);
            color: var(--text-primary);
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            color: white;
        }

        .sidebar-header {
            padding: 25px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header .logo {
            color: white;
            font-size: 28px;
            font-weight: bold;
            text-decoration: none;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .user-info {
            padding: 25px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
        }

        .user-info .avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border: 3px solid rgba(255,255,255,0.2);
        }

        .menu-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .menu-item i {
            width: 24px;
            margin-right: 15px;
            font-size: 18px;
        }

        .menu-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--accent-color);
        }

        .menu-item.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left-color: var(--accent-color);
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }

        /* Reuse sidebar styles */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
        }

        .profile-header {
            margin-bottom: 40px;
        }

        .profile-header h1 {
            font-size: 32px;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .profile-header p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }

        /* Profile Card */
        .profile-card {
            background: var(--card-background);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 30px;
            text-align: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            margin: 0 auto 20px;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .profile-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .profile-email {
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid rgba(0,0,0,0.05);
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Profile Form */
        .profile-form-container {
            background: var(--card-background);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 30px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h2 {
            font-size: 20px;
            color: var(--text-primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section h2 i {
            color: var(--accent-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
        }

        .password-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid rgba(0,0,0,0.05);
        }

        .update-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .update-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        /* Messages */
        .success-message, .error-message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .success-message {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #86efac;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        @media (max-width: 1024px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="logo">SafeHand</a>
            </div>
            
            <div class="user-info">
                <div class="avatar">
                    <?php echo strtoupper(substr($user['firstname'], 0, 1)); ?>
                </div>
                <div class="name">
                    <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                </div>
            </div>

            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="search.php" class="menu-item">
                    <i class="fas fa-search"></i>
                    Search Items
                </a>
                <a href="profile.php" class="menu-item active">
                    <i class="fas fa-user"></i>
                    Profile
                </a>
            
                <a href="logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="profile-header">
                <h1>Profile Settings</h1>
                <p>Manage your account information and preferences</p>
            </div>

            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($errors[0]); ?>
                </div>
            <?php endif; ?>

            <div class="profile-grid">
                <!-- Profile Card -->
                <div class="profile-card">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['firstname'], 0, 1)); ?>
                    </div>
                    <div class="profile-name">
                        <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                    </div>
                    <div class="profile-email">
                        <?php echo htmlspecialchars($user['email']); ?>
                    </div>
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['total_items']; ?></div>
                            <div class="stat-label">Total Items</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['found_items']; ?></div>
                            <div class="stat-label">Found Items</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['lost_items']; ?></div>
                            <div class="stat-label">Lost Items</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['resolved_items']; ?></div>
                            <div class="stat-label">Resolved</div>
                        </div>
                    </div>
                </div>

                <!-- Profile Form -->
                <div class="profile-form-container">
                    <form method="POST" action="">
                        <div class="form-section">
                            <h2>
                                <i class="fas fa-user-circle"></i>
                                Personal Information
                            </h2>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="firstname">First Name</label>
                                    <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="lastname">Last Name</label>
                                    <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone_number']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            </div>
                        </div>

                        <div class="form-section password-section">
                            <h2>
                                <i class="fas fa-lock"></i>
                                Change Password
                            </h2>
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password">
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password">
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="update_profile" class="update-btn">
                            <i class="fas fa-save"></i>
                            Update Profile
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html> 