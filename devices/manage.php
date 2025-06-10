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
            error_log("Dotenv Error in devices/manage.php: " . $e->getMessage());
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
            // Create new device
            validateRequiredFields(['device_uuid', 'device_name'], $data);
            
            // Check if device UUID already exists
            $stmt = $conn->prepare("SELECT id FROM devices WHERE device_uuid = ?");
            $stmt->execute([$data['device_uuid']]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Device UUID already exists'
                ]);
            }
            
            $stmt = $conn->prepare("
                INSERT INTO devices (company_id, device_uuid, device_name, is_active) 
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([
                $company_id,
                $data['device_uuid'],
                $data['device_name']
            ]);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Device created successfully',
                'device' => [
                    'id' => $conn->lastInsertId(),
                    'device_uuid' => $data['device_uuid'],
                    'device_name' => $data['device_name'],
                    'status' => 'active',
                    'registered_at' => date('Y-m-d H:i:s')
                ]
            ]);
            break;
            
        case 'PUT':
            // Update existing device
            validateRequiredFields(['id'], $data);
            
            // Verify device belongs to company
            $stmt = $conn->prepare("SELECT id FROM devices WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Device not found or does not belong to your company'
                ]);
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['device_uuid'])) {
                // Check if new UUID already exists
                $stmt = $conn->prepare("SELECT id FROM devices WHERE device_uuid = ? AND id != ?");
                $stmt->execute([$data['device_uuid'], $data['id']]);
                if ($stmt->fetch()) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => 'Device UUID already exists'
                    ]);
                }
                $updates[] = "device_uuid = ?";
                $params[] = $data['device_uuid'];
            }
            if (isset($data['device_name'])) {
                $updates[] = "device_name = ?";
                $params[] = $data['device_name'];
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
                UPDATE devices 
                SET " . implode(", ", $updates) . "
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute($params);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Device updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete device
            validateRequiredFields(['id'], $data);
            
            // Verify device belongs to company
            $stmt = $conn->prepare("SELECT id FROM devices WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Device not found or does not belong to your company'
                ]);
            }
            
            // Delete device
            $stmt = $conn->prepare("DELETE FROM devices WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Device deleted successfully'
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