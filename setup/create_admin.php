<?php
require_once './config/db.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // First, create the company if it doesn't exist
    $stmt = $conn->prepare("SELECT id FROM companies WHERE name = 'Zawadi Co. Ltd'");
    $stmt->execute();
    $company = $stmt->fetch();

    if (!$company) {
        $stmt = $conn->prepare("INSERT INTO companies (name) VALUES ('Zawadi Co. Ltd')");
        $stmt->execute();
        $company_id = $conn->lastInsertId();
    } else {
        $company_id = $company['id'];
    }

    // Check if admin user already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = 'admin@zawadi.co.ke'");
    $stmt->execute();
    $user = $stmt->fetch();

    if (!$user) {
        // Create admin user with password 'adminpassword'
        $password_hash = password_hash('adminpassword', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO users (company_id, name, email, password, role) 
            VALUES (?, 'Admin User', 'admin@zawadi.co.ke', ?, 'admin')
        ");
        $stmt->execute([$company_id, $password_hash]);
        echo "Admin user created successfully!\n";
    } else {
        // Update existing admin user's password
        $password_hash = password_hash('adminpassword', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            UPDATE users 
            SET password = ?, role = 'admin' 
            WHERE email = 'admin@zawadi.co.ke'
        ");
        $stmt->execute([$password_hash]);
        echo "Admin user password updated successfully!\n";
    }

    echo "You can now login with:\n";
    echo "Email: admin@zawadi.co.ke\n";
    echo "Password: adminpassword\n";

} catch(PDOException $e) {
    echo "Setup Error: " . $e->getMessage();
}
?> 