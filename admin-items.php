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

// Handle item actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['verify_item'])) {
        $item_id = $_POST['item_id'];
        $stmt = $pdo->prepare("UPDATE lost_items SET verified = 1 WHERE id = ?");
        $stmt->execute([$item_id]);
        $_SESSION['success_message'] = "Item verified successfully!";
        header("Location: admin-items.php");
        exit();
    }
    
    if (isset($_POST['delete_item'])) {
        $item_id = $_POST['item_id'];
        // Delete related match requests first
        $stmt = $pdo->prepare("DELETE FROM match_requests WHERE item_id = ?");
        $stmt->execute([$item_id]);
        // Then delete the item
        $stmt = $pdo->prepare("DELETE FROM lost_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $_SESSION['success_message'] = "Item and related match requests deleted successfully!";
        header("Location: admin-items.php");
        exit();
    }
    
    if (isset($_POST['update_status'])) {
        $item_id = $_POST['item_id'];
        $new_status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE lost_items SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $item_id]);
        $_SESSION['success_message'] = "Item status updated successfully!";
        header("Location: admin-items.php");
        exit();
    }
}

// Get all items with reporter information
$stmt = $pdo->query("
    SELECT i.*, u.firstname, u.lastname, u.email,
           COUNT(DISTINCT mr.id) as match_requests,
           COUNT(DISTINCT CASE WHEN mr.status = 'accepted' THEN mr.id END) as accepted_matches
    FROM lost_items i
    JOIN users u ON i.user_id = u.id
    LEFT JOIN match_requests mr ON i.id = mr.item_id
    GROUP BY i.id
    ORDER BY i.created_at DESC
");
$items = $stmt->fetchAll();

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
    <title>Item Management - SafeHand Admin</title>
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

        .verify-btn {
            background: var(--success-color);
            color: white;
        }

        .delete-btn {
            background: var(--danger-color);
            color: white;
        }

        .status-select {
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

        .status-lost {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-found {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-resolved {
            background: #dbeafe;
            color: #2563eb;
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

        .item-title {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .item-title:hover {
            color: var(--primary-dark);
        }

        .reporter-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .reporter-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
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
                <a href="admin-users.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    Users
                </a>
                <a href="admin-items.php" class="menu-item active">
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
                        <i class="fas fa-list"></i>
                        Item Management
                    </h2>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Reporter</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Match Requests</th>
                            <th>Date Reported</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <a href="item-details.php?id=<?php echo $item['id']; ?>" class="item-title">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="reporter-info">
                                        <div class="reporter-avatar">
                                            <?php echo strtoupper(substr($item['firstname'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <?php echo htmlspecialchars($item['firstname'] . ' ' . $item['lastname']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <select name="status" class="status-select" onchange="this.form.submit()">
                                            <option value="lost" <?php echo $item['status'] == 'lost' ? 'selected' : ''; ?>>Lost</option>
                                            <option value="found" <?php echo $item['status'] == 'found' ? 'selected' : ''; ?>>Found</option>
                                            <option value="resolved" <?php echo $item['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                                <td>
                                    <?php echo $item['match_requests']; ?> 
                                    <?php if ($item['accepted_matches'] > 0): ?>
                                        <span class="status-badge status-found">
                                            <?php echo $item['accepted_matches']; ?> Accepted
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                <td>
                                    <?php if (!$item['verified']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="verify_item" class="action-btn verify-btn">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this item? This action cannot be undone.');">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="delete_item" class="action-btn delete-btn">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
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