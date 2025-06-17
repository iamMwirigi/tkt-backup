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
            error_log("Dotenv Error in route_details/list.php: " . $e->getMessage());
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
    
    // Validate required fields
    if (!isset($data['company_id'])) {
        sendResponse(400, [
            'error' => true,
            'message' => 'company_id is required'
        ]);
    }

    if (!isset($data['route_id'])) {
        sendResponse(400, [
            'error' => true,
            'message' => 'route_id is required'
        ]);
    }

    // Get route details with destinations and fares
    $stmt = $conn->prepare("
        SELECT 
            r.*,
            (
                SELECT COUNT(*) 
                FROM trips t 
                WHERE t.route_id = r.id 
                AND t.status != 'completed'
            ) as active_trips
        FROM routes r
        WHERE r.id = ? AND r.company_id = ?
    ");
    $stmt->execute([$data['route_id'], $data['company_id']]);
    $route = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$route) {
        sendResponse(404, [
            'error' => true,
            'message' => 'Route not found or does not belong to your company'
        ]);
    }

    // Get destinations for this route
    $stmt = $conn->prepare("
        SELECT 
            d.*,
            (
                SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'id', f.id,
                        'label', f.label,
                        'amount', f.amount
                    )
                )
                FROM fares f
                WHERE f.destination_id = d.id
            ) as fares
        FROM destinations d
        WHERE d.route_id = ?
        ORDER BY d.stop_order ASC
    ");
    $stmt->execute([$data['route_id']]);
    $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process destinations and their fares
    foreach ($destinations as &$destination) {
        $destination['fares'] = json_decode($destination['fares'], true) ?: [];
    }

    $route['destinations'] = $destinations;
    
    sendResponse(200, [
        'success' => true,
        'message' => 'Route details retrieved successfully',
        'data' => [
            'route' => $route
        ]
    ]);

} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 