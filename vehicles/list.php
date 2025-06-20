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
            error_log("Dotenv Error in vehicles/list.php: " . $e->getMessage());
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

    // Get all vehicles for the specified company with their owners
    $stmt = $conn->prepare("
        SELECT 
            v.*,
            vo.name as owner_name,
            vo.phone as owner_phone,
            vt.name as vehicle_type_name,
            vt.seats as vehicle_type_seats,
            (
                SELECT COUNT(*) 
                FROM trips t 
                WHERE t.vehicle_id = v.id 
                AND t.status IN ('pending', 'in_progress')
            ) as active_trips
        FROM vehicles v
        LEFT JOIN vehicle_owners vo ON v.owner_id = vo.id
        LEFT JOIN vehicle_types vt ON v.vehicle_type = vt.name AND v.company_id = vt.company_id
        WHERE v.company_id = ?
        ORDER BY v.plate_number ASC
    ");
    $stmt->execute([$data['company_id']]);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(200, [
        'success' => true,
        'message' => 'Vehicles retrieved successfully',
        'data' => [
            'vehicles' => $vehicles
        ]
    ]);

} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 