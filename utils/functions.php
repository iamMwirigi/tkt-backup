<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        sendResponse(401, ['error' => 'Unauthorized access']);
        exit();
    }
    return $_SESSION['user_id'];
}

function checkDevice() {
    $device_id = $_SERVER['HTTP_X_DEVICE_ID'] ?? null;
    if (!$device_id) {
        sendResponse(401, ['error' => 'Device ID required']);
        exit();
    }
    return $device_id;
}

function verifyDevice($db, $user_id, $device_id) {
    $stmt = $db->prepare("SELECT id FROM devices WHERE user_id = ? AND device_uuid = ? AND is_active = 1");
    $stmt->execute([$user_id, $device_id]);
    return $stmt->fetch() ? true : false;
}

function sendResponse($status_code, $data) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function validateRequiredFields($required_fields, $data) {
    $missing = [];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        sendResponse(400, [
            'error' => 'Missing required fields',
            'fields' => $missing
        ]);
    }
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags($data));
}

function generateTripCode($company_prefix, $route_code, $date) {
    return sprintf(
        "%s-%s-%s-%d",
        $company_prefix,
        $route_code,
        date('Ymd', strtotime($date)),
        rand(1, 999)
    );
}

function calculateNetAmount($gross_amount, $deductions) {
    $total_deductions = 0;
    foreach ($deductions as $deduction) {
        $total_deductions += $deduction['amount'];
    }
    return $gross_amount - $total_deductions;
}


