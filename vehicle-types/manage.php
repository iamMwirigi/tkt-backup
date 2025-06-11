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
            error_log("Dotenv Error in vehicle-types/manage.php: " . $e->getMessage());
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
            // Create new vehicle type
            validateRequiredFields(['name'], $data);
            
            // Check if vehicle type already exists for company
            $stmt = $conn->prepare("SELECT id FROM vehicle_types WHERE name = ? AND company_id = ?");
            $stmt->execute([$data['name'], $company_id]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Vehicle type already exists'
                ]);
            }
            
            // Create vehicle type
            $stmt = $conn->prepare("
                INSERT INTO vehicle_types (
                    name, company_id
                ) VALUES (?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $company_id
            ]);
            
            $type_id = $conn->lastInsertId();
            
            // Get created vehicle type
            $stmt = $conn->prepare("
                SELECT * FROM vehicle_types WHERE id = ?
            ");
            $stmt->execute([$type_id]);
            $vehicle_type = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Vehicle type created successfully',
                'vehicle_type' => $vehicle_type
            ]);
            break;
            
        case 'PUT':
            // Update vehicle type
            validateRequiredFields(['id', 'name'], $data);
            
            // Verify vehicle type exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM vehicle_types WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            $vehicle_type = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehicle_type) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle type not found or does not belong to your company'
                ]);
            }
            
            // Check if new name already exists
            $stmt = $conn->prepare("SELECT id FROM vehicle_types WHERE name = ? AND company_id = ? AND id != ?");
            $stmt->execute([$data['name'], $company_id, $data['id']]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Vehicle type name already exists'
                ]);
            }
            
            // Update vehicle type
            $stmt = $conn->prepare("
                UPDATE vehicle_types 
                SET name = ?
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['id'],
                $company_id
            ]);
            
            // Get updated vehicle type
            $stmt = $conn->prepare("
                SELECT * FROM vehicle_types WHERE id = ?
            ");
            $stmt->execute([$data['id']]);
            $updated_type = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Vehicle type updated successfully',
                'vehicle_type' => $updated_type
            ]);
            break;
            
        case 'DELETE':
            // Delete vehicle type
            validateRequiredFields(['id'], $data);
            
            // Verify vehicle type exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM vehicle_types WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            $vehicle_type = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehicle_type) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle type not found or does not belong to your company'
                ]);
            }
            
            // Check if vehicle type is in use
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM vehicles 
                WHERE vehicle_type_id = ?
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete vehicle type that is in use'
                ]);
            }
            
            // Delete vehicle type
            $stmt = $conn->prepare("DELETE FROM vehicle_types WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Vehicle type deleted successfully'
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