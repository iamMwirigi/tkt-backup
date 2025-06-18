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
            error_log("Dotenv Error in destinations/manage.php: " . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

// Set JSON content type
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
            // Create new destination
            validateRequiredFields(['route_id', 'name', 'stop_order'], $data);
            
            // Verify route exists and belongs to company
            $stmt = $conn->prepare("SELECT id FROM routes WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['route_id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Route not found or does not belong to your company'
                ]);
            }
            
            // Check if stop_order already exists for this route
            $stmt = $conn->prepare("
                SELECT id, name FROM destinations 
                WHERE route_id = ? AND stop_order = ?
            ");
            $stmt->execute([$data['route_id'], $data['stop_order']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                sendResponse(400, [
                    'error' => true,
                    'message' => "Stop order {$data['stop_order']} is already taken by destination '{$existing['name']}' in this route"
                ]);
            }
            
            // Create destination
            $stmt = $conn->prepare("
                INSERT INTO destinations (
                    route_id, 
                    name, 
                    stop_order, 
                    min_fare, 
                    max_fare, 
                    current_fare
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['route_id'],
                $data['name'],
                $data['stop_order'],
                $data['min_fare'] ?? 0,
                $data['max_fare'] ?? 0,
                $data['current_fare'] ?? 0
            ]);
            
            $destination_id = $conn->lastInsertId();
            
            // Get created destination
            $stmt = $conn->prepare("
                SELECT d.*, r.name as route_name
                FROM destinations d
                JOIN routes r ON d.route_id = r.id
                WHERE d.id = ?
            ");
            $stmt->execute([$destination_id]);
            $destination = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Destination created successfully',
                'destination' => $destination
            ]);
            break;
            
        case 'PUT':
            // Update existing destination
            validateRequiredFields(['id', 'name', 'stop_order'], $data);
            
            // Verify destination exists and belongs to company
            $stmt = $conn->prepare("
                SELECT d.id, d.route_id, d.name 
                FROM destinations d
                JOIN routes r ON d.route_id = r.id
                WHERE d.id = ? AND r.company_id = ?
            ");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Destination not found or does not belong to your company'
                ]);
            }
            
            // Check if new stop_order conflicts with another destination
            $stmt = $conn->prepare("
                SELECT id, name FROM destinations 
                WHERE route_id = (SELECT route_id FROM destinations WHERE id = ?)
                AND stop_order = ? 
                AND id != ?
            ");
            $stmt->execute([$data['id'], $data['stop_order'], $data['id']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                sendResponse(400, [
                    'error' => true,
                    'message' => "Stop order {$data['stop_order']} is already taken by destination '{$existing['name']}' in this route"
                ]);
            }
            
            // Update destination
            $stmt = $conn->prepare("
                UPDATE destinations 
                SET name = ?, 
                    stop_order = ?, 
                    min_fare = ?, 
                    max_fare = ?, 
                    current_fare = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['stop_order'],
                $data['min_fare'] ?? 0,
                $data['max_fare'] ?? 0,
                $data['current_fare'] ?? 0,
                $data['id']
            ]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Destination updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete destination
            validateRequiredFields(['id'], $data);
            
            // Verify destination exists and belongs to company
            $stmt = $conn->prepare("
                SELECT d.id 
                FROM destinations d
                JOIN routes r ON d.route_id = r.id
                WHERE d.id = ? AND r.company_id = ?
            ");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Destination not found or does not belong to your company'
                ]);
            }
            
            // Check if destination is in use
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM bookings 
                WHERE destination_id = ?
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete destination that has bookings'
                ]);
            }
            
            // Delete destination
            $stmt = $conn->prepare("DELETE FROM destinations WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Destination deleted successfully'
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