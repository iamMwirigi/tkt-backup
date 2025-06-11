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
            error_log("Dotenv Error in routes/manage.php: " . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Authentication disabled for testing
// Check if user is logged in
// $auth = checkAuth();
// $user_id = $auth['user_id'];
// $company_id = $auth['company_id'];
// $user_role = $_SESSION['role'] ?? '';

// Use dummy values for testing
$user_id = 1;
$company_id = 1;
$user_role = 'admin';

// Only admin users can perform CRUD operations
/*
if ($user_role !== 'admin') {
    sendResponse(403, [
        'error' => true,
        'message' => 'Only admin users can manage routes'
    ]);
}
*/

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            // Create new route
            validateRequiredFields(['name'], $data);
            
            $stmt = $conn->prepare("
                INSERT INTO routes (company_id, name, description) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $company_id,
                $data['name'],
                $data['description'] ?? null
            ]);
            
            $route_id = $conn->lastInsertId();
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Route created successfully',
                'route' => [
                    'id' => $route_id,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'company_id' => $company_id,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);
            break;
            
        case 'PUT':
            // Update existing route
            validateRequiredFields(['id', 'name'], $data);
            
            // Verify route belongs to company
            $stmt = $conn->prepare("SELECT id FROM routes WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Route not found or does not belong to your company'
                ]);
            }
            
            $stmt = $conn->prepare("
                UPDATE routes 
                SET name = ?, description = ? 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['id'],
                $company_id
            ]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Route updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete route
            validateRequiredFields(['id'], $data);
            
            // Verify route belongs to company
            $stmt = $conn->prepare("SELECT id FROM routes WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Route not found or does not belong to your company'
                ]);
            }
            
            // Check if route is in use
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM trips 
                WHERE route_id = ? AND status != 'completed'
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete route that has active trips'
                ]);
            }
            
            // Delete route (cascade will handle destinations and fares)
            $stmt = $conn->prepare("DELETE FROM routes WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Route deleted successfully'
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