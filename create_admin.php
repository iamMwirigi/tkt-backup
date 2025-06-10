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

    // First ensure company exists
    $stmt = $conn->prepare("
        INSERT INTO companies (name, email) 
        SELECT 'Zawadi Express', 'admin@zawadi.co.ke'
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

    // Delete existing admin users
    $stmt = $conn->prepare("DELETE FROM users WHERE email = 'admin@zawadi.co.ke'");
    $stmt->execute();
    
    // Create new admin user with fresh password
    $admin_password = "admin123";
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("
        INSERT INTO users (company_id, name, email, password, role) 
        VALUES (?, 'Admin User', 'admin@zawadi.co.ke', ?, 'admin')
    ");
    
    $result = $stmt->execute([$company['id'], $hashed_password]);
    
    if ($result) {
        echo "Admin user created successfully!\n";
        echo "You can now login with:\n";
        echo "Email: admin@zawadi.co.ke\n";
        echo "Password: admin123\n";
        echo "Generated hash: " . $hashed_password . "\n";
        
        // Verify the user was created with admin role
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = 'admin@zawadi.co.ke'");
        $stmt->execute();
        $user = $stmt->fetch();
        echo "\nVerification:\n";
        echo "User ID: " . $user['id'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        echo "Company ID: " . $user['company_id'] . "\n";
    } else {
        echo "Failed to create admin user\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 