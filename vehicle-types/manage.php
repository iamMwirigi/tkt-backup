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

    // Verify company exists
    $stmt = $conn->prepare("SELECT id FROM companies WHERE id = ?");
    $stmt->execute([$data['company_id']]);
    if (!$stmt->fetch()) {
        sendResponse(404, [
            'error' => true,
            'message' => 'Company not found'
        ]);
    }
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get vehicle types
            if (isset($_GET['id'])) {
                // Get single vehicle type
                $stmt = $conn->prepare("
                    SELECT vt.*, c.name as company_name 
                    FROM vehicle_types vt
                    LEFT JOIN companies c ON vt.company_id = c.id
                    WHERE vt.id = ? AND vt.company_id = ?
                ");
                $stmt->execute([$_GET['id'], $data['company_id']]);
                $vehicle_type = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$vehicle_type) {
                    sendResponse(404, [
                        'error' => true,
                        'message' => 'Vehicle type not found or does not belong to your company'
                    ]);
                }
                
                sendResponse(200, [
                    'success' => true,
                    'vehicle_type' => $vehicle_type
                ]);
            } else {
                // Get all vehicle types with filters
                $where = ['vt.company_id = ?'];
                $params = [$data['company_id']];
                
                if (isset($_GET['name'])) {
                    $where[] = 'vt.name LIKE ?';
                    $params[] = '%' . $_GET['name'] . '%';
                }
                
                $where_clause = implode(' AND ', $where);
                
                $stmt = $conn->prepare("
                    SELECT vt.*, c.name as company_name 
                    FROM vehicle_types vt
                    LEFT JOIN companies c ON vt.company_id = c.id
                    WHERE $where_clause
                    ORDER BY vt.name ASC
                ");
                $stmt->execute($params);
                $vehicle_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendResponse(200, [
                    'success' => true,
                    'vehicle_types' => $vehicle_types
                ]);
            }
            break;
            
        case 'POST':
            // Create new vehicle type
            validateRequiredFields(['name', 'description', 'seats'], $data);
            
            // Check if vehicle type name already exists for this company
            $stmt = $conn->prepare("SELECT id FROM vehicle_types WHERE name = ? AND company_id = ?");
            $stmt->execute([$data['name'], $data['company_id']]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Vehicle type with this name already exists in your company'
                ]);
            }
            
            $stmt = $conn->prepare("
                INSERT INTO vehicle_types (
                    name, description, seats, company_id
                ) VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['seats'],
                $data['company_id']
            ]);
            
            $vehicle_type_id = $conn->lastInsertId();
            
            // Get created vehicle type
            $stmt = $conn->prepare("
                SELECT vt.*, c.name as company_name 
                FROM vehicle_types vt
                LEFT JOIN companies c ON vt.company_id = c.id
                WHERE vt.id = ?
            ");
            $stmt->execute([$vehicle_type_id]);
            $vehicle_type = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Vehicle type created successfully',
                'vehicle_type' => $vehicle_type
            ]);
            break;
            
        case 'PUT':
            // Update vehicle type
            validateRequiredFields(['id'], $data);
            
            // Verify vehicle type exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM vehicle_types WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $data['company_id']]);
            $vehicle_type = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehicle_type) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle type not found or does not belong to your company'
                ]);
            }
            
            // If name is being updated, check if new name already exists
            if (isset($data['name']) && $data['name'] !== $vehicle_type['name']) {
                $stmt = $conn->prepare("SELECT id FROM vehicle_types WHERE name = ? AND company_id = ? AND id != ?");
                $stmt->execute([$data['name'], $data['company_id'], $data['id']]);
                if ($stmt->fetch()) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => 'Vehicle type with this name already exists in your company'
                    ]);
                }
            }
            
            // Build update query based on provided fields
            $updates = [];
            $params = [];
            
            $allowed_fields = [
                'name',
                'description',
                'seats'
            ];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'No valid fields to update'
                ]);
            }
            
            $params[] = $data['id'];
            $params[] = $data['company_id'];
            
            $stmt = $conn->prepare("
                UPDATE vehicle_types 
                SET " . implode(', ', $updates) . "
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute($params);
            
            // Get updated vehicle type
            $stmt = $conn->prepare("
                SELECT vt.*, c.name as company_name 
                FROM vehicle_types vt
                LEFT JOIN companies c ON vt.company_id = c.id
                WHERE vt.id = ?
            ");
            $stmt->execute([$data['id']]);
            $vehicle_type = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Vehicle type updated successfully',
                'vehicle_type' => $vehicle_type
            ]);
            break;
            
        case 'DELETE':
            // Delete vehicle type
            validateRequiredFields(['id'], $data);
            
            // Verify vehicle type exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM vehicle_types WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $data['company_id']]);
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
                WHERE vehicle_type = ? AND company_id = ?
            ");
            $stmt->execute([$vehicle_type['name'], $data['company_id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete vehicle type that is in use by vehicles'
                ]);
            }
            
            $stmt = $conn->prepare("DELETE FROM vehicle_types WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $data['company_id']]);
            
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