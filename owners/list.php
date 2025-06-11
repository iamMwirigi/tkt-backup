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
            error_log("Dotenv Error in owners/list.php: " . $e->getMessage());
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

    // Get all owners with their vehicles for the company
    $stmt = $conn->prepare("
        SELECT 
            vo.*,
            JSON_ARRAYAGG(
                JSON_OBJECT(
                    'id', v.id,
                    'plate_number', v.plate_number,
                    'vehicle_type', v.vehicle_type,
                    'created_at', v.created_at
                )
            ) as vehicles
        FROM vehicle_owners vo
        LEFT JOIN vehicles v ON vo.id = v.owner_id
        WHERE vo.company_id = ?
        GROUP BY vo.id
        ORDER BY vo.name ASC
    ");
    $stmt->execute([$company_id]);
    $owners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse vehicles JSON for each owner
    foreach ($owners as &$owner) {
        $owner['vehicles'] = json_decode($owner['vehicles'], true);
        // Remove null vehicles (if an owner has no vehicles)
        if ($owner['vehicles'][0] === null) {
            $owner['vehicles'] = [];
        }
    }

    sendResponse(200, [
        'success' => true,
        'message' => 'Owners retrieved successfully',
        'data' => [
            'owners' => $owners
        ]
    ]);

} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?> 