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
            error_log("Dotenv Error in bookings/manage.php: " . $e->getMessage());
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
        case 'GET':
            // Get bookings
            if (isset($_GET['id'])) {
                // Get single booking
                $stmt = $conn->prepare("
                    SELECT 
                        b.*,
                        t.trip_code,
                        t.departure_time,
                        v.plate_number,
                        v.vehicle_type,
                        d.name as destination_name,
                        r.name as route_name,
                        u.name as booked_by
                    FROM bookings b
                    LEFT JOIN trips t ON b.trip_id = t.id
                    LEFT JOIN vehicles v ON b.vehicle_id = v.id
                    LEFT JOIN destinations d ON b.destination_id = d.id
                    LEFT JOIN routes r ON t.route_id = r.id
                    LEFT JOIN users u ON b.user_id = u.id
                    WHERE b.id = ? AND b.company_id = ?
                ");
                $stmt->execute([$_GET['id'], $company_id]);
                $booking = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$booking) {
                    sendResponse(404, [
                        'error' => true,
                        'message' => 'Booking not found'
                    ]);
                }
                
                sendResponse(200, [
                    'success' => true,
                    'booking' => $booking
                ]);
            } else {
                // Get all bookings with filters
                $where = ['b.company_id = ?'];
                $params = [$company_id];
                
                if (isset($_GET['trip_id'])) {
                    $where[] = 'b.trip_id = ?';
                    $params[] = $_GET['trip_id'];
                }
                
                if (isset($_GET['status'])) {
                    $where[] = 'b.status = ?';
                    $params[] = $_GET['status'];
                }
                
                if (isset($_GET['date'])) {
                    $where[] = 'DATE(b.booked_at) = ?';
                    $params[] = $_GET['date'];
                }
                
                $where_clause = implode(' AND ', $where);
                
                $stmt = $conn->prepare("
                    SELECT 
                        b.*,
                        t.trip_code,
                        t.departure_time,
                        v.plate_number,
                        v.vehicle_type,
                        d.name as destination_name,
                        r.name as route_name,
                        u.name as booked_by
                    FROM bookings b
                    LEFT JOIN trips t ON b.trip_id = t.id
                    LEFT JOIN vehicles v ON b.vehicle_id = v.id
                    LEFT JOIN destinations d ON b.destination_id = d.id
                    LEFT JOIN routes r ON t.route_id = r.id
                    LEFT JOIN users u ON b.user_id = u.id
                    WHERE $where_clause
                    ORDER BY b.booked_at DESC
                ");
                $stmt->execute($params);
                $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendResponse(200, [
                    'success' => true,
                    'bookings' => $bookings
                ]);
            }
            break;
            
        case 'POST':
            // Create booking
            validateRequiredFields([
                'trip_id',
                'customer_name',
                'customer_phone',
                'destination_id',
                'seat_number',
                'fare_amount'
            ], $data);
            
            // Verify trip exists and belongs to company
            $stmt = $conn->prepare("
                SELECT t.*, v.id as vehicle_id 
                FROM trips t
                LEFT JOIN vehicles v ON t.vehicle_id = v.id
                WHERE t.id = ? AND t.company_id = ?
            ");
            $stmt->execute([$data['trip_id'], $company_id]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$trip) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Trip not found or does not belong to your company'
                ]);
            }
            
            // Verify destination exists and belongs to trip's route
            $stmt = $conn->prepare("
                SELECT d.* 
                FROM destinations d
                WHERE d.id = ? AND d.route_id = ?
            ");
            $stmt->execute([$data['destination_id'], $trip['route_id']]);
            if (!$stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Invalid destination for this trip'
                ]);
            }
            
            // Verify seat is available
            $stmt = $conn->prepare("
                SELECT id FROM bookings 
                WHERE trip_id = ? AND seat_number = ? AND status = 'booked'
            ");
            $stmt->execute([$data['trip_id'], $data['seat_number']]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Seat is already booked'
                ]);
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Create booking
                $stmt = $conn->prepare("
                    INSERT INTO bookings (
                        company_id, trip_id, vehicle_id, user_id,
                        customer_name, customer_phone, destination_id,
                        seat_number, fare_amount, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'booked')
                ");
                
                $stmt->execute([
                    $company_id,
                    $data['trip_id'],
                    $trip['vehicle_id'],
                    $user_id,
                    $data['customer_name'],
                    $data['customer_phone'],
                    $data['destination_id'],
                    $data['seat_number'],
                    $data['fare_amount']
                ]);
                
                $booking_id = $conn->lastInsertId();
                
                // Get created booking with details
                $stmt = $conn->prepare("
                    SELECT 
                        b.*,
                        t.trip_code,
                        t.departure_time,
                        v.plate_number,
                        v.vehicle_type,
                        d.name as destination_name,
                        r.name as route_name,
                        u.name as booked_by
                    FROM bookings b
                    LEFT JOIN trips t ON b.trip_id = t.id
                    LEFT JOIN vehicles v ON b.vehicle_id = v.id
                    LEFT JOIN destinations d ON b.destination_id = d.id
                    LEFT JOIN routes r ON t.route_id = r.id
                    LEFT JOIN users u ON b.user_id = u.id
                    WHERE b.id = ?
                ");
                $stmt->execute([$booking_id]);
                $booking = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $conn->commit();
                
                sendResponse(201, [
                    'success' => true,
                    'message' => 'Booking created successfully',
                    'booking' => $booking
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'PUT':
            // Update booking
            validateRequiredFields(['id'], $data);
            
            // Verify booking exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Booking not found or does not belong to your company'
                ]);
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Build update query based on provided fields
                $updates = [];
                $params = [];
                
                $allowed_fields = [
                    'customer_name',
                    'customer_phone',
                    'seat_number',
                    'fare_amount',
                    'status'
                ];
                
                foreach ($allowed_fields as $field) {
                    if (isset($data[$field])) {
                        // Validate status if being updated
                        if ($field === 'status') {
                            $allowed_statuses = ['booked', 'cancelled', 'converted'];
                            if (!in_array($data['status'], $allowed_statuses)) {
                                throw new Exception('Invalid status. Allowed values: ' . implode(', ', $allowed_statuses));
                            }
                        }
                        
                        // Verify seat is available if being changed
                        if ($field === 'seat_number' && $data['seat_number'] !== $booking['seat_number']) {
                            $stmt = $conn->prepare("
                                SELECT id FROM bookings 
                                WHERE trip_id = ? AND seat_number = ? AND id != ? AND status = 'booked'
                            ");
                            $stmt->execute([$booking['trip_id'], $data['seat_number'], $data['id']]);
                            if ($stmt->fetch()) {
                                throw new Exception('Seat is already booked');
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
                        UPDATE bookings 
                        SET " . implode(", ", $updates) . "
                        WHERE id = ? AND company_id = ?
                    ");
                    $stmt->execute($params);
                }
                
                // Get updated booking with details
                $stmt = $conn->prepare("
                    SELECT 
                        b.*,
                        t.trip_code,
                        t.departure_time,
                        v.plate_number,
                        v.vehicle_type,
                        d.name as destination_name,
                        r.name as route_name,
                        u.name as booked_by
                    FROM bookings b
                    LEFT JOIN trips t ON b.trip_id = t.id
                    LEFT JOIN vehicles v ON b.vehicle_id = v.id
                    LEFT JOIN destinations d ON b.destination_id = d.id
                    LEFT JOIN routes r ON t.route_id = r.id
                    LEFT JOIN users u ON b.user_id = u.id
                    WHERE b.id = ?
                ");
                $stmt->execute([$data['id']]);
                $updated_booking = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $conn->commit();
                
                sendResponse(200, [
                    'success' => true,
                    'message' => 'Booking updated successfully',
                    'booking' => $updated_booking
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'DELETE':
            // Delete booking
            validateRequiredFields(['id'], $data);
            
            // Verify booking exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Booking not found or does not belong to your company'
                ]);
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Delete booking
                $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ? AND company_id = ?");
                $stmt->execute([$data['id'], $company_id]);
                
                $conn->commit();
                
                sendResponse(200, [
                    'success' => true,
                    'message' => 'Booking deleted successfully'
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