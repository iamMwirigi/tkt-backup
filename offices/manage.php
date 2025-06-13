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
            error_log("Dotenv Error in offices/manage.php: " . $e->getMessage());
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
            // Get offices
            if (isset($_GET['id'])) {
                // Get single office
                $stmt = $conn->prepare("
                    SELECT o.*, c.name as company_name
                    FROM offices o
                    LEFT JOIN companies c ON o.company_id = c.id
                    WHERE o.id = ? AND o.company_id = ?
                ");
                $stmt->execute([$_GET['id'], $data['company_id']]);
                $office = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$office) {
                    sendResponse(404, [
                        'error' => true,
                        'message' => 'Office not found'
                    ]);
                }
                
                sendResponse(200, [
                    'success' => true,
                    'office' => $office
                ]);
            } else {
                // Get all offices
                $stmt = $conn->prepare("
                    SELECT o.*, c.name as company_name
                    FROM offices o
                    LEFT JOIN companies c ON o.company_id = c.id
                    WHERE o.company_id = ?
                    ORDER BY o.name ASC
                ");
                $stmt->execute([$data['company_id']]);
                $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendResponse(200, [
                    'success' => true,
                    'offices' => $offices
                ]);
            }
            break;
            
        case 'POST':
            // Create new office
            validateRequiredFields(['name', 'location'], $data);
            
            $stmt = $conn->prepare("
                INSERT INTO offices (company_id, name, location) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $data['company_id'],
                $data['name'],
                $data['location']
            ]);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Office created successfully',
                'office_id' => $conn->lastInsertId()
            ]);
            break;
            
        case 'PUT':
            // Update existing office
            validateRequiredFields(['id'], $data);
            
            // Verify office belongs to company
            $stmt = $conn->prepare("SELECT id FROM offices WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Office not found or does not belong to your company'
                ]);
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['name'])) {
                $updates[] = "name = ?";
                $params[] = $data['name'];
            }
            if (isset($data['location'])) {
                $updates[] = "location = ?";
                $params[] = $data['location'];
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
                UPDATE offices 
                SET " . implode(", ", $updates) . "
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute($params);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Office updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete office
            validateRequiredFields(['id'], $data);
            
            // Verify office belongs to company
            $stmt = $conn->prepare("SELECT id FROM offices WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Office not found or does not belong to your company'
                ]);
            }
            
            // Check if office has any users
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE office_id = ?");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete office with associated users'
                ]);
            }
            
            // Delete office
            $stmt = $conn->prepare("DELETE FROM offices WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $data['company_id']]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Office deleted successfully'
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