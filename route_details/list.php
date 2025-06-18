<?php
// Disable error display and enable logging
ini_set('display_errors', 0);
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

// Set JSON content type
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

    // If route_id is provided, validate it belongs to the company
    if (isset($data['route_id'])) {
        $stmt = $conn->prepare("SELECT id FROM routes WHERE id = ? AND company_id = ?");
        $stmt->execute([$data['route_id'], $data['company_id']]);
        if (!$stmt->fetch()) {
            sendResponse(404, [
                'error' => true,
                'message' => 'Route not found or does not belong to your company'
            ]);
        }
    }

    // Get route details with destinations and fares
    $sql = "
        SELECT r.*,
               COUNT(DISTINCT t.id) as active_trips,
               COALESCE(
                   JSON_ARRAYAGG(
                       CASE 
                           WHEN d.id IS NOT NULL THEN
                               JSON_OBJECT(
                                   'id', d.id,
                                   'route_id', d.route_id,
                                   'name', d.name,
                                   'stop_order', d.stop_order,
                                   'min_fare', d.min_fare,
                                   'max_fare', d.max_fare,
                                   'current_fare', d.current_fare
                               )
                           ELSE NULL
                       END
                   ),
                   '[]'
               ) as destinations
        FROM routes r
        LEFT JOIN trips t ON r.id = t.route_id AND t.status = 'in_progress'
        LEFT JOIN destinations d ON r.id = d.route_id
        WHERE r.company_id = ?
    ";

    $params = [$data['company_id']];

    // Add route_id filter if provided
    if (isset($data['route_id'])) {
        $sql .= " AND r.id = ?";
        $params[] = $data['route_id'];
    }

    // Complete the query with grouping and ordering
    $sql .= "
        GROUP BY r.id
        ORDER BY r.name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process each route's destinations
    foreach ($routes as &$route) {
        $destinations = json_decode($route['destinations'], true);
        // Filter out null values and sort by stop_order
        $destinations = array_filter($destinations, function($dest) {
            return $dest !== null;
        });
        usort($destinations, function($a, $b) {
            return $a['stop_order'] - $b['stop_order'];
        });
        $route['destinations'] = array_values($destinations);
    }

    sendResponse(200, [
        'success' => true,
        'message' => 'Route details retrieved successfully',
        'data' => [
            'routes' => $routes
        ]
    ]);

} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 