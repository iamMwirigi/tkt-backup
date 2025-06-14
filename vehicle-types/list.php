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

// Get company_id from either GET parameters or request body
$company_id = $_GET['company_id'] ?? null;
if (!$company_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $company_id = $data['company_id'] ?? null;
}

// Validate company_id
if (!$company_id) {
    sendResponse(400, [
        'error' => true,
        'message' => 'company_id is required'
    ]);
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verify company exists
    $stmt = $conn->prepare("SELECT id FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    if (!$stmt->fetch()) {
        sendResponse(404, [
            'error' => true,
            'message' => 'Company not found'
        ]);
    }
    
    if (isset($_GET['id'])) {
        // Get single vehicle type
        $stmt = $conn->prepare("
            SELECT vt.*, c.name as company_name 
            FROM vehicle_types vt
            LEFT JOIN companies c ON vt.company_id = c.id
            WHERE vt.id = ? AND vt.company_id = ?
        ");
        $stmt->execute([$_GET['id'], $company_id]);
        $vehicle_type = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vehicle_type) {
            sendResponse(404, [
                'error' => true,
                'message' => 'Vehicle type not found or does not belong to your company'
            ]);
        }
        
        sendResponse(200, [
            'success' => true,
            'vehicle_type' => $vehicle_type
        ]);
    } else {
        // Get all vehicle types with filters
        $where = ['vt.company_id = ?'];
        $params = [$company_id];
        
        if (isset($_GET['name'])) {
            $where[] = 'vt.name LIKE ?';
            $params[] = '%' . $_GET['name'] . '%';
        }
        
        $where_clause = implode(' AND ', $where);
        
        $stmt = $conn->prepare("
            SELECT vt.*, c.name as company_name 
            FROM vehicle_types vt
            LEFT JOIN companies c ON vt.company_id = c.id
            WHERE $where_clause
            ORDER BY vt.name ASC
        ");
        $stmt->execute($params);
        $vehicle_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, [
            'success' => true,
            'vehicle_types' => $vehicle_types
        ]);
    }
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?> 