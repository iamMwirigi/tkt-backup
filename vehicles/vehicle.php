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
            error_log("Dotenv Error in vehicles/vehicle.php: " . $e->getMessage());
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

    // If vehicle_id is provided, validate it belongs to the company
    if (isset($data['vehicle_id'])) {
        $stmt = $conn->prepare("
            SELECT v.*,
                   vt.name as vehicle_type_name,
                   vt.seats as vehicle_type_seats,
                   COUNT(DISTINCT t.id) as active_trips
            FROM vehicles v
            LEFT JOIN vehicle_types vt ON v.vehicle_type = vt.name AND v.company_id = vt.company_id
            LEFT JOIN trips t ON v.id = t.vehicle_id AND t.status = 'in_progress'
            WHERE v.id = ? AND v.company_id = ?
            GROUP BY v.id
        ");
        $stmt->execute([$data['vehicle_id'], $data['company_id']]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vehicle) {
            sendResponse(404, [
                'error' => true,
                'message' => 'Vehicle not found or does not belong to your company'
            ]);
        }
        // Remove unwanted fields from vehicle
        unset($vehicle['owner_id'], $vehicle['owner_name'], $vehicle['owner_phone'], $vehicle['vehicle_type_id']);
        sendResponse(200, [
            'success' => true,
            'message' => 'Vehicle details retrieved successfully',
            'data' => [
                'vehicle' => $vehicle
            ]
        ]);
    } else {
        // Get all vehicles for the company
        $stmt = $conn->prepare("
            SELECT v.*,
                   vt.name as vehicle_type_name,
                   vt.seats as vehicle_type_seats,
                   COUNT(DISTINCT t.id) as active_trips
            FROM vehicles v
            LEFT JOIN vehicle_types vt ON v.vehicle_type = vt.name AND v.company_id = vt.company_id
            LEFT JOIN trips t ON v.id = t.vehicle_id AND t.status = 'in_progress'
            WHERE v.company_id = ?
            GROUP BY v.id
            ORDER BY v.plate_number ASC
        ");
        $stmt->execute([$data['company_id']]);
        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Remove unwanted fields from each vehicle
        $filtered_vehicles = array_map(function($vehicle) {
            unset($vehicle['owner_id'], $vehicle['owner_name'], $vehicle['owner_phone'], $vehicle['vehicle_type_id']);
            return $vehicle;
        }, $vehicles);
        
        sendResponse(200, [
            'success' => true,
            'message' => 'Vehicles retrieved successfully',
            'data' => [
                'vehicles' => $filtered_vehicles
            ]
        ]);
    }
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 