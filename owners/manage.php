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
            error_log("Dotenv Error in owners/manage.php: " . $e->getMessage());
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
    
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            // Create new owner
            validateRequiredFields(['name', 'phone_number'], $data);
            
            // Check if phone number already exists
            $stmt = $conn->prepare("SELECT id FROM vehicle_owners WHERE phone_number = ? AND company_id = ?");
            $stmt->execute([$data['phone_number'], $company_id]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Phone number already registered'
                ]);
            }
            
            // Create owner
            $stmt = $conn->prepare("
                INSERT INTO vehicle_owners (
                    name, phone_number, company_id, created_by
                ) VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $data['phone_number'],
                $company_id,
                $user_id
            ]);
            
            $owner_id = $conn->lastInsertId();
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Owner created successfully',
                'owner' => [
                    'id' => $owner_id,
                    'name' => $data['name'],
                    'phone_number' => $data['phone_number']
                ]
            ]);
            break;
            
        case 'PUT':
            // Update owner
            validateRequiredFields(['id'], $data);
            
            // Verify owner exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM vehicle_owners WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            $owner = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$owner) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Owner not found or does not belong to your company'
                ]);
            }
            
            // Check if new phone number already exists
            if (isset($data['phone_number']) && $data['phone_number'] !== $owner['phone_number']) {
                $stmt = $conn->prepare("SELECT id FROM vehicle_owners WHERE phone_number = ? AND company_id = ? AND id != ?");
                $stmt->execute([$data['phone_number'], $company_id, $data['id']]);
                if ($stmt->fetch()) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => 'Phone number already registered to another owner'
                    ]);
                }
            }
            
            // Build update query based on provided fields
            $updates = [];
            $params = [];
            
            $allowed_fields = [
                'name',
                'phone_number'
            ];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'No valid fields to update'
                ]);
            }
            
            $params[] = $data['id'];
            $params[] = $company_id;
            
            $stmt = $conn->prepare("
                UPDATE vehicle_owners 
                SET " . implode(", ", $updates) . "
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute($params);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Owner updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete owner
            validateRequiredFields(['id'], $data);
            
            // Verify owner exists and belongs to company
            $stmt = $conn->prepare("SELECT * FROM vehicle_owners WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            $owner = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$owner) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Owner not found or does not belong to your company'
                ]);
            }
            
            // Check if owner has any vehicles
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM vehicles 
                WHERE owner_id = ?
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete owner with registered vehicles. Please reassign or delete vehicles first.'
                ]);
            }
            
            // Delete owner
            $stmt = $conn->prepare("DELETE FROM vehicle_owners WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Owner deleted successfully'
            ]);
            break;
            
        default:
            sendResponse(405, [
                'error' => true,
                'message' => 'Method not allowed'
            ]);
    }
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?> 