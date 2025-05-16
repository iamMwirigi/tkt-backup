<?php
$host = '127.0.0.1'; 
$dbname = 'tkt'; 
$username = 'root'; 
$password = '31278527'; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Important for security
];

$pdo = null; // Initialize $pdo outside the try-catch

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    // echo "Database connection successful!"; // For testing
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
