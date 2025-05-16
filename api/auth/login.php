<?php
session_start();

// Corrected paths:
// From 'api/auth/' go up two levels to 'tkt_dev/', then into 'config/' or 'includes/'
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

header('Content-Type: application/json');

$company_id = verify_device($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['username']) && isset($data['password'])) {
        $username = $data['username'];
        $password = $data['password']; // Insecure! Use password_hash and password_verify in production.
        // $company_id is available from verify_device($pdo) call above

        try {
            // IMPORTANT: For this to work, your 'users' table needs a 'password' column
            // storing plain text passwords (VERY INSECURE - for testing only)
            // and a 'company_id' column.
            // Also, ensure the user is active, e.g., AND status = 'active'
            $stmt = $pdo->prepare("SELECT id, company_id FROM users WHERE username = ? AND password = ?");
            $stmt->execute([$username, $password, $company_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Also verify that the user's company_id matches the device's company_id
            if ($user && $user['company_id'] == $company_id) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['company_id'] = $user['company_id']; // Optionally store company_id in session
                echo json_encode(['message' => 'Login successful', 'user_id' => $user['id']]);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials or company mismatch.']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            error_log("Login failed: " . $e->getMessage());
            echo json_encode(['error' => 'Login failed due to a server error.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing username or password.']);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method Not Allowed. Please use POST.']);
}
?>

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                echo json_encode(['message' => 'Login successful']);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing username or password']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>