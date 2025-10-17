<?php
<<<<<<< HEAD
header('Content-Type: application/json');
require_once 'db_connection.php';

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'twoFactorRequired' => false,
    'redirect' => 'dashboard.php'
=======
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
>>>>>>> 8db67218f4d60fac2acfb5216fe9f321801c05e6
];

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get JSON input
<<<<<<< HEAD
$json_input = file_get_contents('php://input');
$googleData = json_decode($json_input, true);

// Validate Google data
if (empty($googleData['idToken']) || empty($googleData['email'])) {
=======
 $json_input = file_get_contents('php://input');
 $data = json_decode($json_input, true);

// Validate input
if (empty($data['id']) || empty($data['email']) || empty($data['idToken'])) {
>>>>>>> 8db67218f4d60fac2acfb5216fe9f321801c05e6
    $response['message'] = 'Invalid Google authentication data';
    echo json_encode($response);
    exit;
}

<<<<<<< HEAD
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
=======
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
>>>>>>> 8db67218f4d60fac2acfb5216fe9f321801c05e6
    
    // Check if account is active
    if ($user['status'] !== 'active') {
        $response['message'] = 'Your account is not active. Please contact support.';
        echo json_encode($response);
        exit;
    }
    
<<<<<<< HEAD
    // Start session and set session variables
=======
    // Update last login and profile image if changed
    $update_sql = "UPDATE users SET last_login = NOW(), profile_image = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $google_image_url, $user['id']);
    $stmt->execute();
    
    // Create session
>>>>>>> 8db67218f4d60fac2acfb5216fe9f321801c05e6
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
<<<<<<< HEAD
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    $_SESSION['profile_image'] = $profile_image;
    $_SESSION['login_method'] = 'google';
    
    // Set remember me cookie
    $session_expiration = time() + (30 * 24 * 60 * 60); // 30 days
    setcookie('remember_me', $user['id'], $session_expiration, '/');
=======
    
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
>>>>>>> 8db67218f4d60fac2acfb5216fe9f321801c05e6
    
    // Check if 2FA is enabled
    if ($user['two_factor_enabled']) {
        $response['success'] = true;
        $response['twoFactorRequired'] = true;
        $response['message'] = 'Please enter your 2FA code';
    } else {
        $response['success'] = true;
<<<<<<< HEAD
        $response['message'] = 'Google Sign-In successful';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Authentication failed: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
=======
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
>>>>>>> 8db67218f4d60fac2acfb5216fe9f321801c05e6
?>