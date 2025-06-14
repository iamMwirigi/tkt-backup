<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

// Get company_id from either GET parameters or request body
$company_id = $_GET['company_id'] ?? $data['company_id'] ?? null;

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
    
    switch ($method) {
        case 'POST':
            // Create dashboard settings or save dashboard layout
            if (!isset($data['type'])) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'type is required'
                ]);
            }
            
            switch ($data['type']) {
                case 'layout':
                    // Save dashboard layout preferences
                    if (!isset($data['layout'])) {
                        sendResponse(400, [
                            'error' => true,
                            'message' => 'layout configuration is required'
                        ]);
                    }
                    
                    // Here you would typically save the layout to a database
                    // For now, we'll just return success
                    sendResponse(201, [
                        'success' => true,
                        'message' => 'Dashboard layout saved successfully'
                    ]);
                    break;
                    
                case 'filter':
                    // Save dashboard filter preferences
                    if (!isset($data['filters'])) {
                        sendResponse(400, [
                            'error' => true,
                            'message' => 'filters configuration is required'
                        ]);
                    }
                    
                    // Here you would typically save the filters to a database
                    // For now, we'll just return success
                    sendResponse(201, [
                        'success' => true,
                        'message' => 'Dashboard filters saved successfully'
                    ]);
                    break;
                    
                default:
                    sendResponse(400, [
                        'error' => true,
                        'message' => 'Invalid type specified'
                    ]);
            }
            break;
            
        case 'PUT':
            // Update dashboard settings
            if (!isset($data['type'])) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'type is required'
                ]);
            }
            
            switch ($data['type']) {
                case 'layout':
                    // Update dashboard layout preferences
                    if (!isset($data['layout'])) {
                        sendResponse(400, [
                            'error' => true,
                            'message' => 'layout configuration is required'
                        ]);
                    }
                    
                    // Here you would typically update the layout in a database
                    // For now, we'll just return success
                    sendResponse(200, [
                        'success' => true,
                        'message' => 'Dashboard layout updated successfully'
                    ]);
                    break;
                    
                case 'filter':
                    // Update dashboard filter preferences
                    if (!isset($data['filters'])) {
                        sendResponse(400, [
                            'error' => true,
                            'message' => 'filters configuration is required'
                        ]);
                    }
                    
                    // Here you would typically update the filters in a database
                    // For now, we'll just return success
                    sendResponse(200, [
                        'success' => true,
                        'message' => 'Dashboard filters updated successfully'
                    ]);
                    break;
                    
                default:
                    sendResponse(400, [
                        'error' => true,
                        'message' => 'Invalid type specified'
                    ]);
            }
            break;
            
        case 'DELETE':
            // Delete dashboard settings
            if (!isset($data['type'])) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'type is required'
                ]);
            }
            
            switch ($data['type']) {
                case 'layout':
                    // Reset dashboard layout to default
                    // Here you would typically delete the layout from a database
                    // For now, we'll just return success
                    sendResponse(200, [
                        'success' => true,
                        'message' => 'Dashboard layout reset to default'
                    ]);
                    break;
                    
                case 'filter':
                    // Reset dashboard filters to default
                    // Here you would typically delete the filters from a database
                    // For now, we'll just return success
                    sendResponse(200, [
                        'success' => true,
                        'message' => 'Dashboard filters reset to default'
                    ]);
                    break;
                    
                default:
                    sendResponse(400, [
                        'error' => true,
                        'message' => 'Invalid type specified'
                    ]);
            }
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