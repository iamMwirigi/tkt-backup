<?php
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
        // Get single ticket
        $stmt = $conn->prepare("
            SELECT 
                t.*,
                v.plate_number,
                v.vehicle_type,
                tr.trip_code,
                tr.departure_time,
                d.name as destination_name,
                r.name as route_name,
                u.name as officer_name,
                o.title as offense_name
            FROM tickets t
            LEFT JOIN vehicles v ON t.vehicle_id = v.id
            LEFT JOIN trips tr ON t.trip_id = tr.id
            LEFT JOIN destinations d ON t.destination_id = d.id
            LEFT JOIN routes r ON tr.route_id = r.id
            LEFT JOIN users u ON t.officer_id = u.id
            LEFT JOIN offenses o ON t.offense_id = o.id
            WHERE t.id = ? AND t.company_id = ?
        ");
        $stmt->execute([$_GET['id'], $company_id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ticket) {
            sendResponse(404, [
                'error' => true,
                'message' => 'Ticket not found or does not belong to your company'
            ]);
        }
        
        sendResponse(200, [
            'success' => true,
            'ticket' => $ticket
        ]);
    } else {
        // Get all tickets with filters
        $where = ['t.company_id = ?'];
        $params = [$company_id];
        
        if (isset($_GET['vehicle_id'])) {
            $where[] = 't.vehicle_id = ?';
            $params[] = $_GET['vehicle_id'];
        }
        
        if (isset($_GET['trip_id'])) {
            $where[] = 't.trip_id = ?';
            $params[] = $_GET['trip_id'];
        }
        
        if (isset($_GET['officer_id'])) {
            $where[] = 't.officer_id = ?';
            $params[] = $_GET['officer_id'];
        }
        
        if (isset($_GET['status'])) {
            $where[] = 't.status = ?';
            $params[] = $_GET['status'];
        }
        
        if (isset($_GET['date'])) {
            $where[] = 'DATE(t.issued_at) = ?';
            $params[] = $_GET['date'];
        }
        
        if (isset($_GET['offense_id'])) {
            $where[] = 't.offense_id = ?';
            $params[] = $_GET['offense_id'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Get ticket statistics
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_tickets,
                SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_tickets
            FROM tickets t
            WHERE $where_clause
        ");
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get tickets with details
        $stmt = $conn->prepare("
            SELECT 
                t.*,
                v.plate_number,
                v.vehicle_type,
                tr.trip_code,
                tr.departure_time,
                d.name as destination_name,
                r.name as route_name,
                u.name as officer_name,
                o.title as offense_name
            FROM tickets t
            LEFT JOIN vehicles v ON t.vehicle_id = v.id
            LEFT JOIN trips tr ON t.trip_id = tr.id
            LEFT JOIN destinations d ON t.destination_id = d.id
            LEFT JOIN routes r ON tr.route_id = r.id
            LEFT JOIN users u ON t.officer_id = u.id
            LEFT JOIN offenses o ON t.offense_id = o.id
            WHERE $where_clause
            ORDER BY t.issued_at DESC
        ");
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, [
            'success' => true,
            'statistics' => [
                'total_tickets' => (int)$stats['total_tickets'],
                'paid_tickets' => (int)$stats['paid_tickets'],
                'unpaid_tickets' => (int)$stats['unpaid_tickets']
            ],
            'tickets' => $tickets
        ]);
    }
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 