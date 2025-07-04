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
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get vehicles
            if (isset($_GET['id'])) {
                // Get single vehicle
                $stmt = $conn->prepare("
                    SELECT 
                        v.*,
                        vo.name as owner_name,
                        vo.phone as owner_phone,
                        c.name as company_name,
                        vt.seats as vehicle_type_seats
                    FROM vehicles v
                    LEFT JOIN vehicle_owners vo ON v.owner_id = vo.id
                    LEFT JOIN companies c ON v.company_id = c.id
                    LEFT JOIN vehicle_types vt ON v.vehicle_type = vt.name AND v.company_id = vt.company_id
                    WHERE v.id = ? AND v.company_id = ?
                ");
                $stmt->execute([$_GET['id'], $data['company_id']]);
                $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$vehicle) {
                    sendResponse(404, [
                        'error' => true,
                        'message' => 'Vehicle not found'
                    ]);
                }
                
                // Remove unwanted fields from vehicle
                unset($vehicle['owner_id'], $vehicle['owner_name'], $vehicle['owner_phone'], $vehicle['vehicle_type_id']);
                sendResponse(200, [
                    'success' => true,
                    'vehicle' => $vehicle
                ]);
            } else {
                // Get all vehicles with filters
                $where = ['v.company_id = ?'];
                $params = [$data['company_id']];
                
                if (isset($_GET['plate_number'])) {
                    $where[] = 'v.plate_number LIKE ?';
                    $params[] = '%' . $_GET['plate_number'] . '%';
                }
                
                if (isset($_GET['vehicle_type'])) {
                    $where[] = 'v.vehicle_type = ?';
                    $params[] = $_GET['vehicle_type'];
                }
                
                if (isset($_GET['owner_id'])) {
                    $where[] = 'v.owner_id = ?';
                    $params[] = $_GET['owner_id'];
                }
                
                $where_clause = implode(' AND ', $where);
                
                $stmt = $conn->prepare("
                    SELECT 
                        v.*,
                        vo.name as owner_name,
                        vo.phone as owner_phone,
                        c.name as company_name,
                        vt.seats as vehicle_type_seats
                    FROM vehicles v
                    LEFT JOIN vehicle_owners vo ON v.owner_id = vo.id
                    LEFT JOIN companies c ON v.company_id = c.id
                    LEFT JOIN vehicle_types vt ON v.vehicle_type = vt.name AND v.company_id = vt.company_id
                    WHERE $where_clause
                    ORDER BY v.plate_number ASC
                ");
                $stmt->execute($params);
                $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // Remove unwanted fields from each vehicle
                $filtered_vehicles = array_map(function($vehicle) {
                    unset($vehicle['owner_id'], $vehicle['owner_name'], $vehicle['owner_phone'], $vehicle['vehicle_type_id']);
                    return $vehicle;
                }, $vehicles);
                sendResponse(200, [
                    'success' => true,
                    'vehicles' => $filtered_vehicles
                ]);
            }
            break;
            
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
            $stmt = $conn->prepare("SELECT id FROM vehicle_types WHERE name = ? AND company_id = ?");
            $stmt->execute([$data['vehicle_type'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Invalid vehicle type. Please select a valid vehicle type from your company\'s vehicle types.'
                ]);
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Create vehicle
                if (isset($data['vehicle_configuration_id'])) {
                    $stmt = $conn->prepare("
                        INSERT INTO vehicles (
                            plate_number, vehicle_type, company_id, vehicle_configuration_id
                        ) VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $data['plate_number'],
                        $data['vehicle_type'],
                        $data['company_id'],
                        $data['vehicle_configuration_id']
                    ]);
                } else {
                $stmt = $conn->prepare("
                    INSERT INTO vehicles (
                        plate_number, vehicle_type, company_id
                    ) VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $data['plate_number'],
                    $data['vehicle_type'],
                    $data['company_id']
                ]);
                }
                
                $vehicle_id = $conn->lastInsertId();
                
                // If owner_id is provided, verify and assign
                if (!empty($data['owner_id'])) {
                    // Verify owner exists and belongs to company
                    $stmt = $conn->prepare("
                        SELECT id FROM vehicle_owners 
                        WHERE id = ? AND company_id = ?
                    ");
                    $stmt->execute([$data['owner_id'], $data['company_id']]);
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

                // Automatically create seats if vehicle_configuration_id is provided
                if (isset($data['vehicle_configuration_id'])) {
                    // Fetch layout from configuration
                    $stmt = $conn->prepare("SELECT layout FROM vehicle_configurations WHERE id = ?");
                    $stmt->execute([$data['vehicle_configuration_id']]);
                    $config = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($config && !empty($config['layout'])) {
                        $layout = json_decode($config['layout'], true);
                        if (is_array($layout)) {
                            // Handle 2D array with 'label' as seat_number
                            foreach ($layout as $rowIndex => $row) {
                                if (is_array($row)) {
                                    foreach ($row as $colIndex => $seat) {
                                        if (is_array($seat) && !empty($seat['label'])) {
                                            $seat_number = $seat['label'];
                                            $position = 'row' . ($rowIndex + 1) . '-col' . ($colIndex + 1);
                                            $stmt_insert = $conn->prepare("
                                                INSERT INTO vehicle_seats (vehicle_id, seat_number, position, is_reserved)
                                                VALUES (?, ?, ?, ?)
                                            ");
                                            $stmt_insert->execute([
                                                $vehicle_id,
                                                $seat_number,
                                                $position,
                                                isset($seat['is_reserved']) ? $seat['is_reserved'] : 0
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Get created vehicle with owner info
                $stmt = $conn->prepare("
                    SELECT 
                        v.*,
                        vo.name as owner_name,
                        vo.phone as owner_phone,
                        vt.seats as vehicle_type_seats
                    FROM vehicles v
                    LEFT JOIN vehicle_owners vo ON v.owner_id = vo.id
                    LEFT JOIN vehicle_types vt ON v.vehicle_type = vt.name AND v.company_id = vt.company_id
                    WHERE v.id = ?
                ");
                $stmt->execute([$vehicle_id]);
                $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Remove unwanted fields from vehicle
                unset($vehicle['owner_id'], $vehicle['owner_name'], $vehicle['owner_phone'], $vehicle['vehicle_type_id']);
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
            $stmt->execute([$data['id'], $data['company_id']]);
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
                    'vehicle_type',
                    'vehicle_configuration_id'
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
                            $stmt = $conn->prepare("SELECT id FROM vehicle_types WHERE name = ? AND company_id = ?");
                            $stmt->execute([$data['vehicle_type'], $data['company_id']]);
                            if (!$stmt->fetch()) {
                                throw new Exception('Invalid vehicle type. Please select a valid vehicle type from your company\'s vehicle types.');
                            }
                        }
                        
                        $updates[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
                
                if (!empty($updates)) {
                    $params[] = $data['id'];
                    $params[] = $data['company_id'];
                    
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
                        $stmt->execute([$data['owner_id'], $data['company_id']]);
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
                        vo.phone as owner_phone,
                        vt.seats as vehicle_type_seats
                    FROM vehicles v
                    LEFT JOIN vehicle_owners vo ON v.owner_id = vo.id
                    LEFT JOIN vehicle_types vt ON v.vehicle_type = vt.name AND v.company_id = vt.company_id
                    WHERE v.id = ?
                ");
                $stmt->execute([$data['id']]);
                $updated_vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Remove unwanted fields from vehicle
                unset($updated_vehicle['owner_id'], $updated_vehicle['owner_name'], $updated_vehicle['owner_phone'], $updated_vehicle['vehicle_type_id']);
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
            $stmt = $conn->prepare("SELECT id FROM vehicles WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle not found or does not belong to your company'
                ]);
            }
            
            // Check if vehicle has any active bookings
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM bookings 
                WHERE vehicle_id = ? AND status != 'completed'
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete vehicle with active bookings'
                ]);
            }
            
            // Delete vehicle
            $stmt = $conn->prepare("DELETE FROM vehicles WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $data['company_id']]);
            
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