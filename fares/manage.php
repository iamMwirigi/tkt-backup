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
            error_log("Dotenv Error in fares/manage.php: " . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Use dummy values for testing
$user_id = 1;
$company_id = 1;
$user_role = 'admin';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            // Create new fare
            validateRequiredFields(['destination_id', 'label', 'amount'], $data);
            
            // Verify destination exists and belongs to company
            $stmt = $conn->prepare("
                SELECT d.id 
                FROM destinations d
                JOIN routes r ON d.route_id = r.id
                WHERE d.id = ? AND r.company_id = ?
            ");
            $stmt->execute([$data['destination_id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Destination not found or does not belong to your company'
                ]);
            }
            
            $stmt = $conn->prepare("
                INSERT INTO fares (destination_id, label, amount) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $data['destination_id'],
                $data['label'],
                $data['amount']
            ]);
            
            $fare_id = $conn->lastInsertId();
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Fare created successfully',
                'fare' => [
                    'id' => $fare_id,
                    'destination_id' => $data['destination_id'],
                    'label' => $data['label'],
                    'amount' => $data['amount'],
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);
            break;
            
        case 'PUT':
            // Update existing fare
            validateRequiredFields(['id', 'label', 'amount'], $data);
            
            // Verify fare exists and belongs to company
            $stmt = $conn->prepare("
                SELECT f.id 
                FROM fares f
                JOIN destinations d ON f.destination_id = d.id
                JOIN routes r ON d.route_id = r.id
                WHERE f.id = ? AND r.company_id = ?
            ");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Fare not found or does not belong to your company'
                ]);
            }
            
            $stmt = $conn->prepare("
                UPDATE fares 
                SET label = ?, amount = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $data['label'],
                $data['amount'],
                $data['id']
            ]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Fare updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete fare
            validateRequiredFields(['id'], $data);
            
            // Verify fare exists and belongs to company
            $stmt = $conn->prepare("
                SELECT f.id 
                FROM fares f
                JOIN destinations d ON f.destination_id = d.id
                JOIN routes r ON d.route_id = r.id
                WHERE f.id = ? AND r.company_id = ?
            ");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Fare not found or does not belong to your company'
                ]);
            }
            
            // Check if fare is in use
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM bookings 
                WHERE fare_amount = (
                    SELECT amount 
                    FROM fares 
                    WHERE id = ?
                )
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete fare that is in use by bookings'
                ]);
            }
            
            // Delete fare
            $stmt = $conn->prepare("DELETE FROM fares WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Fare deleted successfully'
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
?> 