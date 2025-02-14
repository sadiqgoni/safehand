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

// Get statistics
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_items' => $pdo->query("SELECT COUNT(*) FROM lost_items")->fetchColumn(),
    'pending_matches' => $pdo->query("SELECT COUNT(*) FROM match_requests WHERE status = 'pending'")->fetchColumn(),
    'resolved_items' => $pdo->query("SELECT COUNT(*) FROM lost_items WHERE status = 'resolved'")->fetchColumn()
];

// Get recent items
$stmt = $pdo->query("
    SELECT i.*, u.firstname, u.lastname 
    FROM lost_items i 
    JOIN users u ON i.user_id = u.id 
    ORDER BY i.created_at DESC 
    LIMIT 10
");
$recent_items = $stmt->fetchAll();

// Get pending match requests
$stmt = $pdo->query("
    SELECT mr.*, i.title as item_title, 
           u1.firstname as owner_firstname, u1.lastname as owner_lastname,
           u2.firstname as finder_firstname, u2.lastname as finder_lastname
    FROM match_requests mr
    JOIN lost_items i ON mr.item_id = i.id
    JOIN users u1 ON mr.owner_id = u1.id
    JOIN users u2 ON mr.finder_id = u2.id
    WHERE mr.status = 'pending'
    ORDER BY mr.created_at DESC
    LIMIT 10
");
$pending_matches = $stmt->fetchAll();

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['verify_item'])) {
        $item_id = $_POST['item_id'];
        $stmt = $pdo->prepare("UPDATE lost_items SET verified = 1 WHERE id = ?");
        $stmt->execute([$item_id]);
        $_SESSION['success_message'] = "Item verified successfully!";
        header("Location: admin.php");
        exit();
    }
    
    if (isset($_POST['delete_item'])) {
        $item_id = $_POST['item_id'];
        $stmt = $pdo->prepare("DELETE FROM lost_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $_SESSION['success_message'] = "Item deleted successfully!";
        header("Location: admin.php");
        exit();
    }
}

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
    <title>Admin Dashboard - SafeHand</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--card-background);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
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
        }

        .verify-btn {
            background: var(--success-color);
            color: white;
        }

        .delete-btn {
            background: var(--danger-color);
            color: white;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: #fff7ed;
            color: #c2410c;
        }

        .status-verified {
            background: #f0fdf4;
            color: #15803d;
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
                <a href="admin.php" class="menu-item active">
                    <i class="fas fa-dashboard"></i>
                    Dashboard
                </a>
                <a href="admin-users.php" class="menu-item">
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
            <div class="page-header">
                <h1>Admin Dashboard</h1>
            </div>

            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-users"></i>
                        Total Users
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_items']; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-list"></i>
                        Total Items
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['pending_matches']; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-handshake"></i>
                        Pending Matches
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['resolved_items']; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-check-circle"></i>
                        Resolved Items
                    </div>
                </div>
            </div>

            <!-- Recent Items -->
            <div class="content-section">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-list"></i>
                        Recent Items
                    </h2>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Reporter</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['title']); ?></td>
                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                <td><?php echo htmlspecialchars($item['firstname'] . ' ' . $item['lastname']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $item['status']; ?>">
                                        <?php echo ucfirst($item['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="verify_item" class="action-btn verify-btn">
                                            <i class="fas fa-check"></i> Verify
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="delete_item" class="action-btn delete-btn">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pending Match Requests -->
            <div class="content-section">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-handshake"></i>
                        Pending Match Requests
                    </h2>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Owner</th>
                            <th>Finder</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_matches as $match): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($match['item_title']); ?></td>
                                <td><?php echo htmlspecialchars($match['owner_firstname'] . ' ' . $match['owner_lastname']); ?></td>
                                <td><?php echo htmlspecialchars($match['finder_firstname'] . ' ' . $match['finder_lastname']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($match['created_at'])); ?></td>
                                <td>
                                    <a href="item-details.php?id=<?php echo $match['item_id']; ?>" class="action-btn verify-btn">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
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