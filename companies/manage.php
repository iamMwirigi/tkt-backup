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
            error_log("Dotenv Error in companies/manage.php: " . $e->getMessage());
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
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get companies
            if (isset($_GET['id'])) {
                // Get single company
                $stmt = $conn->prepare("
                    SELECT 
                        c.*,
                        COUNT(DISTINCT u.id) as total_users,
                        COUNT(DISTINCT v.id) as total_vehicles,
                        COUNT(DISTINCT t.id) as total_trips
                    FROM companies c
                    LEFT JOIN users u ON c.id = u.company_id
                    LEFT JOIN vehicles v ON c.id = v.company_id
                    LEFT JOIN trips t ON c.id = t.company_id
                    WHERE c.id = ?
                    GROUP BY c.id
                ");
                $stmt->execute([$_GET['id']]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$company) {
                    sendResponse(404, [
                        'error' => true,
                        'message' => 'Company not found'
                    ]);
                }
                
                sendResponse(200, [
                    'success' => true,
                    'company' => $company
                ]);
            } else {
                // Get all companies with filters
                $where = ['1=1'];
                $params = [];
                
                if (isset($_GET['name'])) {
                    $where[] = 'c.name LIKE ?';
                    $params[] = '%' . $_GET['name'] . '%';
                }
                
                if (isset($_GET['email'])) {
                    $where[] = 'c.email LIKE ?';
                    $params[] = '%' . $_GET['email'] . '%';
                }
                
                $where_clause = implode(' AND ', $where);
                
                $stmt = $conn->prepare("
                    SELECT 
                        c.*,
                        COUNT(DISTINCT u.id) as total_users,
                        COUNT(DISTINCT v.id) as total_vehicles,
                        COUNT(DISTINCT t.id) as total_trips
                    FROM companies c
                    LEFT JOIN users u ON c.id = u.company_id
                    LEFT JOIN vehicles v ON c.id = v.company_id
                    LEFT JOIN trips t ON c.id = t.company_id
                    WHERE $where_clause
                    GROUP BY c.id
                    ORDER BY c.created_at DESC
                ");
                $stmt->execute($params);
                $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendResponse(200, [
                    'success' => true,
                    'companies' => $companies
                ]);
            }
            break;
            
        case 'POST':
            // Create new company
            validateRequiredFields(['name', 'email', 'password'], $data);
            
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM companies WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Email already exists'
                ]);
            }
            
            $stmt = $conn->prepare("
                INSERT INTO companies (name, email, password) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT)
            ]);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Company created successfully',
                'company_id' => $conn->lastInsertId()
            ]);
            break;
            
        case 'PUT':
            // Update existing company
            validateRequiredFields(['id'], $data);
            
            // Verify company exists
            $stmt = $conn->prepare("SELECT id FROM companies WHERE id = ?");
            $stmt->execute([$data['id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Company not found'
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
                $stmt = $conn->prepare("SELECT id FROM companies WHERE email = ? AND id != ?");
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
            
            if (empty($updates)) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'No fields to update'
                ]);
            }
            
            $params[] = $data['id'];
            
            $stmt = $conn->prepare("
                UPDATE companies 
                SET " . implode(", ", $updates) . "
                WHERE id = ?
            ");
            $stmt->execute($params);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Company updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete company
            validateRequiredFields(['id'], $data);
            
            // Verify company exists
            $stmt = $conn->prepare("SELECT id FROM companies WHERE id = ?");
            $stmt->execute([$data['id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Company not found'
                ]);
            }
            
            // Check if company has any active users
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM users 
                WHERE company_id = ?
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete company with active users'
                ]);
            }
            
            // Check if company has any active bookings
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM bookings 
                WHERE company_id = ? AND status != 'completed'
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete company with active bookings'
                ]);
            }
            
            $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Company deleted successfully'
            ]);
            break;
            
        default:
            sendResponse(405, [
                'error' => true,
                'message' => 'Method not allowed'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Error in companies/manage.php: " . $e->getMessage());
    sendResponse(500, [
        'error' => true,
        'message' => 'Internal server error'
    ]);
} 