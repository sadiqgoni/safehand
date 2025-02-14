<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get admin information
$stmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['change_role'])) {
        $user_id = $_POST['user_id'];
        $new_role = $_POST['role'];
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $user_id]);
        $_SESSION['success_message'] = "User role updated successfully!";
        header("Location: admin-users.php");
        exit();
    }
    
    if (isset($_POST['deactivate_user'])) {
        $user_id = $_POST['user_id'];
        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['success_message'] = "User deactivated successfully!";
        header("Location: admin-users.php");
        exit();
    }
    
    if (isset($_POST['activate_user'])) {
        $user_id = $_POST['user_id'];
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['success_message'] = "User activated successfully!";
        header("Location: admin-users.php");
        exit();
    }
}

// Get all users with their statistics
$stmt = $pdo->query("
    SELECT u.*, 
           COUNT(DISTINCT li.id) as total_items,
           COUNT(DISTINCT CASE WHEN li.status = 'resolved' THEN li.id END) as resolved_items,
           COUNT(DISTINCT mr.id) as total_matches
    FROM users u
    LEFT JOIN lost_items li ON u.id = li.user_id
    LEFT JOIN match_requests mr ON u.id = mr.finder_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

// Get flash messages
$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - SafeHand Admin</title>
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


        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
            background: var(--background-color);
        }

        .content-section {
            background: var(--card-background);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 30px;
            margin-bottom: 40px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0,0,0,0.05);
        }

        .section-header h2 {
            font-size: 24px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .data-table th {
            font-weight: 600;
            color: var(--text-secondary);
            background: #f8fafc;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            margin-right: 5px;
        }

        .activate-btn {
            background: var(--success-color);
            color: white;
        }

        .deactivate-btn {
            background: var(--danger-color);
            color: white;
        }

        .role-select {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            font-size: 14px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #f0fdf4;
            color: #15803d;
        }

        .status-inactive {
            background: #fef2f2;
            color: #dc2626;
        }

        .success-message {
            background: #dcfce7;
            color: #15803d;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="admin.php" class="logo">SafeHand Admin</a>
            </div>
            
            <div class="user-info">
                <div class="avatar">
                    <?php echo strtoupper(substr($admin['firstname'], 0, 1)); ?>
                </div>
                <div class="name">
                    <?php echo htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']); ?>
                </div>
            </div>

            <nav class="sidebar-menu">
                <a href="admin.php" class="menu-item">
                    <i class="fas fa-dashboard"></i>
                    Dashboard
                </a>
                <a href="admin-users.php" class="menu-item active">
                    <i class="fas fa-users"></i>
                    Users
                </a>
                <a href="admin-items.php" class="menu-item">
                    <i class="fas fa-list"></i>
                    Items
                </a>
                <a href="admin-matches.php" class="menu-item">
                    <i class="fas fa-handshake"></i>
                    Match Requests
                </a>
                <a href="logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="content-section">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-users"></i>
                        User Management
                    </h2>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Resolved</th>
                            <th>Matches</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="role" class="role-select" onchange="this.form.submit()" <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                            <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>User</option>
                                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                        <input type="hidden" name="change_role" value="1">
                                    </form>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['total_items']; ?></td>
                                <td><?php echo $user['resolved_items']; ?></td>
                                <td><?php echo $user['total_matches']; ?></td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <?php if ($user['status'] == 'active'): ?>
                                                <button type="submit" name="deactivate_user" class="action-btn deactivate-btn">
                                                    <i class="fas fa-user-times"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="activate_user" class="action-btn activate-btn">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html> 