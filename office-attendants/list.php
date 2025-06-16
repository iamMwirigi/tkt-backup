<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get parameters from both GET and request body
    $request_data = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Get query parameters
    $company_id = $_GET['company_id'] ?? $request_data['company_id'] ?? null;
    $office_id = $_GET['office_id'] ?? $request_data['office_id'] ?? null;
    $status = $_GET['status'] ?? $request_data['status'] ?? null;
    $search = $_GET['search'] ?? $request_data['search'] ?? null;
    
    // Verify company exists if company_id is provided
    if ($company_id) {
        $stmt = $conn->prepare("SELECT id FROM companies WHERE id = ?");
        $stmt->execute([$company_id]);
        if (!$stmt->fetch()) {
            sendResponse(404, [
                'error' => true,
                'message' => 'Company not found'
            ]);
        }
    }
    
    // Build query
    $query = "
        SELECT 
            oa.id,
            oa.company_id,
            c.name as company_name,
            oa.office_id,
            o.name as office_name,
            oa.name,
            oa.email,
            oa.phone,
            oa.id_number,
            oa.status,
            oa.created_at
        FROM office_attendants oa
        LEFT JOIN companies c ON oa.company_id = c.id
        LEFT JOIN offices o ON oa.office_id = o.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($company_id) {
        $query .= " AND oa.company_id = ?";
        $params[] = $company_id;
    }
    
    if ($office_id) {
        $query .= " AND oa.office_id = ?";
        $params[] = $office_id;
    }
    
    if ($status) {
        $query .= " AND oa.status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $query .= " AND (
            oa.name LIKE ? OR 
            oa.email LIKE ? OR 
            oa.phone LIKE ? OR 
            oa.id_number LIKE ?
        )";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    $query .= " ORDER BY oa.created_at DESC";
    
    // Execute query
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $attendants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(200, [
        'success' => true,
        'data' => [
            'attendants' => $attendants
        ]
    ]);
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 