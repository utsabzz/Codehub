<?php
// Set response headers
header('Content-Type: application/json');

// Include database connection
require_once 'db_connection.php';

// Initialize response array
 $response = [
    'success' => false,
    'message' => ''
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
if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
    $response['message'] = 'All fields are required';
    echo json_encode($response);
    exit;
}

// Get and sanitize input
 $username = $conn->real_escape_string(trim($data['username']));
 $email = $conn->real_escape_string(trim($data['email']));
 $password = $data['password'];

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Invalid email format';
    echo json_encode($response);
    exit;
}

// Check if username already exists
 $sql = "SELECT id FROM users WHERE username = ?";
 $stmt = $conn->prepare($sql);
 $stmt->bind_param("s", $username);
 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows > 0) {
    $response['message'] = 'Username already exists';
    echo json_encode($response);
    exit;
}

// Check if email already exists
 $sql = "SELECT id FROM users WHERE email = ?";
 $stmt = $conn->prepare($sql);
 $stmt->bind_param("s", $email);
 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows > 0) {
    $response['message'] = 'Email already exists';
    echo json_encode($response);
    exit;
}

// Hash password
 $hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert new user
 $sql = "INSERT INTO users (username, email, password, status, created_at) 
        VALUES (?, ?, ?, 'active', NOW())";
 $stmt = $conn->prepare($sql);
 $stmt->bind_param("sss", $username, $email, $hashed_password);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Account created successfully';
} else {
    $response['message'] = 'Failed to create account';
}

// Close connection
 $conn->close();

// Return JSON response
echo json_encode($response);
?>