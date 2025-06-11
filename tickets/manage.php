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
            error_log("Dotenv Error in tickets/manage.php: " . $e->getMessage());
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
            // Create new ticket
            validateRequiredFields(['vehicle_id', 'trip_id', 'officer_id', 'destination_id', 'route', 'location'], $data);
            
            // Verify vehicle exists and belongs to company
            $stmt = $conn->prepare("SELECT id FROM vehicles WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['vehicle_id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle not found or does not belong to your company'
                ]);
            }
            
            // Verify trip exists and belongs to company
            $stmt = $conn->prepare("SELECT id FROM trips WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['trip_id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Trip not found or does not belong to your company'
                ]);
            }
            
            // Verify officer exists and belongs to company
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['officer_id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Officer not found or does not belong to your company'
                ]);
            }
            
            // Verify destination exists
            $stmt = $conn->prepare("SELECT id FROM destinations WHERE id = ?");
            $stmt->execute([$data['destination_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Destination not found'
                ]);
            }
            
            // Verify offense exists if provided
            if (!empty($data['offense_id'])) {
                $stmt = $conn->prepare("SELECT id FROM offenses WHERE id = ? AND company_id = ?");
                $stmt->execute([$data['offense_id'], $company_id]);
                if (!$stmt->fetch()) {
                    sendResponse(404, [
                        'error' => true,
                        'message' => 'Offense not found or does not belong to your company'
                    ]);
                }
            }
            
            // Create ticket
            $stmt = $conn->prepare("
                INSERT INTO tickets (
                    company_id, vehicle_id, trip_id, officer_id, 
                    offense_id, destination_id, booking_id, route, 
                    location, status, included_in_delivery
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $company_id,
                $data['vehicle_id'],
                $data['trip_id'],
                $data['officer_id'],
                $data['offense_id'] ?? null,
                $data['destination_id'],
                $data['booking_id'] ?? null,
                $data['route'],
                $data['location'],
                $data['status'] ?? 'unpaid',
                $data['included_in_delivery'] ?? 0
            ]);
            
            $ticket_id = $conn->lastInsertId();
            
            // Get created ticket
            $stmt = $conn->prepare("
                SELECT t.*, 
                    v.plate_number,
                    tr.trip_code,
                    u.name as officer_name,
                    o.title as offense_title,
                    d.name as destination_name
                FROM tickets t
                LEFT JOIN vehicles v ON t.vehicle_id = v.id
                LEFT JOIN trips tr ON t.trip_id = tr.id
                LEFT JOIN users u ON t.officer_id = u.id
                LEFT JOIN offenses o ON t.offense_id = o.id
                LEFT JOIN destinations d ON t.destination_id = d.id
                WHERE t.id = ?
            ");
            $stmt->execute([$ticket_id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Ticket created successfully',
                'ticket' => $ticket
            ]);
            break;
            
        case 'PUT':
            // Update ticket
            validateRequiredFields(['id'], $data);
            
            // Verify ticket exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM tickets WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Ticket not found or does not belong to your company'
                ]);
            }
            
            // Build update query dynamically based on provided fields
            $updateFields = [];
            $params = [];
            
            $allowedFields = [
                'vehicle_id', 'trip_id', 'officer_id', 'offense_id',
                'destination_id', 'booking_id', 'route', 'location',
                'status', 'included_in_delivery'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updateFields)) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'No valid fields to update'
                ]);
            }
            
            $params[] = $data['id'];
            $params[] = $company_id;
            
            $stmt = $conn->prepare("
                UPDATE tickets 
                SET " . implode(', ', $updateFields) . "
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute($params);
            
            // Get updated ticket
            $stmt = $conn->prepare("
                SELECT t.*, 
                    v.plate_number,
                    tr.trip_code,
                    u.name as officer_name,
                    o.title as offense_title,
                    d.name as destination_name
                FROM tickets t
                LEFT JOIN vehicles v ON t.vehicle_id = v.id
                LEFT JOIN trips tr ON t.trip_id = tr.id
                LEFT JOIN users u ON t.officer_id = u.id
                LEFT JOIN offenses o ON t.offense_id = o.id
                LEFT JOIN destinations d ON t.destination_id = d.id
                WHERE t.id = ?
            ");
            $stmt->execute([$data['id']]);
            $updated_ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Ticket updated successfully',
                'ticket' => $updated_ticket
            ]);
            break;
            
        case 'DELETE':
            // Delete ticket
            validateRequiredFields(['id'], $data);
            
            // Verify ticket exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM tickets WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Ticket not found or does not belong to your company'
                ]);
            }
            
            // Delete ticket
            $stmt = $conn->prepare("DELETE FROM tickets WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Ticket deleted successfully'
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