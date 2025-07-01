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

    // Collect all unique vehicle_configuration_ids
    $config_ids = array_unique(array_filter(array_column($vehicles, 'vehicle_configuration_id')));
    $layouts = [];
    if (!empty($config_ids)) {
        $in = implode(',', array_fill(0, count($config_ids), '?'));
        $stmt2 = $conn->prepare("SELECT id, layout FROM vehicle_configurations WHERE id IN ($in)");
        $stmt2->execute(array_values($config_ids));
        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $layouts[$row['id']] = json_decode($row['layout'], true);
        }
    }

    // Remove unwanted fields and add layout
    $filtered_vehicles = array_map(function($vehicle) use ($layouts) {
        unset($vehicle['owner_id'], $vehicle['owner_name'], $vehicle['owner_phone'], $vehicle['vehicle_type_id']);
        if (!empty($vehicle['vehicle_configuration_id']) && isset($layouts[$vehicle['vehicle_configuration_id']])) {
            $vehicle['layout'] = $layouts[$vehicle['vehicle_configuration_id']];
        } else {
            $vehicle['layout'] = null;
        }
        return $vehicle;
    }, $vehicles);

    sendResponse(200, [
        'success' => true,
        'message' => 'Vehicles retrieved successfully',
        'data' => [
            'vehicles' => $filtered_vehicles
        ]
    ]);

} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 