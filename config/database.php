<?php
$host = 'localhost';
$dbname = 'safehand';
$username = 'root';
$password = '';

try {
    // First, connect without specifying a database
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    
    // Connect to the specific database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        firstname VARCHAR(50) NOT NULL,
        lastname VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone_number VARCHAR(20),
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin') DEFAULT 'user',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Create item_categories table
    $pdo->exec("CREATE TABLE IF NOT EXISTS item_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        description TEXT,
        requires_unique_id BOOLEAN DEFAULT FALSE,
        unique_id_label VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create lost_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS lost_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        category VARCHAR(50) NOT NULL,
        title VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        unique_identifier VARCHAR(100),
        location VARCHAR(255) NOT NULL,
        date_lost DATE NOT NULL,
        image_path VARCHAR(255),
        status ENUM('lost', 'stolen', 'missing', 'found', 'resolved') DEFAULT 'lost',
        verified TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Create match_requests table
    $pdo->exec("CREATE TABLE IF NOT EXISTS match_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        owner_id INT NOT NULL,
        finder_id INT NOT NULL,
        message TEXT NOT NULL,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        admin_verified TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES lost_items(id),
        FOREIGN KEY (owner_id) REFERENCES users(id),
        FOREIGN KEY (finder_id) REFERENCES users(id)
    )");

    // Create notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        related_id INT,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Insert default categories if item_categories table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM item_categories");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO item_categories (name, description, requires_unique_id, unique_id_label) VALUES 
            ('Electronics', 'Mobile phones, laptops, tablets, and other electronic devices', TRUE, 'IMEI/Serial Number'),
            ('Documents', 'ID cards, passports, certificates, and other important documents', TRUE, 'Document Number'),
            ('Vehicles', 'Cars, motorcycles, bicycles', TRUE, 'VIN/Registration Number'),
            ('Personal Accessories', 'Wallets, bags, jewelry, watches', FALSE, NULL),
            ('Books', 'Textbooks, novels, and other reading materials', FALSE, NULL),
            ('Clothing', 'Clothes, shoes, and other wearable items', FALSE, NULL),
            ('Keys', 'House keys, car keys, office keys', FALSE, NULL),
            ('Others', 'Other items not fitting in above categories', FALSE, NULL)
        ");
    }

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
