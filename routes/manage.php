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
            error_log("Dotenv Error in routes/manage.php: " . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
// $auth = checkAuth();
// $user_id = $auth['user_id'];
// $company_id = $auth['company_id'];
// $user_role = $_SESSION['role'] ?? '';

// Only admin users can perform CRUD operations
/*
if ($user_role !== 'admin') {
    sendResponse(403, [
        'error' => true,
        'message' => 'Only admin users can manage routes'
    ]);
}
*/

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            // Create new route
            validateRequiredFields(['name'], $data);
            
            $stmt = $conn->prepare("
                INSERT INTO routes (company_id, name, description) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $company_id,
                $data['name'],
                $data['description'] ?? null
            ]);
            
            $route_id = $conn->lastInsertId();
            
            // If destinations are provided, create them
            if (isset($data['destinations']) && is_array($data['destinations'])) {
                foreach ($data['destinations'] as $index => $destination) {
                    $stmt = $conn->prepare("
                        INSERT INTO destinations (route_id, name, stop_order) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([
                        $route_id,
                        $destination['name'],
                        $destination['stop_order'] ?? ($index + 1)
                    ]);
                    
                    $destination_id = $conn->lastInsertId();
                    
                    // If fares are provided for this destination, create them
                    if (isset($destination['fares']) && is_array($destination['fares'])) {
                        foreach ($destination['fares'] as $fare) {
                            $stmt = $conn->prepare("
                                INSERT INTO fares (destination_id, label, amount) 
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([
                                $destination_id,
                                $fare['label'],
                                $fare['amount']
                            ]);
                        }
                    }
                }
            }
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Route created successfully',
                'route_id' => $route_id
            ]);
            break;
            
        case 'PUT':
            // Update existing route
            validateRequiredFields(['id', 'name'], $data);
            
            // Verify route belongs to company
            $stmt = $conn->prepare("SELECT id FROM routes WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Route not found or does not belong to your company'
                ]);
            }
            
            $stmt = $conn->prepare("
                UPDATE routes 
                SET name = ?, description = ? 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['id'],
                $company_id
            ]);
            
            // Update destinations if provided
            if (isset($data['destinations']) && is_array($data['destinations'])) {
                // Get existing destinations
                $stmt = $conn->prepare("SELECT id, name FROM destinations WHERE route_id = ?");
                $stmt->execute([$data['id']]);
                $existing_destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Update or create destinations
                foreach ($data['destinations'] as $index => $destination) {
                    // Check if destination exists
                    $existing_destination = null;
                    foreach ($existing_destinations as $ed) {
                        if ($ed['name'] === $destination['name']) {
                            $existing_destination = $ed;
                            break;
                        }
                    }
                    
                    if ($existing_destination) {
                        // Update existing destination
                        $stmt = $conn->prepare("
                            UPDATE destinations 
                            SET stop_order = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $destination['stop_order'] ?? ($index + 1),
                            $existing_destination['id']
                        ]);
                        $destination_id = $existing_destination['id'];
                    } else {
                        // Create new destination
                        $stmt = $conn->prepare("
                            INSERT INTO destinations (route_id, name, stop_order) 
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([
                            $data['id'],
                            $destination['name'],
                            $destination['stop_order'] ?? ($index + 1)
                        ]);
                        $destination_id = $conn->lastInsertId();
                    }
                    
                    // Update fares if provided
                    if (isset($destination['fares']) && is_array($destination['fares'])) {
                        // Delete existing fares for this destination
                        $stmt = $conn->prepare("DELETE FROM fares WHERE destination_id = ?");
                        $stmt->execute([$destination_id]);
                        
                        // Create new fares
                        foreach ($destination['fares'] as $fare) {
                            $stmt = $conn->prepare("
                                INSERT INTO fares (destination_id, label, amount) 
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([
                                $destination_id,
                                $fare['label'],
                                $fare['amount']
                            ]);
                        }
                    }
                }
            }
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Route updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete route
            validateRequiredFields(['id'], $data);
            
            // Verify route belongs to company
            $stmt = $conn->prepare("SELECT id FROM routes WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Route not found or does not belong to your company'
                ]);
            }
            
            // Check if route is in use
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM trips 
                WHERE route_id = ? AND status != 'completed'
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete route that has active trips'
                ]);
            }
            
            // Delete route (cascade will handle destinations and fares)
            $stmt = $conn->prepare("DELETE FROM routes WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Route deleted successfully'
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