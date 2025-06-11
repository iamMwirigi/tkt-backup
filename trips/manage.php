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
            error_log("Dotenv Error in trips/manage.php: " . $e->getMessage());
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
            // Create new trip
            validateRequiredFields(['vehicle_id', 'route_id', 'driver_name', 'conductor_name', 'departure_time'], $data);
            
            // Verify vehicle exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM vehicles WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['vehicle_id'], $company_id]);
            $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehicle) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle not found or does not belong to your company'
                ]);
            }
            
            // Verify route exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM routes WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['route_id'], $company_id]);
            $route = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$route) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Route not found or does not belong to your company'
                ]);
            }
            
            // Get company prefix and route code
            $stmt = $conn->prepare("
                SELECT c.name as company_name, r.name as route_name 
                FROM companies c 
                JOIN routes r ON r.id = ? 
                WHERE c.id = ?
            ");
            $stmt->execute([$data['route_id'], $company_id]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$info) {
                throw new Exception("Could not get company or route information");
            }
            
            // Generate company prefix and route code
            $company_prefix = substr(preg_replace('/[^A-Z]/', '', strtoupper($info['company_name'])), 0, 3);
            $route_code = substr(preg_replace('/[^A-Z]/', '', strtoupper($info['route_name'])), 0, 3);
            
            // Generate trip code
            $trip_code = generateTripCode($company_prefix, $route_code, $data['departure_time'], $conn);
            
            // Create trip
            $stmt = $conn->prepare("
                INSERT INTO trips (
                    trip_code, company_id, vehicle_id, route_id,
                    driver_name, conductor_name, departure_time,
                    status, created_via
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'mobile')
            ");
            
            $stmt->execute([
                $trip_code,
                $company_id,
                $data['vehicle_id'],
                $data['route_id'],
                $data['driver_name'],
                $data['conductor_name'],
                $data['departure_time']
            ]);
            
            $trip_id = $conn->lastInsertId();
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Trip created successfully',
                'trip' => [
                    'id' => $trip_id,
                    'trip_code' => $trip_code,
                    'status' => 'pending'
                ]
            ]);
            break;
            
        case 'PUT':
            // Update trip
            validateRequiredFields(['id'], $data);
            
            // Verify trip exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM trips WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$trip) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Trip not found or does not belong to your company'
                ]);
            }
            
            // Build update query based on provided fields
            $updates = [];
            $params = [];
            
            $allowed_fields = [
                'driver_name',
                'conductor_name',
                'departure_time',
                'status'
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
            $params[] = $company_id;
            
            $stmt = $conn->prepare("
                UPDATE trips 
                SET " . implode(", ", $updates) . "
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute($params);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Trip updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete trip
            validateRequiredFields(['id'], $data);
            
            // Verify trip exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM trips WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$trip) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Trip not found or does not belong to your company'
                ]);
            }
            
            // Check if trip has any bookings
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM bookings 
                WHERE trip_id = ? AND status = 'booked'
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete trip with active bookings'
                ]);
            }
            
            // Delete trip
            $stmt = $conn->prepare("DELETE FROM trips WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Trip deleted successfully'
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