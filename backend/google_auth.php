<?php
// Set response headers
header('Content-Type: application/json');

// Include database connection
require_once '../db_connection.php';

// Initialize response array
 $response = [
    'success' => false,
    'message' => '',
    'twoFactorRequired' => false,
    'redirect' => '../dashboard.php'
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
if (empty($data['id']) || empty($data['email']) || empty($data['idToken'])) {
    $response['message'] = 'Invalid Google authentication data';
    echo json_encode($response);
    exit;
}

// Get Google data
 $google_id = $conn->real_escape_string($data['id']);
 $google_name = $conn->real_escape_string($data['name']);
 $google_first_name = $conn->real_escape_string($data['firstName']);
 $google_last_name = $conn->real_escape_string($data['lastName']);
 $google_email = $conn->real_escape_string($data['email']);
 $google_image_url = $conn->real_escape_string($data['imageUrl']);
 $google_id_token = $data['idToken'];

// Verify Google ID token (you should implement proper token verification)
// For simplicity, we're skipping the verification step here
// In production, you should verify the token with Google's API

// Get client IP for login attempts
 $ip_address = $_SERVER['REMOTE_ADDR'];

// Check if user already exists with this Google ID
 $sql = "SELECT id, username, email, two_factor_enabled, status 
        FROM users 
        WHERE google_id = ?";
 $stmt = $conn->prepare($sql);
 $stmt->bind_param("s", $google_id);
 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows === 1) {
    // User exists with Google ID, log them in
    $user = $result->fetch_assoc();
    
    // Check if account is active
    if ($user['status'] !== 'active') {
        $response['message'] = 'Your account is not active. Please contact support.';
        echo json_encode($response);
        exit;
    }
    
    // Update last login and profile image if changed
    $update_sql = "UPDATE users SET last_login = NOW(), profile_image = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $google_image_url, $user['id']);
    $stmt->execute();
    
    // Create session
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    
    // Store session in database
    $session_id = session_id();
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $session_expiration = time() + (24 * 60 * 60); // 24 hours
    $expires_at = date('Y-m-d H:i:s', $session_expiration);
    
    $session_sql = "INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, expires_at) 
                   VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($session_sql);
    $stmt->bind_param("issss", $user['id'], $session_id, $ip_address, $user_agent, $expires_at);
    $stmt->execute();
    
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
    // Check if user exists with this email but without Google ID
    $sql = "SELECT id, username, email, two_factor_enabled, status 
            FROM users 
            WHERE email = ? AND google_id IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $google_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        // User exists with this email but not linked to Google
        $response['message'] = 'An account with this email already exists. Please sign in with your password.';
        echo json_encode($response);
        exit;
    } else {
        // New user, create account with Google data
        // Generate a unique username
        $base_username = strtolower(str_replace(' ', '', $google_name));
        $username = generateUniqueUsername($conn, $base_username);
        
        // Generate a random password (user won't use it, but it's required)
        $random_password = bin2hex(random_bytes(16));
        $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
        
        // Insert new user
        $insert_sql = "INSERT INTO users (username, email, password, first_name, last_name, profile_image, google_id, email_verified, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, 'active')";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sssssss", $username, $google_email, $hashed_password, $google_first_name, $google_last_name, $google_image_url, $google_id);
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            
            // Create session
            session_start();
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $google_email;
            
            // Store session in database
            $session_id = session_id();
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $session_expiration = time() + (24 * 60 * 60); // 24 hours
            $expires_at = date('Y-m-d H:i:s', $session_expiration);
            
            $session_sql = "INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, expires_at) 
                           VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($session_sql);
            $stmt->bind_param("issss", $user_id, $session_id, $ip_address, $user_agent, $expires_at);
            $stmt->execute();
            
            $response['success'] = true;
            $response['message'] = 'Account created and logged in successfully';
        } else {
            $response['message'] = 'Failed to create account with Google data';
        }
    }
}

// Close connection
 $conn->close();

// Return JSON response
echo json_encode($response);

// Function to generate a unique username
function generateUniqueUsername($conn, $base_username) {
    $username = $base_username;
    $counter = 1;
    
    // Check if username exists
    $check_sql = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // If username exists, append a number until we find a unique one
    while ($result->num_rows > 0) {
        $username = $base_username . $counter;
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $counter++;
    }
    
    return $username;
}
?>