<?php
// var_dump(value: $pdo);
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$item_id = $_GET['id'];

// Get item details with match request check
$stmt = $pdo->prepare("
    SELECT i.*,
           u.firstname, u.lastname, u.phone_number, u.email,
           CASE WHEN mr.id IS NOT NULL THEN 1 ELSE 0 END as has_requested_match
    FROM lost_items i
    JOIN users u ON i.user_id = u.id
    LEFT JOIN match_requests mr ON i.id = mr.item_id AND mr.finder_id = ?
    WHERE i.id = ?
");
$stmt->execute([$_SESSION['user_id'], $item_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header("Location: dashboard.php");
    exit();
}

// Check if the current user is the owner of the item
$is_owner = ($item['user_id'] == $_SESSION['user_id']);

// Get user information for the sidebar
$stmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    if ($is_owner || isset($_SESSION['is_admin'])) {
        $new_status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE lost_items SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $item_id]);
        $_SESSION['success_message'] = "Item status updated successfully!";
        header("Location: item-details.php?id=" . $item_id);
        exit();
    }
}

// Get flash messages
$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// First, let's create the match_requests table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS match_requests (
            id INT PRIMARY KEY AUTO_INCREMENT,
            item_id INT NOT NULL,
            owner_id INT NOT NULL,
            finder_id INT NOT NULL,
            message TEXT NOT NULL,
            status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (item_id) REFERENCES lost_items(id),
            FOREIGN KEY (owner_id) REFERENCES users(id),
            FOREIGN KEY (finder_id) REFERENCES users(id)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            related_id INT,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
} catch(PDOException $e) {
    // Log the error but don't show it to users
    error_log("Error creating tables: " . $e->getMessage());
}

// Check if user has already sent a match request
$has_requested_match = $item['has_requested_match'] == 1;

// Handle match request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_match'])) {
    if (!$is_owner) {
        try {
            if (!$has_requested_match) {
                // Create match request
                $stmt = $pdo->prepare("INSERT INTO match_requests (item_id, owner_id, finder_id, message) VALUES (?, ?, ?, ?)");
                $stmt->execute([$item_id, $item['user_id'], $_SESSION['user_id'], $_POST['match_message']]);
                
                // Create notification for item owner
                $notification_message = "New match request for your item: " . $item['title'];
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'match_request', ?, ?)");
                $stmt->execute([$item['user_id'], $notification_message, $item_id]);
                
                $_SESSION['success_message'] = "Match request sent successfully! The owner will be notified.";
            } else {
                $_SESSION['error_message'] = "You have already sent a match request for this item.";
            }
            header("Location: item-details.php?id=" . $item_id);
            exit();
        } catch(PDOException $e) {
            $_SESSION['error_message'] = "Failed to send match request. Please try again.";
            header("Location: item-details.php?id=" . $item_id);
            exit();
        }
    }
}

// Get match requests for the item owner
$match_requests = [];
if ($is_owner) {
    try {
        $stmt = $pdo->prepare("
            SELECT mr.*, u.firstname, u.lastname, u.email, u.phone_number
            FROM match_requests mr
            JOIN users u ON mr.finder_id = u.id
            WHERE mr.item_id = ? AND mr.status = 'pending'
            ORDER BY mr.created_at DESC
        ");
        $stmt->execute([$item_id]);
        $match_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // Table might not exist yet, that's okay
        $match_requests = [];
    }
}

// Handle match request actions (accept/reject)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($is_owner || isset($_SESSION['is_admin']))) {
    if (isset($_POST['accept_match']) || isset($_POST['reject_match'])) {
        $request_id = $_POST['request_id'];
        $new_status = isset($_POST['accept_match']) ? 'accepted' : 'rejected';
        
        try {
            // Update match request status
            $stmt = $pdo->prepare("UPDATE match_requests SET status = ? WHERE id = ? AND item_id = ?");
            $stmt->execute([$new_status, $request_id, $item_id]);
            
            if ($new_status === 'accepted') {
                // Update item status to found
                $stmt = $pdo->prepare("UPDATE lost_items SET status = 'found' WHERE id = ?");
                $stmt->execute([$item_id]);
                
                // Get finder info for notification
                $stmt = $pdo->prepare("
                    SELECT u.id, mr.finder_id, u.firstname, u.lastname 
                    FROM match_requests mr
                    JOIN users u ON mr.finder_id = u.id
                    WHERE mr.id = ?
                ");
                $stmt->execute([$request_id]);
                $finder = $stmt->fetch();
                
                // Create notification for finder
                $notification_message = "Your match request for item '{$item['title']}' has been accepted!";
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'match_accepted', ?, ?)");
                $stmt->execute([$finder['finder_id'], $notification_message, $item_id]);
            }
            
            $_SESSION['success_message'] = "Match request " . ($new_status === 'accepted' ? 'accepted' : 'rejected') . " successfully!";
        } catch(PDOException $e) {
            $_SESSION['error_message'] = "Failed to process match request. Please try again.";
        }
        
        header("Location: item-details.php?id=" . $item_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['title']); ?> - SafeHand</title>
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

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 30px;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: var(--primary-color);
        }

        .item-details {
            background: var(--card-background);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .item-header {
            padding: 30px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            position: relative;
        }

        .item-header h1 {
            font-size: 28px;
            margin-bottom: 15px;
        }

        .item-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 12px;
        }

        .status-badge.lost {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-badge.found {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-badge.resolved {
            background: #dbeafe;
            color: #2563eb;
        }

        .item-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            padding: 30px;
        }

        .item-image {
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .item-image img {
            width: 100%;
            height: auto;
            display: block;
        }

        .info-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .info-section h2 {
            font-size: 18px;
            color: var(--text-primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-section h2 i {
            color: var(--accent-color);
        }

        .info-grid {
            display: grid;
            gap: 15px;
        }

        .info-item {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 15px;
            align-items: baseline;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .info-value {
            color: var(--text-primary);
            font-size: 15px;
        }

        .reporter-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .reporter-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .reporter-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 600;
        }

        .reporter-info h3 {
            font-size: 16px;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .reporter-info p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .status-form {
            margin-top: 20px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
        }

        .status-form select {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .status-form select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .update-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            justify-content: center;
        }

        .update-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        .success-message {
            background: #dcfce7;
            color: #16a34a;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            border: 1px solid #86efac;
        }

        @media (max-width: 1024px) {
            .item-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .item-header {
                padding: 20px;
            }

            .item-content {
                padding: 20px;
            }

            .info-item {
                grid-template-columns: 1fr;
                gap: 5px;
            }
        }

        /* Match Request Styles */
        .match-request-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .match-request-form {
            margin-top: 15px;
        }

        .match-message {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            margin-bottom: 15px;
            min-height: 100px;
            resize: vertical;
        }

        .match-requests-list {
            margin-top: 20px;
        }

        .match-request-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .finder-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .finder-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 600;
        }

        .finder-details h4 {
            font-size: 16px;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .finder-details p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .match-message-content {
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 8px;
            font-size: 14px;
            color: var(--text-primary);
        }

        .match-actions {
            display: flex;
            gap: 10px;
        }

        .match-btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .accept-btn {
            background: var(--success-color);
            color: white;
            border: none;
        }

        .reject-btn {
            background: var(--danger-color);
            color: white;
            border: none;
        }

        .contact-info {
            margin-top: 15px;
            padding: 15px;
            background: #f0fdf4;
            border-radius: 8px;
            border: 1px solid #86efac;
        }

        .contact-info h4 {
            color: #16a34a;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .contact-info p {
            color: var(--text-primary);
            font-size: 14px;
            margin: 5px 0;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
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
                <a href="profile.php" class="menu-item">
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
            <a href="dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>

            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="item-details">
                <div class="item-header">
                    <h1><?php echo htmlspecialchars($item['title']); ?></h1>
                    <div class="item-status">
                        <i class="fas fa-circle"></i>
                        <span class="status-badge <?php echo $item['status']; ?>">
                            <?php echo ucfirst($item['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="item-content">
                    <div class="main-info">
                        <?php if ($item['image_path']): ?>
                            <div class="item-image">
                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item Image">
                            </div>
                        <?php endif; ?>

                        <div class="info-section">
                            <h2>
                                <i class="fas fa-info-circle"></i>
                                Item Details
                            </h2>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Category</div>
                                    <div class="info-value"><?php echo htmlspecialchars($item['category']); ?></div>
                                </div>

                                <?php if ($item['unique_identifier']): ?>
                                    <div class="info-item">
                                        <div class="info-label">
                                            <?php echo $item['category'] == 'Phone' ? 'IMEI Number' : 'VIN Number'; ?>
                                        </div>
                                        <div class="info-value"><?php echo htmlspecialchars($item['unique_identifier']); ?></div>
                                    </div>
                                <?php endif; ?>

                                <div class="info-item">
                                    <div class="info-label">Location</div>
                                    <div class="info-value"><?php echo htmlspecialchars($item['location']); ?></div>
                                </div>

                                <div class="info-item">
                                    <div class="info-label">Date Lost</div>
                                    <div class="info-value"><?php echo date('F j, Y', strtotime($item['date_lost'])); ?></div>
                                </div>

                                <div class="info-item">
                                    <div class="info-label">Description</div>
                                    <div class="info-value"><?php echo nl2br(htmlspecialchars($item['description'])); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sidebar-info">
                        <div class="reporter-card">
                            <div class="reporter-header">
                                <div class="reporter-avatar">
                                    <?php echo strtoupper(substr($item['firstname'], 0, 1)); ?>
                                </div>
                                <div class="reporter-info">
                                    <h3>Reported by</h3>
                                    <p><?php echo htmlspecialchars($item['firstname'] . ' ' . $item['lastname']); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($is_owner || $item['status'] === 'resolved'): ?>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <div class="info-label">Contact</div>
                                        <div class="info-value">
                                            <?php echo htmlspecialchars($item['phone_number']); ?><br>
                                            <?php echo htmlspecialchars($item['email']); ?>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Date Reported</div>
                                        <div class="info-value"><?php echo date('F j, Y', strtotime($item['created_at'])); ?></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="info-item">
                                    <div class="info-label">Date Reported</div>
                                    <div class="info-value"><?php echo date('F j, Y', strtotime($item['created_at'])); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($is_owner || isset($_SESSION['is_admin'])): ?>
                                <form method="POST" class="status-form">
                                    <select name="status" required>
                                        <option value="lost" <?php echo $item['status'] == 'lost' ? 'selected' : ''; ?>>Lost</option>
                                        <option value="found" <?php echo $item['status'] == 'found' ? 'selected' : ''; ?>>Found</option>
                                        <option value="resolved" <?php echo $item['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    </select>
                                    <button type="submit" name="update_status" class="update-btn">
                                        <i class="fas fa-sync-alt"></i>
                                        Update Status
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (!$is_owner && $item['status'] !== 'resolved'): ?>
                                <div class="match-request-section">
                                    <h3>Found This Item?</h3>
                                    <?php if ($item['has_requested_match']): ?>
                                        <p>You have already sent a match request for this item.</p>
                                    <?php else: ?>
                                        <form method="POST" class="match-request-form">
                                            <textarea name="match_message" class="match-message" placeholder="Describe how you found this item and provide any additional details that might help verify your claim..." required></textarea>
                                            <button type="submit" name="request_match" class="update-btn">
                                                <i class="fas fa-handshake"></i>
                                                Send Match Request
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($is_owner && !empty($match_requests)): ?>
                                <div class="match-requests-list">
                                    <h3>Match Requests</h3>
                                    <?php foreach ($match_requests as $request): ?>
                                        <div class="match-request-card">
                                            <div class="finder-info">
                                                <div class="finder-avatar">
                                                    <?php echo strtoupper(substr($request['firstname'], 0, 1)); ?>
                                                </div>
                                                <div class="finder-details">
                                                    <h4><?php echo htmlspecialchars($request['firstname'] . ' ' . $request['lastname']); ?></h4>
                                                    <p>Requested <?php echo date('M d, Y', strtotime($request['created_at'])); ?></p>
                                                </div>
                                            </div>
                                            <div class="match-message-content">
                                                <?php echo nl2br(htmlspecialchars($request['message'])); ?>
                                            </div>
                                            <div class="contact-info">
                                                <h4>Contact Information</h4>
                                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($request['phone_number']); ?></p>
                                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($request['email']); ?></p>
                                            </div>
                                            <div class="match-actions">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                    <button type="submit" name="accept_match" class="match-btn accept-btn">
                                                        <i class="fas fa-check"></i> Accept Match
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                    <button type="submit" name="reject_match" class="match-btn reject-btn">
                                                        <i class="fas fa-times"></i> Reject Match
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html> 