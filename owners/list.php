<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Validate company_id
if (!isset($_GET['company_id'])) {
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
    $stmt->execute([$_GET['company_id']]);
    if (!$stmt->fetch()) {
        sendResponse(404, [
            'error' => true,
            'message' => 'Company not found'
        ]);
    }
    
    if (isset($_GET['id'])) {
        // Get single owner
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
            WHERE vo.id = ? AND vo.company_id = ?
            GROUP BY vo.id
        ");
        $stmt->execute([$_GET['id'], $_GET['company_id']]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$owner) {
            sendResponse(404, [
                'error' => true,
                'message' => 'Owner not found or does not belong to your company'
            ]);
        }
        
        // Parse vehicles JSON
        $owner['vehicles'] = json_decode($owner['vehicles'], true);
        
        sendResponse(200, [
            'success' => true,
            'owner' => $owner
        ]);
    } else {
        // Get all owners with filters
        $where = ['vo.company_id = ?'];
        $params = [$_GET['company_id']];
        
        if (isset($_GET['name'])) {
            $where[] = 'vo.name LIKE ?';
            $params[] = '%' . $_GET['name'] . '%';
        }
        
        if (isset($_GET['phone'])) {
            $where[] = 'vo.phone LIKE ?';
            $params[] = '%' . $_GET['phone'] . '%';
        }
        
        $where_clause = implode(' AND ', $where);
        
        $stmt = $conn->prepare("
            SELECT 
                vo.*,
                COUNT(v.id) as total_vehicles
            FROM vehicle_owners vo
            LEFT JOIN vehicles v ON vo.id = v.owner_id
            WHERE $where_clause
            GROUP BY vo.id
            ORDER BY vo.name ASC
        ");
        $stmt->execute($params);
        $owners = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, [
            'success' => true,
            'owners' => $owners
        ]);
    }
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 