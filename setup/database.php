<?php
require_once './config/db.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Create companies table
    $conn->exec("CREATE TABLE IF NOT EXISTS companies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create users table
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id)
    )");

    // Create devices table
    $conn->exec("CREATE TABLE IF NOT EXISTS devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT,
        user_id INT,
        device_uuid VARCHAR(100) UNIQUE NOT NULL,
        device_name VARCHAR(100),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Create test company and admin user if they don't exist
    $stmt = $conn->prepare("SELECT id FROM companies WHERE name = 'Test Company'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $conn->exec("INSERT INTO companies (name) VALUES ('Test Company')");
        $company_id = $conn->lastInsertId();
        
        // Create admin user with password 'admin123'
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->exec("INSERT INTO users (company_id, name, email, password, role) 
                    VALUES ($company_id, 'Admin User', 'admin@example.com', '$password_hash', 'admin')");
    }

    echo "Database setup completed successfully!\n";
    echo "You can now login with:\n";
    echo "Email: admin@example.com\n";
    echo "Password: admin123\n";

} catch(PDOException $e) {
    echo "Setup Error: " . $e->getMessage();
}
?> 