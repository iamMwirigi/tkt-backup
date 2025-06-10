<?php
// FOR DEBUGGING ONLY - !! REMOVE OR DISABLE FOR PRODUCTION !!
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Destroy the session
session_destroy();

sendResponse(200, [
    'success' => true,
    'message' => 'Logged out successfully'
]); 