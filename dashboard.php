<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get categories for the dropdown
$stmt = $pdo->query("SELECT * FROM item_categories");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['report_item'])) {
    $category = trim($_POST['category']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $unique_identifier = trim($_POST['unique_identifier'] ?? '');
    $location = trim($_POST['location']);
    $date_lost = trim($_POST['date_lost']);
    
    $image_path = null;
    
    // Handle image upload
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['item_image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($filetype), $allowed)) {
            $new_filename = uniqid() . '.' . $filetype;
            $upload_path = 'uploads/' . $new_filename;
            
            if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_path)) {
                $image_path = $upload_path;
            }
        }
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO lost_items (user_id, category, title, description, unique_identifier, location, date_lost, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $category, $title, $description, $unique_identifier, $location, $date_lost, $image_path]);
        $success = "Item reported successfully!";
    } catch(PDOException $e) {
        $error = "Failed to report item. Please try again.";
    }
}

// Get user's reported items
$stmt = $pdo->prepare("SELECT * FROM lost_items WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$reported_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeFind - Dashboard</title>
    <link rel="stylesheet" type="text/css" href="./assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <div class="logo">
                <h1>SafeFind</h1>
            </div>
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="search.php">Search Items</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>

        <div class="dashboard-content">
            <div class="report-section">
                <h2>Report Lost Item</h2>
                
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="success-message"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" class="report-form">
                    <div class="form-group">
                        <label for="category">Category:</label>
                        <select name="category" id="category" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>" 
                                        data-requires-id="<?php echo $category['requires_unique_id']; ?>"
                                        data-id-label="<?php echo htmlspecialchars($category['unique_id_label']); ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="title">Title:</label>
                        <input type="text" id="title" name="title" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" required></textarea>
                    </div>

                    <div class="form-group unique-id-field" style="display: none;">
                        <label for="unique_identifier">Unique Identifier:</label>
                        <input type="text" id="unique_identifier" name="unique_identifier">
                        <small class="hint"></small>
                    </div>

                    <div class="form-group">
                        <label for="location">Last Known Location:</label>
                        <input type="text" id="location" name="location" required>
                    </div>

                    <div class="form-group">
                        <label for="date_lost">Date Lost:</label>
                        <input type="date" id="date_lost" name="date_lost" required>
                    </div>

                    <div class="form-group">
                        <label for="item_image">Upload Image:</label>
                        <input type="file" id="item_image" name="item_image" accept="image/*">
                    </div>

                    <button type="submit" name="report_item" class="primary-btn">Report Item</button>
                </form>
            </div>

            <div class="reported-items-section">
                <h2>Your Reported Items</h2>
                <div class="items-grid">
                    <?php foreach ($reported_items as $item): ?>
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
            </div>
        </div>
    </div>

    <script>
        // Handle unique identifier field visibility
        document.getElementById('category').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const uniqueIdField = document.querySelector('.unique-id-field');
            const uniqueIdInput = document.getElementById('unique_identifier');
            const hint = document.querySelector('.unique-id-field .hint');
            
            if (selectedOption.dataset.requiresId === '1') {
                uniqueIdField.style.display = 'block';
                uniqueIdInput.required = true;
                hint.textContent = `Please enter the ${selectedOption.dataset.idLabel}`;
            } else {
                uniqueIdField.style.display = 'none';
                uniqueIdInput.required = false;
                uniqueIdInput.value = '';
            }
        });
    </script>
</body>
</html>