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

// Handle match request actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['verify_match'])) {
        $match_id = $_POST['match_id'];
        $stmt = $pdo->prepare("UPDATE match_requests SET admin_verified = 1 WHERE id = ?");
        $stmt->execute([$match_id]);
        $_SESSION['success_message'] = "Match request verified successfully!";
        header("Location: admin-matches.php");
        exit();
    }
    
    if (isset($_POST['delete_match'])) {
        $match_id = $_POST['match_id'];
        $stmt = $pdo->prepare("DELETE FROM match_requests WHERE id = ?");
        $stmt->execute([$match_id]);
        $_SESSION['success_message'] = "Match request deleted successfully!";
        header("Location: admin-matches.php");
        exit();
    }
    
    if (isset($_POST['update_status'])) {
        $match_id = $_POST['match_id'];
        $new_status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE match_requests SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $match_id]);
        
        // If match is accepted, update item status to found
        if ($new_status === 'accepted') {
            $stmt = $pdo->prepare("
                UPDATE lost_items li 
                JOIN match_requests mr ON li.id = mr.item_id 
                SET li.status = 'found' 
                WHERE mr.id = ?
            ");
            $stmt->execute([$match_id]);
        }
        
        $_SESSION['success_message'] = "Match request status updated successfully!";
        header("Location: admin-matches.php");
        exit();
    }
}

// Get all match requests with related information
$stmt = $pdo->query("
    SELECT mr.*, 
           i.title as item_title, i.category, i.status as item_status,
           u1.firstname as owner_firstname, u1.lastname as owner_lastname,
           u2.firstname as finder_firstname, u2.lastname as finder_lastname
    FROM match_requests mr
    JOIN lost_items i ON mr.item_id = i.id
    JOIN users u1 ON mr.owner_id = u1.id
    JOIN users u2 ON mr.finder_id = u2.id
    ORDER BY mr.created_at DESC
");
$matches = $stmt->fetchAll();

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
    <title>Match Requests - SafeHand Admin</title>
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

        .match-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .match-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .match-title {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 18px;
            transition: color 0.3s ease;
        }

        .match-title:hover {
            color: var(--primary-dark);
        }

        .match-status {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-pending {
            background: #fff7ed;
            color: #c2410c;
        }

        .status-accepted {
            background: #f0fdf4;
            color: #15803d;
        }

        .status-rejected {
            background: #fef2f2;
            color: #dc2626;
        }

        .match-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-group h3 {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 600;
        }

        .user-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .match-message {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--text-primary);
        }

        .match-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .action-btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            font-size: 14px;
            margin-right: 10px;
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
                <a href="admin-users.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    Users
                </a>
                <a href="admin-items.php" class="menu-item">
                    <i class="fas fa-list"></i>
                    Items
                </a>
                <a href="admin-matches.php" class="menu-item active">
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
                        <i class="fas fa-handshake"></i>
                        Match Requests
                    </h2>
                </div>

                <?php foreach ($matches as $match): ?>
                    <div class="match-card">
                        <div class="match-header">
                            <a href="item-details.php?id=<?php echo $match['item_id']; ?>" class="match-title">
                                <?php echo htmlspecialchars($match['item_title']); ?>
                                <span style="font-size: 14px; color: var(--text-secondary);">
                                    (<?php echo htmlspecialchars($match['category']); ?>)
                                </span>
                            </a>
                            <span class="match-status status-<?php echo $match['status']; ?>">
                                <?php echo ucfirst($match['status']); ?>
                            </span>
                        </div>

                        <div class="match-details">
                            <div class="detail-group">
                                <h3>Item Owner</h3>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($match['owner_firstname'], 0, 1)); ?>
                                    </div>
                                    <span class="user-name">
                                        <?php echo htmlspecialchars($match['owner_firstname'] . ' ' . $match['owner_lastname']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="detail-group">
                                <h3>Finder</h3>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($match['finder_firstname'], 0, 1)); ?>
                                    </div>
                                    <span class="user-name">
                                        <?php echo htmlspecialchars($match['finder_firstname'] . ' ' . $match['finder_lastname']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="detail-group">
                                <h3>Request Date</h3>
                                <div class="user-name">
                                    <?php echo date('M d, Y H:i', strtotime($match['created_at'])); ?>
                                </div>
                            </div>

                            <div class="detail-group">
                                <h3>Item Status</h3>
                                <span class="match-status status-<?php echo $match['item_status']; ?>">
                                    <?php echo ucfirst($match['item_status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="match-message">
                            <h3 style="margin-bottom: 8px;">Finder's Message:</h3>
                            <?php echo nl2br(htmlspecialchars($match['message'])); ?>
                        </div>

                        <div class="match-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                <select name="status" class="status-select" <?php echo $match['status'] !== 'pending' ? 'disabled' : ''; ?>>
                                    <option value="pending" <?php echo $match['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="accepted" <?php echo $match['status'] == 'accepted' ? 'selected' : ''; ?>>Accept</option>
                                    <option value="rejected" <?php echo $match['status'] == 'rejected' ? 'selected' : ''; ?>>Reject</option>
                                </select>
                                <button type="submit" name="update_status" class="action-btn verify-btn" <?php echo $match['status'] !== 'pending' ? 'disabled' : ''; ?>>
                                    <i class="fas fa-save"></i>
                                    Update Status
                                </button>
                            </form>

                            <?php if (!$match['admin_verified']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                    <button type="submit" name="verify_match" class="action-btn verify-btn">
                                        <i class="fas fa-check"></i>
                                        Verify
                                    </button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this match request? This action cannot be undone.');">
                                <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                <button type="submit" name="delete_match" class="action-btn delete-btn">
                                    <i class="fas fa-trash"></i>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</body>
</html> 