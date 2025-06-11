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

// Use dummy values for testing
$user_id = 1;
$company_id = 1;
$user_role = 'admin';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get all vehicles for the company with their owners
    $stmt = $conn->prepare("
        SELECT 
            v.*,
            vo.name as owner_name,
            vo.phone_number as owner_phone,
            (
                SELECT COUNT(*) 
                FROM trips t 
                WHERE t.vehicle_id = v.id 
                AND t.status IN ('pending', 'in_progress')
            ) as active_trips
        FROM vehicles v
        LEFT JOIN vehicle_owners vo ON v.owner_id = vo.id
        WHERE v.company_id = ?
        ORDER BY v.plate_number ASC
    ");
    $stmt->execute([$company_id]);
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