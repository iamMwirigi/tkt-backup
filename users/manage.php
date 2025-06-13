<?php
// FOR DEBUGGING ONLY - !! REMOVE OR DISABLE FOR PRODUCTION !!
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment variables
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('Dotenv\Dotenv')) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
        } catch (Exception $e) {
            error_log("Dotenv Error in users/manage.php: " . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate company_id
    if (!isset($data['company_id'])) {
        sendResponse(400, [
            'error' => true,
            'message' => 'company_id is required'
        ]);
    }
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get users
            if (isset($_GET['id'])) {
                // Get single user
                $stmt = $conn->prepare("
                    SELECT u.*, c.name as company_name, o.name as office_name
                    FROM users u
                    LEFT JOIN companies c ON u.company_id = c.id
                    LEFT JOIN offices o ON u.office_id = o.id
                    WHERE u.id = ? AND u.company_id = ?
                ");
                $stmt->execute([$_GET['id'], $data['company_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    sendResponse(404, [
                        'error' => true,
                        'message' => 'User not found'
                    ]);
                }
                
                sendResponse(200, [
                    'success' => true,
                    'user' => $user
                ]);
            } else {
                // Get all users with filters
                $where = ['u.company_id = ?'];
                $params = [$data['company_id']];
                
                if (isset($_GET['office_id'])) {
                    $where[] = 'u.office_id = ?';
                    $params[] = $_GET['office_id'];
                }
                
                if (isset($_GET['role'])) {
                    $where[] = 'u.role = ?';
                    $params[] = $_GET['role'];
                }
                
                if (isset($_GET['name'])) {
                    $where[] = 'u.name LIKE ?';
                    $params[] = '%' . $_GET['name'] . '%';
                }
                
                if (isset($_GET['email'])) {
                    $where[] = 'u.email LIKE ?';
                    $params[] = '%' . $_GET['email'] . '%';
                }
                
                $where_clause = implode(' AND ', $where);
                
                $stmt = $conn->prepare("
                    SELECT u.*, c.name as company_name, o.name as office_name
                    FROM users u
                    LEFT JOIN companies c ON u.company_id = c.id
                    LEFT JOIN offices o ON u.office_id = o.id
                    WHERE $where_clause
                    ORDER BY u.created_at DESC
                ");
                $stmt->execute($params);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendResponse(200, [
                    'success' => true,
                    'users' => $users
                ]);
            }
            break;
            
        case 'POST':
            // Create new user
            validateRequiredFields(['name', 'email', 'password', 'role'], $data);
            
            // Validate office exists if provided
            if (isset($data['office_id'])) {
                $stmt = $conn->prepare("SELECT id FROM offices WHERE id = ? AND company_id = ?");
                $stmt->execute([$data['office_id'], $data['company_id']]);
                if (!$stmt->fetch()) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => 'Invalid office ID or office does not belong to the specified company'
                    ]);
                }
            }
            
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Email already exists'
                ]);
            }
            
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, password, role, company_id, office_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['role'],
                $data['company_id'],
                $data['office_id'] ?? null
            ]);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'User created successfully',
                'user_id' => $conn->lastInsertId()
            ]);
            break;
            
        case 'PUT':
            // Update existing user
            validateRequiredFields(['id'], $data);
            
            // Verify user exists and belongs to company
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $data['company_id']]);
            $existingUser = $stmt->fetch();
            
            if (!$existingUser) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'User not found or does not belong to your company'
                ]);
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['name'])) {
                $updates[] = "name = ?";
                $params[] = $data['name'];
            }
            if (isset($data['email'])) {
                // Check if new email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$data['email'], $data['id']]);
                if ($stmt->fetch()) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => 'Email already exists'
                    ]);
                }
                $updates[] = "email = ?";
                $params[] = $data['email'];
            }
            if (isset($data['password'])) {
                $updates[] = "password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            if (isset($data['role'])) {
                $updates[] = "role = ?";
                $params[] = $data['role'];
            }
            if (isset($data['office_id'])) {
                // Validate office belongs to company
                $stmt = $conn->prepare("SELECT id FROM offices WHERE id = ? AND company_id = ?");
                $stmt->execute([$data['office_id'], $data['company_id']]);
                if (!$stmt->fetch()) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => 'Invalid office ID or office does not belong to your company'
                    ]);
                }
                $updates[] = "office_id = ?";
                $params[] = $data['office_id'];
            }
            
            if (empty($updates)) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'No fields to update'
                ]);
            }
            
            $params[] = $data['id'];
            $params[] = $data['company_id'];
            
            $stmt = $conn->prepare("
                UPDATE users 
                SET " . implode(", ", $updates) . "
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute($params);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'User updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete user
            validateRequiredFields(['id'], $data);
            
            // Verify user exists and belongs to company
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $data['company_id']]);
            $user = $stmt->fetch();
            
            if (!$user) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'User not found or does not belong to your company'
                ]);
            }
            
            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $data['company_id']]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
            break;
            
        default:
            sendResponse(405, [
                'error' => true,
                'message' => 'Method not allowed'
            ]);
    }
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 