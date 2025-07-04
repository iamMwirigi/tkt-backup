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
            error_log("Dotenv Error in destinations/list.php: " . $e->getMessage());
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
        $stmt = $conn->prepare("
            SELECT id FROM routes 
            WHERE id = ? AND company_id = ?
        ");
        $stmt->execute([$data['route_id'], $data['company_id']]);
        if (!$stmt->fetch()) {
            sendResponse(404, [
                'error' => true,
                'message' => 'Route not found or does not belong to your company'
            ]);
        }
    }

    // Build the base query
    $sql = "
        SELECT d.*,
               r.name as route_name,
               r.description as route_description
        FROM destinations d
        JOIN routes r ON d.route_id = r.id
        WHERE r.company_id = ?
    ";

    $params = [$data['company_id']];

    // Add route_id filter if provided
    if (isset($data['route_id'])) {
        $sql .= " AND d.route_id = ?";
        $params[] = $data['route_id'];
    }

    // Complete the query with ordering
    $sql .= " ORDER BY r.name ASC, d.stop_order ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(200, [
        'success' => true,
        'message' => 'Destinations retrieved successfully',
        'data' => [
            'destinations' => $destinations
        ]
    ]);

} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 