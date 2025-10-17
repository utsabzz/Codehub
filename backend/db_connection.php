<?php
// Database configuration
 $db_host = 'localhost';
 $db_user = 'root';
 $db_pass = '';
 $db_name = 'codehub';

// Create connection
 $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
 $conn->set_charset("utf8mb4");

// Return the connection object
return $conn;
?>