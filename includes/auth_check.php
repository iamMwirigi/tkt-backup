<?php
session_start();

// Ensure this path correctly points to your database connection script
// It's two levels up from 'includes' to 'tkt_dev', then into 'config'
require_once __DIR__ . '/../config/database.php';

// Require the helpers file (it's in the same 'includes' directory)
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');

// $pdo should be available from config/database.php
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection is not available.']);
    exit;
}

// Verify the device and get the company_id.
// The verify_device function will output an error and exit if verification fails.
$company_id = verify_device($pdo);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'User not logged in. Please login.']);
    exit;
}

$user_id = $_SESSION['user_id']; // Make user_id available to the script that includes this file
// $company_id is also available
?>