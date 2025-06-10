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
            error_log("Dotenv Error in tickets/create.php: " . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!checkAuth()) {
    http_response_code(401);
    echo json_encode(['error' => true, 'message' => 'Unauthorized. Please login.']);
    exit;
}

// Get user data from session
$user = $_SESSION['user'];
$company_id = $user['company_id'];

// Get request body
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['trip_id', 'destination_id', 'seat_number', 'fare_amount'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => "Missing required field: $field"]);
        exit;
    }
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->beginTransaction();

    // Get trip details
    $trip_id = $data['trip_id'];
    $stmt = $conn->prepare("SELECT t.*, v.plate_number, r.name as route_name 
                           FROM trips t 
                           JOIN vehicles v ON t.vehicle_id = v.id 
                           JOIN routes r ON t.route_id = r.id 
                           WHERE t.id = ? AND t.company_id = ?");
    $stmt->execute([$trip_id, $company_id]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        throw new Exception("Trip not found or doesn't belong to your company");
    }

    // Check if seat is available
    $seat_number = $data['seat_number'];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tickets 
                           WHERE trip_id = ? AND seat_number = ?");
    $stmt->execute([$trip_id, $seat_number]);
    $seat_check = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($seat_check['count'] > 0) {
        throw new Exception("Seat $seat_number is already taken for this trip");
    }

    // Validate destination
    $destination_id = $data['destination_id'];
    $stmt = $conn->prepare("SELECT * FROM destinations 
                           WHERE id = ? AND route_id = ?");
    $stmt->execute([$destination_id, $trip['route_id']]);
    $destination = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$destination) {
        throw new Exception("Invalid destination for this route");
    }

    // Create ticket
    $stmt = $conn->prepare("INSERT INTO tickets (
        company_id, vehicle_id, trip_id, officer_id, destination_id,
        route, location, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'unpaid')");

    $location = $data['location'] ?? 'Terminal';
    $stmt->execute([
        $company_id,
        $trip['vehicle_id'],
        $trip_id,
        $user['id'],
        $destination_id,
        $trip['route_name'],
        $location
    ]);
    $ticket_id = $conn->lastInsertId();

    // Create payment record
    $stmt = $conn->prepare("INSERT INTO payments (
        company_id, ticket_id, amount, payment_method,
        transaction_id, status, created_by
    ) VALUES (?, ?, ?, ?, ?, 'pending', ?)");

    $payment_method = $data['payment_method'] ?? 'cash';
    $transaction_id = $data['transaction_id'] ?? null;
    $stmt->execute([
        $company_id,
        $ticket_id,
        $data['fare_amount'],
        $payment_method,
        $transaction_id,
        $user['id']
    ]);

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Ticket created successfully',
        'ticket_id' => $ticket_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 