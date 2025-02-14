<?php
var_dump($pdo);
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

// Get item details
$stmt = $pdo->prepare("
    SELECT i.*, u.firstname, u.lastname, u.email 
    FROM lost_items i 
    JOIN users u ON i.user_id = u.id 
    WHERE i.id = ?
");
$stmt->execute([$item_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header("Location: dashboard.php");
    exit();
}

// Check if the current user is the owner of the item
$is_owner = ($item['user_id'] == $_SESSION['user_id']);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    if ($is_owner || isset($_SESSION['is_admin'])) {
        $new_status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE lost_items SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $item_id]);
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
    <title>Item Details - SafeFind</title>
    <link rel="stylesheet" type="text/css" href="./assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <!-- Include your navigation here -->
        </nav>

        <div class="dashboard-content">
            <div class="item-details">
                <h1><?php echo htmlspecialchars($item['title']); ?></h1>
                
                <div class="item-status">
                    Status: <span class="status <?php echo $item['status']; ?>">
                        <?php echo ucfirst($item['status']); ?>
                    </span>
                </div>

                <?php if ($item['image_path']): ?>
                    <div class="item-image">
                        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item Image">
                    </div>
                <?php endif; ?>

                <div class="item-info">
                    <h2>Item Details</h2>
                    <dl>
                        <dt>Category:</dt>
                        <dd><?php echo htmlspecialchars($item['category']); ?></dd>

                        <?php if ($item['unique_identifier']): ?>
                            <dt><?php echo $item['category'] == 'Phone' ? 'IMEI Number:' : 'VIN Number:'; ?></dt>
                            <dd><?php echo htmlspecialchars($item['unique_identifier']); ?></dd>
                        <?php endif; ?>

                        <dt>Location:</dt>
                        <dd><?php echo htmlspecialchars($item['location']); ?></dd>

                        <dt>Date Lost:</dt>
                        <dd><?php echo date('F j, Y', strtotime($item['date_lost'])); ?></dd>

                        <dt>Description:</dt>
                        <dd><?php echo nl2br(htmlspecialchars($item['description'])); ?></dd>
                    </dl>
                </div>

                <div class="reporter-info">
                    <h2>Reporter Information</h2>
                    <p>Reported by: <?php echo htmlspecialchars($item['firstname'] . ' ' . $item['lastname']); ?></p>
                    <p>Contact: <?php echo htmlspecialchars($item['email']); ?></p>
                    <p>Date Reported: <?php echo date('F j, Y', strtotime($item['created_at'])); ?></p>
                </div>

                <?php if ($is_owner || isset($_SESSION['is_admin'])): ?>
                    <div class="item-actions">
                        <h2>Update Status</h2>
                        <form method="POST" class="status-form">
                            <select name="status" required>
                                <option value="lost" <?php echo $item['status'] == 'lost' ? 'selected' : ''; ?>>Lost</option>
                                <option value="found" <?php echo $item['status'] == 'found' ? 'selected' : ''; ?>>Found</option>
                                <option value="resolved" <?php echo $item['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                            <button type="submit" name="update_status" class="primary-btn">Update Status</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 