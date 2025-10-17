<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

// Include database connection
require_once 'db_connection.php';

// Get repository ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$repo_id = intval($_GET['id']);

// Get repository information
$sql = "SELECT r.* FROM repositories r WHERE r.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $repo_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Repository not found
    header('Location: dashboard.php');
    exit;
}

$repo = $result->fetch_assoc();

// Create zip file
function createZipFromDirectory($source, $destination) {
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..'))) {
                continue;
            }

            $file = realpath($file);

            if (is_dir($file) === true) {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            } else if (is_file($file) === true) {
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    } else if (is_file($source) === true) {
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    return $zip->close();
}

$repo_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repo['path'];
$zip_file = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/temp/' . $repo['name'] . '.zip';

// Create temp directory if it doesn't exist
if (!is_dir($_SERVER['DOCUMENT_ROOT'] . '/CodeHub/temp')) {
    mkdir($_SERVER['DOCUMENT_ROOT'] . '/CodeHub/temp', 0755, true);
}

// Create zip file
if (createZipFromDirectory($repo_path, $zip_file)) {
    // Set headers for download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $repo['name'] . '.zip"');
    header('Content-Length: ' . filesize($zip_file));
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output the file
    readfile($zip_file);
    
    // Delete the temporary zip file
    unlink($zip_file);
} else {
    echo "Error creating zip file";
}

// Close connection
$conn->close();
?>