<?php
// Set database credentials directly for this script
$host = 'db.igurudb.com';
$port = '3306';
$dbname = 'tkt';
$username = 'dev_ops1';
$password = 'a26N8Iv22TC4kJdb'; // Replace with your actual password

try {
    $conn = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );

    // Password to use
    $admin_password = "admin123";
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

    // Debug output
    echo "Generated password hash: " . $hashed_password . "\n";

    // First ensure company exists
    $stmt = $conn->prepare("
        INSERT INTO companies (name) 
        SELECT 'Zawadi Express' 
        WHERE NOT EXISTS (SELECT 1 FROM companies WHERE name = 'Zawadi Express')
    ");
    $stmt->execute();
    
    // Get company ID
    $stmt = $conn->prepare("SELECT id FROM companies WHERE name = 'Zawadi Express' LIMIT 1");
    $stmt->execute();
    $company = $stmt->fetch();
    
    if (!$company) {
        throw new Exception("Failed to get company ID");
    }
    
    // Create or update admin user
    $stmt = $conn->prepare("
        INSERT INTO users (company_id, name, email, password, role) 
        VALUES (?, 'Admin User', 'admin@zawadi.co.ke', ?, 'admin')
        ON DUPLICATE KEY UPDATE 
        password = VALUES(password),
        role = 'admin'
    ");
    
    $result = $stmt->execute([$company['id'], $hashed_password]);
    
    if ($result) {
        echo "Admin user created/updated successfully!\n";
        echo "You can now login with:\n";
        echo "Email: admin@zawadi.co.ke\n";
        echo "Password: admin123\n";
    } else {
        echo "Failed to create/update admin user\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 