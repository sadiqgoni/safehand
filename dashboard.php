<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get categories for the dropdown
$stmt = $pdo->query("SELECT * FROM item_categories");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_items,
    SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost_items,
    SUM(CASE WHEN status = 'found' THEN 1 ELSE 0 END) as found_items,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_items
    FROM lost_items WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['report_item'])) {
    $category = trim($_POST['category']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $unique_identifier = trim($_POST['unique_identifier'] ?? '');
    $location = trim($_POST['location']);
    $date_lost = trim($_POST['date_lost']);
    
    $image_path = null;
    $upload_error = null;
    
    // Handle image upload
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['item_image']['name'];
        $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($filetype, $allowed)) {
            $new_filename = uniqid() . '_' . time() . '.' . $filetype;
            $upload_path = 'uploads/' . $new_filename;
            
            if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_path)) {
                $image_path = $upload_path;
            } else {
                $upload_error = "Failed to upload image. Please try again.";
            }
        } else {
            $upload_error = "Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    }
    
    if ($upload_error) {
        $_SESSION['error_message'] = $upload_error;
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO lost_items (user_id, category, title, description, unique_identifier, location, date_lost, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'lost')");
            $stmt->execute([$_SESSION['user_id'], $category, $title, $description, $unique_identifier, $location, $date_lost, $image_path]);
            $_SESSION['success_message'] = "Item reported successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch(PDOException $e) {
            $_SESSION['error_message'] = "Failed to report item. Please try again.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}

// Get flash messages
$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

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
    <title>Dashboard - SafeHand</title>
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

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
        }

        .page-header {
            margin-bottom: 40px;
            position: relative;
        }

        .page-header h1 {
            font-size: 32px;
            color: var(--text-primary);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .page-header::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: var(--accent-color);
            border-radius: 2px;
            margin-top: 10px;
        }

        /* Stats Cards */
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
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card:nth-child(1)::before {
            background: var(--primary-color);
        }

        .stat-card:nth-child(2)::before {
            background: var(--danger-color);
        }

        .stat-card:nth-child(3)::before {
            background: var(--success-color);
        }

        .stat-card:nth-child(4)::before {
            background: var(--warning-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .stat-card .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .stat-card .stat-label {
            color: var(--text-secondary);
            font-size: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-card .stat-label i {
            font-size: 18px;
            opacity: 0.8;
        }

        /* Content Sections */
        .content-section {
            background: var(--card-background);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 30px;
            margin-bottom: 40px;
            border: 1px solid rgba(0,0,0,0.05);
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
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h2 i {
            color: var(--accent-color);
        }

        /* Form Styles */
        .report-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #fff;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .primary-btn {
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
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
        }

        .primary-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(79, 70, 229, 0.3);
        }

        /* Items Grid */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .item-card {
            background: var(--card-background);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .item-card .item-image {
            height: 220px;
            position: relative;
        }

        .item-card .item-image::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(to top, rgba(0,0,0,0.4), transparent);
        }

        .item-card .item-content {
            padding: 25px;
        }

        .item-card .status {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .item-card .status.lost {
            background: #fee2e2;
            color: #dc2626;
        }

        .item-card .status.found {
            background: #dcfce7;
            color: #16a34a;
        }

        .item-card .status.resolved {
            background: #dbeafe;
            color: #2563eb;
        }

        .item-card .view-details {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            margin-top: 15px;
            transition: all 0.3s ease;
        }

        .item-card .view-details:hover {
            color: var(--primary-dark);
            gap: 12px;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .report-form {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
                <a href="dashboard.php" class="menu-item active">
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
            <div class="page-header">
                <h1>Welcome back, <?php echo htmlspecialchars($user['firstname']); ?>!</h1>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_items']; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-layer-group"></i>
                        Total Items
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['lost_items']; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-search"></i>
                        Lost Items
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['found_items']; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-check-circle"></i>
                        Found Items
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['resolved_items']; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-flag-checkered"></i>
                        Resolved Cases
                    </div>
                </div>
            </div>

            <!-- Report Item Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-plus-circle"></i>
                        Report Lost Item
                    </h2>
                </div>

                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" class="report-form">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select name="category" id="category" required>
                            <option value="">Select Category</option>
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
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" required placeholder="Enter a descriptive title">
                    </div>

                    <div class="form-group full-width">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" required placeholder="Provide detailed description of the item"></textarea>
                    </div>

                    <div class="form-group unique-id-field" style="display: none;">
                        <label for="unique_identifier">Unique Identifier</label>
                        <input type="text" id="unique_identifier" name="unique_identifier" placeholder="Enter the unique identifier">
                        <small class="hint"></small>
                    </div>

                    <div class="form-group">
                        <label for="location">Last Known Location</label>
                        <input type="text" id="location" name="location" required placeholder="Where was it last seen?">
                    </div>

                    <div class="form-group">
                        <label for="date_lost">Date Lost</label>
                        <input type="date" id="date_lost" name="date_lost" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="item_image">Upload Image</label>
                        <input type="file" id="item_image" name="item_image" accept="image/*">
                    </div>

                    <div class="form-group full-width">
                        <button type="submit" name="report_item" class="primary-btn">
                            <i class="fas fa-plus"></i> Report Item
                        </button>
                    </div>
                </form>
            </div>

            <!-- Reported Items Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-list"></i>
                        Your Reported Items
                    </h2>
                </div>

                <div class="items-grid">
                    <?php foreach ($reported_items as $item): ?>
                        <div class="item-card">
                            <div class="item-image">
                                <?php if ($item['image_path'] && file_exists($item['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item Image" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <img src="assets/images/placeholder.jpg" alt="No Image" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php endif; ?>
                            </div>
                            <div class="item-content">
                                <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                <p class="category">
                                    <i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($item['category']); ?>
                                </p>
                                <span class="status <?php echo $item['status']; ?>">
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                                <p class="date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M d, Y', strtotime($item['created_at'])); ?>
                                </p>
                                <a href="item-details.php?id=<?php echo $item['id']; ?>" class="view-details">
                                    View Details <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
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

        // Preview image before upload
        document.getElementById('item_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.createElement('img');
                    preview.src = e.target.result;
                    preview.style.maxWidth = '200px';
                    preview.style.marginTop = '10px';
                    
                    const container = document.getElementById('item_image').parentNode;
                    const oldPreview = container.querySelector('img');
                    if (oldPreview) {
                        container.removeChild(oldPreview);
                    }
                    container.appendChild(preview);
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>