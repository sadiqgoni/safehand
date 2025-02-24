<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information for the sidebar
$stmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get categories for filter
$stmt = $pdo->query("SELECT * FROM item_categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle search
$where_conditions = ["1=1"]; // Always true condition to start with
$params = [];

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!empty($_GET['query'])) {
        $where_conditions[] = "(title LIKE ? OR description LIKE ? OR location LIKE ? OR unique_identifier LIKE ?)";
        $search_term = "%" . $_GET['query'] . "%";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    }
    
    if (!empty($_GET['category'])) {
        $where_conditions[] = "category = ?";
        $params[] = $_GET['category'];
    }
    
    if (!empty($_GET['status'])) {
        $where_conditions[] = "status = ?";
        $params[] = $_GET['status'];
    }

    if (!empty($_GET['date_from'])) {
        $where_conditions[] = "date_lost >= ?";
        $params[] = $_GET['date_from'];
    }

    if (!empty($_GET['date_to'])) {
        $where_conditions[] = "date_lost <= ?";
        $params[] = $_GET['date_to'];
    }
}

// Build and execute query
$where_clause = implode(" AND ", $where_conditions);
$query = "
    SELECT i.*, u.firstname, u.lastname 
    FROM lost_items i 
    JOIN users u ON i.user_id = u.id 
    WHERE {$where_clause} 
    ORDER BY i.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Items - SafeHand</title>
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


        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
        }

        /* Search Section Styles */
        .search-header {
            margin-bottom: 30px;
        }

        .search-header h1 {
            font-size: 32px;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .search-header p {
            color: var(--text-secondary);
            font-size: 16px;
            max-width: 600px;
        }

        .search-container {
            background: var(--card-background);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 30px;
            margin-bottom: 30px;
        }

        .search-box {
            position: relative;
            margin-bottom: 25px;
        }

        .search-box input {
            width: 100%;
            padding: 10px;
            /* padding-left: 10px; */
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
        }

        /* .search-box i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 20px;
        } */

        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 14px;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
        }

        .filter-buttons {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .apply-filters {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .apply-filters:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        .reset-filters {
            background: transparent;
            color: var(--text-secondary);
            padding: 12px 24px;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .reset-filters:hover {
            background: rgba(0,0,0,0.05);
        }

        /* Results Section */
        .results-container {
            background: var(--card-background);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 30px;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0,0,0,0.05);
        }

        .results-header h2 {
            font-size: 20px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .results-count {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .result-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .result-image {
            height: 200px;
            overflow: hidden;
            position: relative;
        }

        .result-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .result-content {
            padding: 20px;
        }

        .result-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .result-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.lost {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-badge.stolen {
            background: #f97316;
            color: #fff;
        }

        .status-badge.found {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-badge.resolved {
            background: #dbeafe;
            color: #2563eb;
        }

        .view-details {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            margin-top: 10px;
        }

        .view-details:hover {
            text-decoration: underline;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }

        .no-results i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--text-secondary);
            opacity: 0.5;
        }

        .no-results h3 {
            font-size: 20px;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .no-results p {
            max-width: 400px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .filter-buttons {
                flex-direction: column;
            }

            .results-grid {
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
                <a href="search.php" class="menu-item active">
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
            <div class="search-header">
                <h1>Search Lost Items</h1>
                <p>Search through all reported items or use filters to narrow down your search.</p>
            </div>

            <div class="search-container">
                <form method="GET" action="">
                    <div class="search-box">
                        <input type="text" name="query" placeholder="Search by title, description, location, or unique identifier (IMEI)..." 
                               value="<?php echo isset($_GET['query']) ? htmlspecialchars($_GET['query']) : ''; ?>">
                    </div>

                    <div class="filters">
                        <div class="filter-group">
                            <label for="category">Category</label>
                            <select name="category" id="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['name']); ?>"
                                            <?php echo (isset($_GET['category']) && $_GET['category'] == $category['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select name="status" id="status">
                                <option value="">All Status</option>
                                <option value="lost" <?php echo (isset($_GET['status']) && $_GET['status'] == 'lost') ? 'selected' : ''; ?>>Lost</option>
                                <option value="stolen" <?php echo (isset($_GET['status']) && $_GET['status'] == 'stolen') ? 'selected' : ''; ?>>Stolen</option>
                                <option value="missing" <?php echo (isset($_GET['status']) && $_GET['status'] == 'missing') ? 'selected' : ''; ?>>Missing</option>
                                <option value="found" <?php echo (isset($_GET['status']) && $_GET['status'] == 'found') ? 'selected' : ''; ?>>Found</option>
                                <option value="resolved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="date_from">Date From</label>
                            <input type="date" name="date_from" id="date_from" 
                                   value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                        </div>

                        <div class="filter-group">
                            <label for="date_to">Date To</label>
                            <input type="date" name="date_to" id="date_to"
                                   value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                        </div>
                    </div>

                    <div class="filter-buttons">
                        <button type="submit" class="apply-filters">
                            <i class="fas fa-filter"></i>
                            Apply Filters
                        </button>
                        <a href="search.php" class="reset-filters">Reset Filters</a>
                    </div>
                </form>
            </div>

            <div class="results-container">
                <div class="results-header">
                    <h2>
                        <i class="fas fa-list"></i>
                        Search Results
                    </h2>
                    <div class="results-count">
                        <?php echo count($items); ?> items found
                    </div>
                </div>

                <?php if (count($items) > 0): ?>
                    <div class="results-grid">
                        <?php foreach ($items as $item): ?>
                            <div class="result-card">
                                <div class="result-image">
                                    <?php if ($item['image_path']): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item Image">
                                    <?php else: ?>
                                        <img src="assets/images/placeholder.jpg" alt="No Image">
                                    <?php endif; ?>
                                </div>
                                <div class="result-content">
                                    <div class="result-title">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                    </div>
                                    <div class="result-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-tag"></i>
                                            <?php echo htmlspecialchars($item['category']); ?>
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($item['location']); ?>
                                        </span>
                                    </div>
                                    <span class="status-badge <?php echo $item['status']; ?>">
                                        <?php echo ucfirst($item['status']); ?>
                                    </span>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('M d, Y', strtotime($item['date_lost'])); ?>
                                    </div>
                                    <a href="item-details.php?id=<?php echo $item['id']; ?>" class="view-details">
                                        View Details
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <h3>No items found</h3>
                        <p>Try adjusting your search criteria or filters to find what you're looking for.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>