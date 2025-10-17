<?php
// Start the session if needed (optional)
// session_start();

// Redirect to dashboard.php (one level up)
header("Location: ../dashboard.php");
exit(); // Always use exit after header redirect
?>
