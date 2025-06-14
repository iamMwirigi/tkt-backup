<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if (isset($_GET['id'])) {
        // Get single company
        $stmt = $conn->prepare("
            SELECT 
                c.*,
                COUNT(DISTINCT u.id) as total_users,
                COUNT(DISTINCT v.id) as total_vehicles,
                COUNT(DISTINCT t.id) as total_trips
            FROM companies c
            LEFT JOIN users u ON c.id = u.company_id
            LEFT JOIN vehicles v ON c.id = v.company_id
            LEFT JOIN trips t ON c.id = t.company_id
            WHERE c.id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$_GET['id']]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$company) {
            sendResponse(404, [
                'error' => true,
                'message' => 'Company not found'
            ]);
        }
        
        sendResponse(200, [
            'success' => true,
            'company' => $company
        ]);
    } else {
        // Get all companies with filters
        $where = ['1=1'];
        $params = [];
        
        if (isset($_GET['name'])) {
            $where[] = 'c.name LIKE ?';
            $params[] = '%' . $_GET['name'] . '%';
        }
        
        if (isset($_GET['email'])) {
            $where[] = 'c.email LIKE ?';
            $params[] = '%' . $_GET['email'] . '%';
        }
        
        if (isset($_GET['status'])) {
            $where[] = 'c.status = ?';
            $params[] = $_GET['status'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $stmt = $conn->prepare("
            SELECT 
                c.*,
                COUNT(DISTINCT u.id) as total_users,
                COUNT(DISTINCT v.id) as total_vehicles,
                COUNT(DISTINCT t.id) as total_trips
            FROM companies c
            LEFT JOIN users u ON c.id = u.company_id
            LEFT JOIN vehicles v ON c.id = v.company_id
            LEFT JOIN trips t ON c.id = t.company_id
            WHERE $where_clause
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");
        $stmt->execute($params);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, [
            'success' => true,
            'companies' => $companies
        ]);
    }
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 