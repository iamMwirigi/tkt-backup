<?php
// Load environment variables
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    if (class_exists('Dotenv\Dotenv')) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
            $dotenv->load();
        } catch (Exception $e) {
            error_log("Dotenv Error in create_admin.php: " . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/config/db.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

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