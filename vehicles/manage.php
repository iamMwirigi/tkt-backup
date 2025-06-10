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
            error_log("Dotenv Error in vehicles/manage.php: " . $e->getMessage());
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
            // Create new vehicle
            validateRequiredFields(['plate_number', 'vehicle_type'], $data);
            
            // Check if plate number already exists
            $stmt = $conn->prepare("SELECT id FROM vehicles WHERE plate_number = ?");
            $stmt->execute([$data['plate_number']]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Plate number already exists'
                ]);
            }
            
            $stmt = $conn->prepare("
                INSERT INTO vehicles (company_id, plate_number, vehicle_type, owner_id) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $company_id,
                $data['plate_number'],
                $data['vehicle_type'],
                $data['owner_id'] ?? null
            ]);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Vehicle created successfully',
                'vehicle_id' => $conn->lastInsertId()
            ]);
            break;
            
        case 'PUT':
            // Update existing vehicle
            validateRequiredFields(['id'], $data);
            
            // Verify vehicle belongs to company
            $stmt = $conn->prepare("SELECT id FROM vehicles WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle not found or does not belong to your company'
                ]);
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['plate_number'])) {
                // Check if new plate number already exists
                $stmt = $conn->prepare("SELECT id FROM vehicles WHERE plate_number = ? AND id != ?");
                $stmt->execute([$data['plate_number'], $data['id']]);
                if ($stmt->fetch()) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => 'Plate number already exists'
                    ]);
                }
                $updates[] = "plate_number = ?";
                $params[] = $data['plate_number'];
            }
            if (isset($data['vehicle_type'])) {
                $updates[] = "vehicle_type = ?";
                $params[] = $data['vehicle_type'];
            }
            if (isset($data['owner_id'])) {
                $updates[] = "owner_id = ?";
                $params[] = $data['owner_id'];
            }
            
            if (empty($updates)) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'No fields to update'
                ]);
            }
            
            $params[] = $data['id'];
            $params[] = $company_id;
            
            $stmt = $conn->prepare("
                UPDATE vehicles 
                SET " . implode(", ", $updates) . "
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute($params);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Vehicle updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete vehicle
            validateRequiredFields(['id'], $data);
            
            // Verify vehicle belongs to company
            $stmt = $conn->prepare("SELECT id FROM vehicles WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle not found or does not belong to your company'
                ]);
            }
            
            // Check if vehicle has any active trips
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM trips 
                WHERE vehicle_id = ? AND status != 'completed'
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete vehicle with active trips'
                ]);
            }
            
            // Delete vehicle
            $stmt = $conn->prepare("DELETE FROM vehicles WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Vehicle deleted successfully'
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