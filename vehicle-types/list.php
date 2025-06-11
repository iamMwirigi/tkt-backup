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
            error_log("Dotenv Error in vehicle-types/list.php: " . $e->getMessage());
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

    // Get all vehicle types for the company
    $stmt = $conn->prepare("
        SELECT 
            vt.*,
            (
                SELECT COUNT(*) 
                FROM vehicles v 
                WHERE v.vehicle_type_id = vt.id
            ) as vehicle_count
        FROM vehicle_types vt
        WHERE vt.company_id = ?
        ORDER BY vt.name ASC
    ");
    $stmt->execute([$company_id]);
    $vehicle_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(200, [
        'success' => true,
        'message' => 'Vehicle types retrieved successfully',
        'data' => [
            'vehicle_types' => $vehicle_types
        ]
    ]);

} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?> 