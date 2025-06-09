<?php
// FOR DEBUGGING ONLY
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    if (class_exists('Dotenv\Dotenv')) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
            $dotenv->load();
            echo "Dotenv loaded from test_db.php.<br>";
            echo "DB_HOST from _ENV: " . htmlspecialchars($_ENV['DB_HOST'] ?? 'NOT SET') . "<br>";
            echo "DB_USER from _ENV: " . htmlspecialchars($_ENV['DB_USER'] ?? 'NOT SET') . "<br>";
            echo "DB_PASSWORD from _ENV: " . (isset($_ENV['DB_PASSWORD']) && $_ENV['DB_PASSWORD'] !== '' ? 'SET (present)' : 'NOT SET or EMPTY') . "<br>";
            echo "DB_NAME from _ENV: " . htmlspecialchars($_ENV['DB_NAME'] ?? 'NOT SET') . "<br><hr>";
        } catch (Exception $e) {
            echo "Error loading .env in test_db.php: " . htmlspecialchars($e->getMessage()) . "<br><hr>";
        }
    } else {
        echo "Dotenv class not found in test_db.php.<br><hr>";
    }
} else {
    echo "Autoload file not found in test_db.php (expected at " . htmlspecialchars(__DIR__ . '/vendor/autoload.php') . ").<br><hr>";
}

require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json'); // Set early for consistent output type

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($conn) {
        // To verify connection, let's try a simple query
        $stmt = $conn->query("SELECT 1 AS test_col");
        if ($stmt && $stmt->fetch()) {
            echo json_encode([
                'success' => true,
                'message' => '✅ Successfully connected to the database and executed a query!'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => '❌ Connected, but test query failed!',
                'error_info' => $conn->errorInfo()
            ]);
        }
    } else {
        // This case should ideally not be reached if getConnection throws
        echo json_encode([
            'success' => false,
            'message' => '❌ Database connection returned null/false, but no exception was caught directly in test_db.php.'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '❌ Database connection failed in test_db.php (PDOException).',
        'details' => $e->getMessage()
        // 'trace' => $e->getTraceAsString() // Uncomment for full trace
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '❌ An unexpected error occurred in test_db.php.',
        'details' => $e->getMessage()
        // 'trace' => $e->getTraceAsString() // Uncomment for full trace
    ]);
}
