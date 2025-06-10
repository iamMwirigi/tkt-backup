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
            error_log("Dotenv Error in destinations/manage.php: " . $e->getMessage());
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
            // Create new destination
            validateRequiredFields(['route_id', 'name', 'stop_order'], $data);
            
            // Verify route belongs to company
            $stmt = $conn->prepare("SELECT id FROM routes WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['route_id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Route not found or does not belong to your company'
                ]);
            }
            
            $stmt = $conn->prepare("
                INSERT INTO destinations (route_id, name, stop_order) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $data['route_id'],
                $data['name'],
                $data['stop_order']
            ]);
            
            $destination_id = $conn->lastInsertId();
            
            // If fares are provided, create them
            if (isset($data['fares']) && is_array($data['fares'])) {
                foreach ($data['fares'] as $fare) {
                    $stmt = $conn->prepare("
                        INSERT INTO fares (destination_id, label, amount) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([
                        $destination_id,
                        $fare['label'],
                        $fare['amount']
                    ]);
                }
            }
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Destination created successfully',
                'destination_id' => $destination_id
            ]);
            break;
            
        case 'PUT':
            // Update existing destination
            validateRequiredFields(['id'], $data);
            
            // Verify destination belongs to company's route
            $stmt = $conn->prepare("
                SELECT d.id 
                FROM destinations d
                JOIN routes r ON d.route_id = r.id
                WHERE d.id = ? AND r.company_id = ?
            ");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Destination not found or does not belong to your company'
                ]);
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['name'])) {
                $updates[] = "name = ?";
                $params[] = $data['name'];
            }
            if (isset($data['stop_order'])) {
                $updates[] = "stop_order = ?";
                $params[] = $data['stop_order'];
            }
            
            if (empty($updates)) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'No fields to update'
                ]);
            }
            
            $params[] = $data['id'];
            
            $stmt = $conn->prepare("
                UPDATE destinations 
                SET " . implode(", ", $updates) . "
                WHERE id = ?
            ");
            $stmt->execute($params);
            
            // Update fares if provided
            if (isset($data['fares']) && is_array($data['fares'])) {
                // Delete existing fares
                $stmt = $conn->prepare("DELETE FROM fares WHERE destination_id = ?");
                $stmt->execute([$data['id']]);
                
                // Create new fares
                foreach ($data['fares'] as $fare) {
                    $stmt = $conn->prepare("
                        INSERT INTO fares (destination_id, label, amount) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([
                        $data['id'],
                        $fare['label'],
                        $fare['amount']
                    ]);
                }
            }
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Destination updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete destination
            validateRequiredFields(['id'], $data);
            
            // Verify destination belongs to company's route
            $stmt = $conn->prepare("
                SELECT d.id 
                FROM destinations d
                JOIN routes r ON d.route_id = r.id
                WHERE d.id = ? AND r.company_id = ?
            ");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Destination not found or does not belong to your company'
                ]);
            }
            
            // Check if destination has any active bookings
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM bookings b
                JOIN trips t ON b.trip_id = t.id
                JOIN routes r ON t.route_id = r.id
                JOIN destinations d ON d.route_id = r.id
                WHERE d.id = ? AND b.status != 'completed'
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete destination with active bookings'
                ]);
            }
            
            // Delete destination (cascade will handle fares)
            $stmt = $conn->prepare("DELETE FROM destinations WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Destination deleted successfully'
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