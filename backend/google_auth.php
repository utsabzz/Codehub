<?php
header('Content-Type: application/json');
require_once 'db_connection.php';

// Initialize response
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
$googleData = json_decode($json_input, true);

// Validate Google data
if (empty($googleData['idToken']) || empty($googleData['email'])) {
    $response['message'] = 'Invalid Google authentication data';
    echo json_encode($response);
    exit;
}

try {
    $google_id = $conn->real_escape_string($googleData['id']);
    $email = $conn->real_escape_string($googleData['email']);
    $first_name = $conn->real_escape_string($googleData['firstName'] ?? '');
    $last_name = $conn->real_escape_string($googleData['lastName'] ?? '');
    $full_name = $conn->real_escape_string($googleData['name'] ?? '');
    $profile_image = $conn->real_escape_string($googleData['imageUrl'] ?? '');
    
    // Generate username from first and last name with random number
    $base_username = '';
    if (!empty($first_name) && !empty($last_name)) {
        $base_username = strtolower($first_name . $last_name);
    } else if (!empty($full_name)) {
        $base_username = strtolower(str_replace(' ', '', $full_name));
    } else {
        $base_username = explode('@', $email)[0];
    }
    
    // Clean username (remove special characters)
    $base_username = preg_replace('/[^a-zA-Z0-9]/', '', $base_username);
    
    // Ensure username is unique
    $username = $base_username . rand(100, 999); // Add random 3-digit number
    
    // Check if user exists with this Google ID or email
    $sql = "SELECT id, username, email, two_factor_enabled, status 
            FROM users 
            WHERE google_id = ? OR email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $google_id, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        // User exists - update their information
        $user = $result->fetch_assoc();
        
        // Update user information with latest Google data
        $update_sql = "UPDATE users SET 
                      first_name = ?, 
                      last_name = ?, 
                      profile_image = ?,
                      email_verified = 1,
                      last_login = NOW(),
                      updated_at = NOW()
                      WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssi", $first_name, $last_name, $profile_image, $user['id']);
        $stmt->execute();
        
        $user_id = $user['id'];
    } else {
        // Create new user with generated username
        $insert_sql = "INSERT INTO users 
                      (username, email, first_name, last_name, profile_image, google_id, email_verified, status, created_at, updated_at, last_login) 
                      VALUES (?, ?, ?, ?, ?, ?, 1, 'active', NOW(), NOW(), NOW())";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ssssss", $username, $email, $first_name, $last_name, $profile_image, $google_id);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            // Get the newly created user
            $select_sql = "SELECT id, username, email, two_factor_enabled, status 
                          FROM users 
                          WHERE id = ?";
            $select_stmt = $conn->prepare($select_sql);
            $select_stmt->bind_param("i", $user_id);
            $select_stmt->execute();
            $user_result = $select_stmt->get_result();
            $user = $user_result->fetch_assoc();
        } else {
            throw new Exception('Failed to create user account');
        }
    }
    
    // Check if account is active
    if ($user['status'] !== 'active') {
        $response['message'] = 'Your account is not active. Please contact support.';
        echo json_encode($response);
        exit;
    }
    
    // Start session and set session variables
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    $_SESSION['profile_image'] = $profile_image;
    $_SESSION['login_method'] = 'google';
    
    // Set remember me cookie
    $session_expiration = time() + (30 * 24 * 60 * 60); // 30 days
    setcookie('remember_me', $user['id'], $session_expiration, '/');
    
    // Check if 2FA is enabled
    if ($user['two_factor_enabled']) {
        $response['success'] = true;
        $response['twoFactorRequired'] = true;
        $response['message'] = 'Please enter your 2FA code';
    } else {
        $response['success'] = true;
        $response['message'] = 'Google Sign-In successful';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Authentication failed: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>