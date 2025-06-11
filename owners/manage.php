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
            error_log("Dotenv Error in owners/manage.php: " . $e->getMessage());
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
            // Create new owner with vehicles
            validateRequiredFields(['name', 'phone_number', 'vehicles'], $data);
            
            // Validate vehicles array
            if (!is_array($data['vehicles']) || empty($data['vehicles'])) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'At least one vehicle is required'
                ]);
            }
            
            // Check if phone number already exists
            $stmt = $conn->prepare("SELECT id FROM vehicle_owners WHERE phone = ? AND company_id = ?");
            $stmt->execute([$data['phone_number'], $company_id]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Phone number already registered'
                ]);
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Create owner
                $stmt = $conn->prepare("
                    INSERT INTO vehicle_owners (
                        name, phone, company_id
                    ) VALUES (?, ?, ?)
                ");
                
                $stmt->execute([
                    $data['name'],
                    $data['phone_number'],
                    $company_id
                ]);
                
                $owner_id = $conn->lastInsertId();
                
                // Process each vehicle
                $vehicles = [];
                foreach ($data['vehicles'] as $vehicle) {
                    // Validate required vehicle fields
                    if (empty($vehicle['plate_number']) || empty($vehicle['vehicle_type']) || empty($vehicle['seats'])) {
                        throw new Exception("Each vehicle must have plate_number, vehicle_type, and seats");
                    }
                    
                    // Validate plate number format (e.g., KBR 123A)
                    if (!preg_match('/^[A-Z]{3}\s\d{3}[A-Z]$/', $vehicle['plate_number'])) {
                        throw new Exception("Invalid plate number format for {$vehicle['plate_number']}. Use format: KBR 123A");
                    }
                    
                    // Check if plate number already exists
                    $stmt = $conn->prepare("SELECT id FROM vehicles WHERE plate_number = ?");
                    $stmt->execute([$vehicle['plate_number']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Plate number {$vehicle['plate_number']} already registered");
                    }
                    
                    // Validate vehicle type
                    $allowed_types = ['Bus', 'Van', 'Car', 'Truck'];
                    if (!in_array($vehicle['vehicle_type'], $allowed_types)) {
                        throw new Exception("Invalid vehicle type for {$vehicle['plate_number']}. Allowed types: " . implode(', ', $allowed_types));
                    }
                    
                    // Validate seats
                    if (!is_numeric($vehicle['seats']) || $vehicle['seats'] < 1) {
                        throw new Exception("Invalid number of seats for {$vehicle['plate_number']}");
                    }
                    
                    // Create vehicle
                    $stmt = $conn->prepare("
                        INSERT INTO vehicles (
                            plate_number, vehicle_type, company_id, owner_id
                        ) VALUES (?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $vehicle['plate_number'],
                        $vehicle['vehicle_type'],
                        $company_id,
                        $owner_id
                    ]);
                    
                    $vehicle_id = $conn->lastInsertId();
                    $vehicles[] = [
                        'id' => $vehicle_id,
                        'plate_number' => $vehicle['plate_number'],
                        'vehicle_type' => $vehicle['vehicle_type'],
                        'seats' => $vehicle['seats']
                    ];
                }
                
                // Get created owner with vehicles
                $stmt = $conn->prepare("
                    SELECT 
                        vo.*,
                        JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', v.id,
                                'plate_number', v.plate_number,
                                'vehicle_type', v.vehicle_type,
                                'created_at', v.created_at
                            )
                        ) as vehicles
                    FROM vehicle_owners vo
                    LEFT JOIN vehicles v ON vo.id = v.owner_id
                    WHERE vo.id = ?
                    GROUP BY vo.id
                ");
                $stmt->execute([$owner_id]);
                $owner = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Parse vehicles JSON
                $owner['vehicles'] = json_decode($owner['vehicles'], true);
                
                $conn->commit();
                
                sendResponse(201, [
                    'success' => true,
                    'message' => 'Owner and vehicles created successfully',
                    'owner' => $owner
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'PUT':
            // Update owner
            validateRequiredFields(['id'], $data);
            
            // Verify owner exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM vehicle_owners WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            $owner = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$owner) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Owner not found or does not belong to your company'
                ]);
            }
            
            // Check if new phone number already exists
            if (isset($data['phone_number']) && $data['phone_number'] !== $owner['phone']) {
                $stmt = $conn->prepare("SELECT id FROM vehicle_owners WHERE phone = ? AND company_id = ? AND id != ?");
                $stmt->execute([$data['phone_number'], $company_id, $data['id']]);
                if ($stmt->fetch()) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => 'Phone number already registered to another owner'
                    ]);
                }
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Build update query based on provided fields
                $updates = [];
                $params = [];
                
                $allowed_fields = [
                    'name',
                    'phone_number'
                ];
                
                foreach ($allowed_fields as $field) {
                    if (isset($data[$field])) {
                        $updates[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
                
                if (!empty($updates)) {
                    $params[] = $data['id'];
                    $params[] = $company_id;
                    
                    $stmt = $conn->prepare("
                        UPDATE vehicle_owners 
                        SET " . implode(", ", $updates) . "
                        WHERE id = ? AND company_id = ?
                    ");
                    $stmt->execute($params);
                }
                
                // If new vehicles are provided, add them
                if (isset($data['vehicles']) && is_array($data['vehicles'])) {
                    foreach ($data['vehicles'] as $vehicle) {
                        // Validate required vehicle fields
                        if (empty($vehicle['plate_number']) || empty($vehicle['vehicle_type']) || empty($vehicle['seats'])) {
                            throw new Exception("Each vehicle must have plate_number, vehicle_type, and seats");
                        }
                        
                        // Validate plate number format
                        if (!preg_match('/^[A-Z]{3}\s\d{3}[A-Z]$/', $vehicle['plate_number'])) {
                            throw new Exception("Invalid plate number format for {$vehicle['plate_number']}. Use format: KBR 123A");
                        }
                        
                        // Check if plate number already exists
                        $stmt = $conn->prepare("SELECT id FROM vehicles WHERE plate_number = ?");
                        $stmt->execute([$vehicle['plate_number']]);
                        if ($stmt->fetch()) {
                            throw new Exception("Plate number {$vehicle['plate_number']} already registered");
                        }
                        
                        // Validate vehicle type
                        $allowed_types = ['Bus', 'Van', 'Car', 'Truck'];
                        if (!in_array($vehicle['vehicle_type'], $allowed_types)) {
                            throw new Exception("Invalid vehicle type for {$vehicle['plate_number']}. Allowed types: " . implode(', ', $allowed_types));
                        }
                        
                        // Validate seats
                        if (!is_numeric($vehicle['seats']) || $vehicle['seats'] < 1) {
                            throw new Exception("Invalid number of seats for {$vehicle['plate_number']}");
                        }
                        
                        // Create vehicle
                        $stmt = $conn->prepare("
                            INSERT INTO vehicles (
                                plate_number, vehicle_type, company_id, owner_id
                            ) VALUES (?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $vehicle['plate_number'],
                            $vehicle['vehicle_type'],
                            $company_id,
                            $data['id']
                        ]);
                    }
                }
                
                // Get updated owner with vehicles
                $stmt = $conn->prepare("
                    SELECT 
                        vo.*,
                        JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', v.id,
                                'plate_number', v.plate_number,
                                'vehicle_type', v.vehicle_type,
                                'created_at', v.created_at
                            )
                        ) as vehicles
                    FROM vehicle_owners vo
                    LEFT JOIN vehicles v ON vo.id = v.owner_id
                    WHERE vo.id = ?
                    GROUP BY vo.id
                ");
                $stmt->execute([$data['id']]);
                $updated_owner = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Parse vehicles JSON
                $updated_owner['vehicles'] = json_decode($updated_owner['vehicles'], true);
                
                $conn->commit();
                
                sendResponse(200, [
                    'success' => true,
                    'message' => 'Owner updated successfully',
                    'owner' => $updated_owner
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'DELETE':
            // Delete owner
            validateRequiredFields(['id'], $data);
            
            // Verify owner exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM vehicle_owners WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            $owner = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$owner) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Owner not found or does not belong to your company'
                ]);
            }
            
            // Check if owner has any vehicles with active trips
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM vehicles v
                JOIN trips t ON v.id = t.vehicle_id
                WHERE v.owner_id = ? AND t.status IN ('pending', 'in_progress')
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete owner with vehicles that have active trips'
                ]);
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Delete owner's vehicles
                $stmt = $conn->prepare("DELETE FROM vehicles WHERE owner_id = ? AND company_id = ?");
                $stmt->execute([$data['id'], $company_id]);
                
                // Delete owner
                $stmt = $conn->prepare("DELETE FROM vehicle_owners WHERE id = ? AND company_id = ?");
                $stmt->execute([$data['id'], $company_id]);
                
                $conn->commit();
                
                sendResponse(200, [
                    'success' => true,
                    'message' => 'Owner and their vehicles deleted successfully'
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
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