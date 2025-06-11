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
            validateRequiredFields(['route_id', 'name', 'stop_order', 'fares'], $data);
            
            // Verify route exists and belongs to company
            $stmt = $conn->prepare("SELECT id FROM routes WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['route_id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Route not found or does not belong to your company'
                ]);
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Create destination
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
                
                // Create fares
                if (!isset($data['fares']) || !is_array($data['fares']) || empty($data['fares'])) {
                    throw new Exception('At least one fare is required for the destination');
                }
                
                foreach ($data['fares'] as $fare) {
                    if (!isset($fare['label']) || !isset($fare['amount'])) {
                        throw new Exception('Each fare must have a label and amount');
                    }
                    
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
                
                // Commit transaction
                $conn->commit();
                
                // Get created destination with fares
                $stmt = $conn->prepare("
                    SELECT d.*, 
                           JSON_ARRAYAGG(
                               JSON_OBJECT(
                                   'id', f.id,
                                   'label', f.label,
                                   'amount', f.amount
                               )
                           ) as fares
                    FROM destinations d
                    LEFT JOIN fares f ON d.id = f.destination_id
                    WHERE d.id = ?
                    GROUP BY d.id
                ");
                $stmt->execute([$destination_id]);
                $destination = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Parse fares JSON
                $destination['fares'] = json_decode($destination['fares'], true);
                
                sendResponse(201, [
                    'success' => true,
                    'message' => 'Destination created successfully',
                    'destination' => $destination
                ]);
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'PUT':
            // Update existing destination
            validateRequiredFields(['id', 'name', 'stop_order'], $data);
            
            // Verify destination exists and belongs to company
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
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Update destination
                $stmt = $conn->prepare("
                    UPDATE destinations 
                    SET name = ?, stop_order = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $data['name'],
                    $data['stop_order'],
                    $data['id']
                ]);
                
                // Update fares if provided
                if (isset($data['fares']) && is_array($data['fares'])) {
                    // Delete existing fares
                    $stmt = $conn->prepare("DELETE FROM fares WHERE destination_id = ?");
                    $stmt->execute([$data['id']]);
                    
                    // Create new fares
                    foreach ($data['fares'] as $fare) {
                        if (!isset($fare['label']) || !isset($fare['amount'])) {
                            throw new Exception('Each fare must have a label and amount');
                        }
                        
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
                
                // Commit transaction
                $conn->commit();
                
                sendResponse(200, [
                    'success' => true,
                    'message' => 'Destination updated successfully'
                ]);
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'DELETE':
            // Delete destination
            validateRequiredFields(['id'], $data);
            
            // Verify destination exists and belongs to company
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
            
            // Check if destination is in use
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM bookings 
                WHERE destination_id = ?
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete destination that has bookings'
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