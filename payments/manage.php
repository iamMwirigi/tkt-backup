<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verify company exists
    $stmt = $conn->prepare("SELECT id FROM companies WHERE id = ?");
    $stmt->execute([$data['company_id']]);
    if (!$stmt->fetch()) {
        sendResponse(404, [
            'error' => true,
            'message' => 'Company not found'
        ]);
    }
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            // Validate required fields
            $required_fields = ['company_id', 'amount', 'payment_method'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => "$field is required"
                    ]);
                }
            }
            
            // Validate payment method
            if (!in_array($data['payment_method'], ['mpesa', 'card', 'cash'])) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Invalid payment method. Must be one of: mpesa, card, cash'
                ]);
            }
            
            // If booking_id is provided, verify it exists and belongs to company
            if (isset($data['booking_id'])) {
                $stmt = $conn->prepare("
                    SELECT id 
                    FROM bookings 
                    WHERE id = ? AND company_id = ?
                ");
                $stmt->execute([$data['booking_id'], $data['company_id']]);
                if (!$stmt->fetch()) {
                    sendResponse(404, [
                        'error' => true,
                        'message' => 'Booking not found or does not belong to company'
                    ]);
                }
            }
            
            // Insert new payment
            $stmt = $conn->prepare("
                INSERT INTO payments (
                    company_id,
                    booking_id,
                    payment_method,
                    transaction_reference,
                    amount,
                    status,
                    paid_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['company_id'],
                $data['booking_id'] ?? null,
                $data['payment_method'],
                $data['transaction_reference'] ?? null,
                $data['amount'],
                $data['status'] ?? 'pending',
                $data['paid_at'] ?? null
            ]);
            
            $payment_id = $conn->lastInsertId();
            
            // Get the created payment
            $stmt = $conn->prepare("
                SELECT 
                    p.id,
                    p.company_id,
                    p.booking_id,
                    b.customer_name,
                    b.customer_phone,
                    p.payment_method,
                    p.transaction_reference,
                    p.amount,
                    p.status,
                    p.paid_at,
                    p.created_at
                FROM payments p
                LEFT JOIN bookings b ON p.booking_id = b.id
                WHERE p.id = ?
            ");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Payment created successfully',
                'data' => [
                    'payment' => $payment
                ]
            ]);
            break;
            
        case 'PUT':
            // Validate required fields
            $required_fields = ['company_id', 'id'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => "$field is required"
                    ]);
                }
            }
            
            // Check if payment exists and belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM payments 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Payment not found or does not belong to company'
                ]);
            }
            
            // If payment method is being updated, validate it
            if (isset($data['payment_method']) && !in_array($data['payment_method'], ['mpesa', 'card', 'cash'])) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Invalid payment method. Must be one of: mpesa, card, cash'
                ]);
            }
            
            // If booking_id is being updated, verify it exists and belongs to company
            if (isset($data['booking_id'])) {
                $stmt = $conn->prepare("
                    SELECT id 
                    FROM bookings 
                    WHERE id = ? AND company_id = ?
                ");
                $stmt->execute([$data['booking_id'], $data['company_id']]);
                if (!$stmt->fetch()) {
                    sendResponse(404, [
                        'error' => true,
                        'message' => 'Booking not found or does not belong to company'
                    ]);
                }
            }
            
            // Build update query dynamically based on provided fields
            $update_fields = [];
            $params = [];
            
            $allowed_fields = [
                'booking_id',
                'payment_method',
                'transaction_reference',
                'amount',
                'status',
                'paid_at'
            ];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $update_fields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($update_fields)) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'No fields to update'
                ]);
            }
            
            // Add id and company_id to params
            $params[] = $data['id'];
            $params[] = $data['company_id'];
            
            // Update payment
            $stmt = $conn->prepare("
                UPDATE payments 
                SET " . implode(', ', $update_fields) . "
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute($params);
            
            // Get the updated payment
            $stmt = $conn->prepare("
                SELECT 
                    p.id,
                    p.company_id,
                    p.booking_id,
                    b.customer_name,
                    b.customer_phone,
                    p.payment_method,
                    p.transaction_reference,
                    p.amount,
                    p.status,
                    p.paid_at,
                    p.created_at
                FROM payments p
                LEFT JOIN bookings b ON p.booking_id = b.id
                WHERE p.id = ?
            ");
            $stmt->execute([$data['id']]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Payment updated successfully',
                'data' => [
                    'payment' => $payment
                ]
            ]);
            break;
            
        case 'DELETE':
            // Validate required fields
            $required_fields = ['company_id', 'id'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => "$field is required"
                    ]);
                }
            }
            
            // Check if payment exists and belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM payments 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Payment not found or does not belong to company'
                ]);
            }
            
            // Delete payment
            $stmt = $conn->prepare("
                DELETE FROM payments 
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute([$data['id'], $data['company_id']]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Payment deleted successfully'
            ]);
            break;
            
        default:
            sendResponse(405, [
                'error' => true,
                'message' => 'Method not allowed'
            ]);
            break;
    }
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 