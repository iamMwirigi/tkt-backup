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

header('Content-Type: application/json');

// Use dummy values for testing
$user_id = 1;
$company_id = 1;
$user_role = 'admin';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get all destinations with their fares for the company's routes
    $stmt = $conn->prepare("
        SELECT 
            d.*,
            r.name as route_name,
            r.description as route_description,
            GROUP_CONCAT(
                JSON_OBJECT(
                    'id', f.id,
                    'label', f.label,
                    'amount', f.amount
                )
            ) as fares
        FROM destinations d
        JOIN routes r ON d.route_id = r.id
        LEFT JOIN fares f ON d.id = f.destination_id
        WHERE r.company_id = ?
        GROUP BY d.id
        ORDER BY r.name, d.stop_order
    ");
    $stmt->execute([$company_id]);
    $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process the fares JSON string into an array
    foreach ($destinations as &$destination) {
        if ($destination['fares']) {
            $destination['fares'] = array_map(function($fare) {
                return json_decode($fare, true);
            }, explode(',', $destination['fares']));
        } else {
            $destination['fares'] = [];
        }
    }
    
    sendResponse(200, [
        'destinations' => $destinations
    ]);
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 