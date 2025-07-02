<?php
// FOR DEBUGGING ONLY - !! REMOVE OR DISABLE FOR PRODUCTION !!
ini_set('display_errors', 1);
ini_set('log_errors', 1); // Ensure errors are also logged
error_reporting(E_ALL);

// Start session at the very beginning, before any output.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Attempt to load environment variables from a .env file
// Assumes .env file and vendor directory are one level up from this auth directory
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('Dotenv\Dotenv')) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); // Path to the directory containing .env (tkt-backup)
            $loadedVars = $dotenv->load(); // Attempt to load and capture result

            // Log what Dotenv reports as loaded
            // error_log("Dotenv loaded variables: " . print_r($loadedVars, true)); // Uncomment for very verbose logging if needed

            if (array_key_exists('DB_PASSWORD', $loadedVars)) {
                error_log("login.php: Dotenv reports DB_PASSWORD was loaded from .env file.");
            } else {
                error_log("login.php: Dotenv reports DB_PASSWORD was NOT found in the loaded variables from .env file. Check .env content and path.");
            }

            // Check critical $_ENV variables directly after load
            error_log("login.php: _ENV['DB_HOST'] = " . ($_ENV['DB_HOST'] ?? 'NOT SET'));
            error_log("login.php: _ENV['DB_USER'] = " . ($_ENV['DB_USER'] ?? 'NOT SET'));
            error_log("login.php: _ENV['DB_PASSWORD'] = " . (isset($_ENV['DB_PASSWORD']) && $_ENV['DB_PASSWORD'] !== '' ? 'SET (hidden length)' : 'NOT SET or EMPTY'));
            error_log("login.php: _ENV['DB_NAME'] = " . ($_ENV['DB_NAME'] ?? 'NOT SET'));

        } catch (\Dotenv\Exception\InvalidPathException $e) {
            error_log("Dotenv Error in login.php: Invalid path. Could not find .env file at " . realpath(__DIR__ . '/..') . ". Message: " . $e->getMessage());
        } catch (\Dotenv\Exception\InvalidFileException $e) {
            error_log("Dotenv Error in login.php: Invalid .env file (e.g., permissions, syntax). Message: " . $e->getMessage());
        } catch (Exception $e) { // Catch any other Dotenv related exceptions
            error_log("Dotenv Error in login.php: An unexpected error occurred during .env loading. Message: " . $e->getMessage());
        }
    } else {
        error_log("Dotenv class 'Dotenv\Dotenv' not found in login.php. Ensure 'vlucas/phpdotenv' is installed via Composer and autoloaded.");
    }
}


require_once '../config/db.php';
require_once '../utils/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, ['error' => 'Method not allowed']);
}

$data = json_decode(file_get_contents('php://input'), true);

// First validate email and password for all users
validateRequiredFields(['email', 'password'], $data);

$db = new Database();
$conn = $db->getConnection();

try {
    // Get user
    $stmt = $conn->prepare("
        SELECT 
            u.*,
            c.id as company_id,
            c.name as company_name,
            c.email as company_email,
            o.id as office_id,
            o.name as office_name,
            o.location as office_location
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        LEFT JOIN offices o ON u.office_id = o.id
        WHERE u.email = ?
    ");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch();

    // Add detailed debugging
    error_log("Login attempt for email: " . $data['email']);
    error_log("User found: " . ($user ? 'Yes' : 'No'));
    if ($user) {
        error_log("User role: " . $user['role']);
        error_log("Stored password hash: " . $user['password']);
        error_log("Attempting to verify password...");
        $verify_result = password_verify($data['password'], $user['password']);
        error_log("Password verification result: " . ($verify_result ? 'Success' : 'Failed'));
        if (!$verify_result) {
            error_log("Password verification failed. Input password: " . $data['password']);
        }
    }

    if (!$user || !password_verify($data['password'], $user['password'])) {
        sendResponse(401, ['error' => 'Invalid credentials']);
    }

    // For non-admin users, device_id is required
    if ($user['role'] !== 'admin') {
        if (!isset($data['device_id']) || empty($data['device_id'])) {
            sendResponse(400, ['error' => 'Device ID is required for non-admin users']);
        }

        // Check if device exists and is associated with this user
        $stmt = $conn->prepare("
            SELECT id FROM devices 
            WHERE device_uuid = ? 
            AND (user_id = ? OR user_id IS NULL)
            AND is_active = 1
        ");
        $stmt->execute([$data['device_id'], $user['id']]);
        $device = $stmt->fetch();

        if (!$device) {
            // Register new device
            $stmt = $conn->prepare("
                INSERT INTO devices (company_id, user_id, device_uuid, device_name) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $user['company_id'],
                $user['id'],
                $data['device_id'],
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device'
            ]);
        } else if (isset($device['user_id']) && $device['user_id'] === null) {
            // Update existing device with user_id if it wasn't set
            $stmt = $conn->prepare("
                UPDATE devices 
                SET user_id = ?, company_id = ? 
                WHERE device_uuid = ?
            ");
            $stmt->execute([$user['id'], $user['company_id'], $data['device_id']]);
        }
    }

    

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['company_id'] = $user['company_id'];
    $_SESSION['role'] = $user['role'];

    sendResponse(200, [
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'company' => [
                'id' => $user['company_id'],
                'name' => $user['company_name'],
                'email' => $user['company_email']
            ],
            'office' => $user['office_id'] ? [
                'id' => $user['office_id'],
                'name' => $user['office_name'],
                'location' => $user['office_location']
            ] : null
        ]
    ]);

} catch (PDOException $e) {
    sendResponse(500, [
        'error' => true,
        'message' => 'Login failed due to a database issue.',
        'details' => $e->getMessage()
    ]);
}
