<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$search_results = [];
$search_performed = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $search_type = $_POST['search_type'];
    $search_value = trim($_POST['search_value']);
    
    if (!empty($search_value)) {
        $search_performed = true;
        
        if ($search_type === 'unique_id') {
            // Search by IMEI or VIN
            $stmt = $pdo->prepare("SELECT li.*, u.email as reporter_email 
                                 FROM lost_items li 
                                 JOIN users u ON li.user_id = u.id 
                                 WHERE li.unique_identifier = ?");
            $stmt->execute([$search_value]);
        } else {
            // General search in title and description
            $search_value = "%$search_value%";
            $stmt = $pdo->prepare("SELECT li.*, u.email as reporter_email 
                                 FROM lost_items li 
                                 JOIN users u ON li.user_id = u.id 
                                 WHERE li.title LIKE ? OR li.description LIKE ?");
            $stmt->execute([$search_value, $search_value]);
        }
        
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeFind - Search Items</title>
    <link rel="stylesheet" type="text/css" href="./assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <!-- Same navigation as dashboard.php -->
        </nav>

        <div class="dashboard-content">
            <div class="search-section">
                <h2>Search Lost Items</h2>
                
                <form method="POST" action="" class="search-form">
                    <div class="form-group">
                        <label for="search_type">Search By:</label>
                        <select name="search_type" id="search_type" required>
                            <option value="general">General Search</option>
                            <option value="unique_id">IMEI/VIN Number</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="search_value">Search Term:</label>
                        <input type="text" id="search_value" name="search_value" required>
                    </div>

                    <button type="submit" class="primary-btn">Search</button>
                </form>

                <?php if ($search_performed): ?>
                    <div class="search-results">
                        <h3>Search Results</h3>
                        <?php if (empty($search_results)): ?>
                            <p>No items found matching your search.</p>
                        <?php else: ?>
                            <div class="items-grid">
                                <?php foreach ($search_results as $item): ?>
                                    <div class="item-card">
                                        <?php if ($item['image_path']): ?>
                                            <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item Image">
                                        <?php endif; ?>
                                        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                        <p class="category"><?php echo htmlspecialchars($item['category']); ?></p>
                                        <p class="status <?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></p>
                                        <p class="date">Reported: <?php echo date('M d, Y', strtotime($item['created_at'])); ?></p>
                                        <a href="item-details.php?id=<?php echo $item['id']; ?>" class="view-details">View Details</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>