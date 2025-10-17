<?php
// Set response headers
header('Content-Type: application/json');

// Include database connection
require_once 'db_connection.php';

// Initialize response array
 $response = [
    'success' => false,
    'message' => '',
    'twoFactorRequired' => false,
    'redirect' => 'dashboard.php'
];

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get JSON input
 $json_input = file_get_contents('php://input');
 $data = json_decode($json_input, true);

// Validate input
if (empty($data['email']) || empty($data['password'])) {
    $response['message'] = 'All fields are required';
    echo json_encode($response);
    exit;
}

// Get and sanitize input
 $email = $conn->real_escape_string($data['email']);
 $password = $data['password'];
 $remember = isset($data['remember']) ? $data['remember'] : false;

// Query to check user credentials
 $sql = "SELECT id, username, email, password, two_factor_enabled, status 
        FROM users 
        WHERE email = ?";
 $stmt = $conn->prepare($sql);
 $stmt->bind_param("s", $email);
 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Check if account is active
        if ($user['status'] !== 'active') {
            $response['message'] = 'Your account is not active. Please contact support.';
            echo json_encode($response);
            exit;
        }
        
        // Update last login
        $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        
        // Create session
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        
        // Set session expiration based on "Remember me" checkbox
        if ($remember) {
            $session_expiration = time() + (30 * 24 * 60 * 60); // 30 days
            setcookie('remember_me', $user['id'], $session_expiration, '/');
        }
        
        // Check if 2FA is enabled
        if ($user['two_factor_enabled']) {
            $response['success'] = true;
            $response['twoFactorRequired'] = true;
            $response['message'] = 'Please enter your 2FA code';
        } else {
            $response['success'] = true;
            $response['message'] = 'Login successful';
        }
    } else {
        $response['message'] = 'Invalid email or password';
    }
} else {
    $response['message'] = 'Invalid email or password';
}

// Close connection
 $conn->close();

// Return JSON response
echo json_encode($response);
?>