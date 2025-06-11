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
            
            // Validate plate number format (e.g., KBR 123A)
            if (!preg_match('/^[A-Z]{3}\s\d{3}[A-Z]$/', $data['plate_number'])) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Invalid plate number format. Use format: KBR 123A'
                ]);
            }
            
            // Check if plate number already exists
            $stmt = $conn->prepare("SELECT id FROM vehicles WHERE plate_number = ?");
            $stmt->execute([$data['plate_number']]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Plate number already registered'
                ]);
            }
            
            // Validate vehicle type
            $allowed_types = ['Bus', 'Van', 'Car', 'Truck'];
            if (!in_array($data['vehicle_type'], $allowed_types)) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Invalid vehicle type. Allowed types: ' . implode(', ', $allowed_types)
                ]);
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Create vehicle
                $stmt = $conn->prepare("
                    INSERT INTO vehicles (
                        plate_number, vehicle_type, company_id
                    ) VALUES (?, ?, ?)
                ");
                
                $stmt->execute([
                    $data['plate_number'],
                    $data['vehicle_type'],
                    $company_id
                ]);
                
                $vehicle_id = $conn->lastInsertId();
                
                // If owner_id is provided, verify and assign
                if (!empty($data['owner_id'])) {
                    // Verify owner exists and belongs to company
                    $stmt = $conn->prepare("
                        SELECT id FROM vehicle_owners 
                        WHERE id = ? AND company_id = ?
                    ");
                    $stmt->execute([$data['owner_id'], $company_id]);
                    if (!$stmt->fetch()) {
                        throw new Exception("Owner not found or does not belong to your company");
                    }
                    
                    // Update vehicle owner
                    $stmt = $conn->prepare("
                        UPDATE vehicles 
                        SET owner_id = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$data['owner_id'], $vehicle_id]);
                }
                
                // Get created vehicle with owner info
                $stmt = $conn->prepare("
                    SELECT 
                        v.*,
                        vo.name as owner_name,
                        vo.phone as owner_phone
                    FROM vehicles v
                    LEFT JOIN vehicle_owners vo ON v.owner_id = vo.id
                    WHERE v.id = ?
                ");
                $stmt->execute([$vehicle_id]);
                $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $conn->commit();
                
                sendResponse(201, [
                    'success' => true,
                    'message' => 'Vehicle created successfully',
                    'vehicle' => $vehicle
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'PUT':
            // Update vehicle
            validateRequiredFields(['id'], $data);
            
            // Verify vehicle exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM vehicles WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehicle) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle not found or does not belong to your company'
                ]);
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Build update query based on provided fields
                $updates = [];
                $params = [];
                
                $allowed_fields = [
                    'plate_number',
                    'vehicle_type'
                ];
                
                foreach ($allowed_fields as $field) {
                    if (isset($data[$field])) {
                        // Validate plate number format if being updated
                        if ($field === 'plate_number') {
                            if (!preg_match('/^[A-Z]{3}\s\d{3}[A-Z]$/', $data['plate_number'])) {
                                throw new Exception('Invalid plate number format. Use format: KBR 123A');
                            }
                            
                            // Check if new plate number already exists
                            $stmt = $conn->prepare("
                                SELECT id FROM vehicles 
                                WHERE plate_number = ? AND id != ?
                            ");
                            $stmt->execute([$data['plate_number'], $data['id']]);
                            if ($stmt->fetch()) {
                                throw new Exception('Plate number already registered to another vehicle');
                            }
                        }
                        
                        // Validate vehicle type if being updated
                        if ($field === 'vehicle_type') {
                            $allowed_types = ['Bus', 'Van', 'Car', 'Truck'];
                            if (!in_array($data['vehicle_type'], $allowed_types)) {
                                throw new Exception('Invalid vehicle type. Allowed types: ' . implode(', ', $allowed_types));
                            }
                        }
                        
                        $updates[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
                
                if (!empty($updates)) {
                    $params[] = $data['id'];
                    $params[] = $company_id;
                    
                    $stmt = $conn->prepare("
                        UPDATE vehicles 
                        SET " . implode(", ", $updates) . "
                        WHERE id = ? AND company_id = ?
                    ");
                    $stmt->execute($params);
                }
                
                // If owner_id is provided, update owner
                if (isset($data['owner_id'])) {
                    if (empty($data['owner_id'])) {
                        // Remove owner
                        $stmt = $conn->prepare("
                            UPDATE vehicles 
                            SET owner_id = NULL 
                            WHERE id = ?
                        ");
                        $stmt->execute([$data['id']]);
                    } else {
                        // Verify new owner exists and belongs to company
                        $stmt = $conn->prepare("
                            SELECT id FROM vehicle_owners 
                            WHERE id = ? AND company_id = ?
                        ");
                        $stmt->execute([$data['owner_id'], $company_id]);
                        if (!$stmt->fetch()) {
                            throw new Exception("Owner not found or does not belong to your company");
                        }
                        
                        // Update vehicle owner
                        $stmt = $conn->prepare("
                            UPDATE vehicles 
                            SET owner_id = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$data['owner_id'], $data['id']]);
                    }
                }
                
                // Get updated vehicle with owner info
                $stmt = $conn->prepare("
                    SELECT 
                        v.*,
                        vo.name as owner_name,
                        vo.phone as owner_phone
                    FROM vehicles v
                    LEFT JOIN vehicle_owners vo ON v.owner_id = vo.id
                    WHERE v.id = ?
                ");
                $stmt->execute([$data['id']]);
                $updated_vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $conn->commit();
                
                sendResponse(200, [
                    'success' => true,
                    'message' => 'Vehicle updated successfully',
                    'vehicle' => $updated_vehicle
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'DELETE':
            // Delete vehicle
            validateRequiredFields(['id'], $data);
            
            // Verify vehicle exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM vehicles WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehicle) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle not found or does not belong to your company'
                ]);
            }
            
            // Check if vehicle has any active trips
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM trips 
                WHERE vehicle_id = ? AND status IN ('pending', 'in_progress')
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