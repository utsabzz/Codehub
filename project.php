<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

// Handle API requests
if (isset($_GET['action'])) {
    handleApiRequest();
    exit;
}

// Include database connection
require_once 'db_connection.php';

// Get repository ID from URL
 $repoId = $_GET['id'] ?? null;

if (!$repoId) {
    header('Location: dashboard.php');
    exit;
}

// Get repository information
 $sql = "SELECT * FROM repositories WHERE id = ?";
 $stmt = $conn->prepare($sql);
 $stmt->bind_param("i", $repoId);
 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: dashboard.php');
    exit;
}

 $repo = $result->fetch_assoc();

// Get user information
 $user_id = $_SESSION['user_id'];
 $user_sql = "SELECT id, username, email FROM users WHERE id = ?";
 $user_stmt = $conn->prepare($user_sql);
 $user_stmt->bind_param("i", $user_id);
 $user_stmt->execute();
 $user_result = $user_stmt->get_result();
 $user = $user_result->fetch_assoc();

// Handle form submissions
 $error = '';
 $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_file':
            $path = $_POST['path'] ?? '';
            $fileName = $_POST['file_name'] ?? '';
            $content = $_POST['content'] ?? '';
            
            $result = createFile($repoId, $path, $fileName, $content);
            echo json_encode($result);
            exit;
            
        case 'update_file':
            $oldPath = $_POST['old_path'] ?? '';
            $newName = $_POST['new_name'] ?? '';
            $content = $_POST['content'] ?? '';
            
            $result = updateFile($repoId, $oldPath, $newName, $content);
            echo json_encode($result);
            exit;
            
        case 'create_folder':
            $path = $_POST['path'] ?? '';
            $folderName = $_POST['folder_name'] ?? '';
            
            $result = createFolder($repoId, $path, $folderName);
            echo json_encode($result);
            exit;
            
        case 'upload_files':
            $path = $_POST['path'] ?? '';
            
            $result = uploadFiles($repoId, $path);
            echo json_encode($result);
            exit;
            
        case 'delete_item':
            $path = $_POST['path'] ?? '';
            $type = $_POST['type'] ?? '';
            
            $result = deleteItem($repoId, $path, $type);
            echo json_encode($result);
            exit;
    }
}

// API request handler
function handleApiRequest() {
    global $conn;
    
    $action = $_GET['action'];
    $repoId = $_GET['repo_id'] ?? null;
    
    if (!$repoId) {
        http_response_code(400);
        echo json_encode(['error' => 'Repository ID is required']);
        exit;
    }
    
    switch ($action) {
        case 'get_repo_data':
            // Get repository information
            $sql = "SELECT * FROM repositories WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $repoId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Repository not found']);
                exit;
            }
            
            $repo = $result->fetch_assoc();
            echo json_encode($repo);
            break;
            
        case 'get_repo_files':
            // Get files in a directory
            $path = $_GET['path'] ?? '';
            
            // Get repository path
            $sql = "SELECT path FROM repositories WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $repoId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Repository not found']);
                exit;
            }
            
            $repo = $result->fetch_assoc();
            $repoPath = $repo['path'];
            
            // Build full path
            $basePath = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repoPath;
            if (!empty($path)) {
                $basePath .= '/' . $path;
            }
            
            $files = [];
            
            if (is_dir($basePath)) {
                $iterator = new DirectoryIterator($basePath);
                foreach ($iterator as $item) {
                    if ($item->isDot()) continue;
                    
                    $files[] = [
                        'name' => $item->getFilename(),
                        'path' => (!empty($path) ? $path . '/' : '') . $item->getFilename(),
                        'type' => $item->isDir() ? 'folder' : 'file',
                        'modified' => date('Y-m-d H:i:s', $item->getMTime())
                    ];
                }
            }
            
            echo json_encode($files);
            break;
            
        case 'get_file_content':
            // Get file content
            $filePath = $_GET['file'] ?? '';
            
            // Get repository path
            $sql = "SELECT path FROM repositories WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $repoId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Repository not found']);
                exit;
            }
            
            $repo = $result->fetch_assoc();
            $repoPath = $repo['path'];
            
            // Build full path
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repoPath . '/' . $filePath;
            
            if (!file_exists($fullPath) || is_dir($fullPath)) {
                http_response_code(404);
                echo json_encode(['error' => 'File not found']);
                exit;
            }
            
            $content = file_get_contents($fullPath);
            echo json_encode(['content' => $content]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

// Create a new file
function createFile($repoId, $path, $fileName, $content) {
    global $conn;
    
    // Get repository path
    $sql = "SELECT path FROM repositories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Repository not found'];
    }
    
    $repo = $result->fetch_assoc();
    $repoPath = $repo['path'];
    
    // Build full path
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repoPath;
    if (!empty($path)) {
        $fullPath .= '/' . $path;
    }
    
    // Create directory if it doesn't exist
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
    }
    
    $filePath = $fullPath . '/' . $fileName;
    
    if (file_put_contents($filePath, $content)) {
        return ['success' => true];
    } else {
        return ['success' => false, 'message' => 'Failed to create file'];
    }
}

// Update an existing file
function updateFile($repoId, $oldPath, $newName, $content) {
    global $conn;
    
    // Get repository path
    $sql = "SELECT path FROM repositories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Repository not found'];
    }
    
    $repo = $result->fetch_assoc();
    $repoPath = $repo['path'];
    
    // Build full path
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repoPath . '/' . $oldPath;
    
    if (!file_exists($fullPath) || is_dir($fullPath)) {
        return ['success' => false, 'message' => 'File not found'];
    }
    
    // Extract directory and filename
    $dir = dirname($fullPath);
    $newFullPath = $dir . '/' . $newName;
    
    // Rename file if name changed
    if ($oldPath !== $newName) {
        if (!rename($fullPath, $newFullPath)) {
            return ['success' => false, 'message' => 'Failed to rename file'];
        }
        $fullPath = $newFullPath;
    }
    
    // Update content
    if (file_put_contents($fullPath, $content)) {
        return ['success' => true];
    } else {
        return ['success' => false, 'message' => 'Failed to update file'];
    }
}

// Create a new folder
function createFolder($repoId, $path, $folderName) {
    global $conn;
    
    // Get repository path
    $sql = "SELECT path FROM repositories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Repository not found'];
    }
    
    $repo = $result->fetch_assoc();
    $repoPath = $repo['path'];
    
    // Build full path
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repoPath;
    if (!empty($path)) {
        $fullPath .= '/' . $path;
    }
    
    $folderPath = $fullPath . '/' . $folderName;
    
    if (mkdir($folderPath, 0755, true)) {
        return ['success' => true];
    } else {
        return ['success' => false, 'message' => 'Failed to create folder'];
    }
}

// Upload files
function uploadFiles($repoId, $path) {
    global $conn;
    
    // Get repository path
    $sql = "SELECT path FROM repositories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Repository not found'];
    }
    
    $repo = $result->fetch_assoc();
    $repoPath = $repo['path'];
    
    // Build full path
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repoPath;
    if (!empty($path)) {
        $fullPath .= '/' . $path;
    }
    
    // Create directory if it doesn't exist
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
    }
    
    $uploadedFiles = [];
    
    foreach ($_FILES['files']['tmp_name'] as $key => $tmpName) {
        $fileName = $_FILES['files']['name'][$key];
        $fileError = $_FILES['files']['error'][$key];
        
        if ($fileError === UPLOAD_ERR_OK) {
            $destination = $fullPath . '/' . $fileName;
            
            if (move_uploaded_file($tmpName, $destination)) {
                $uploadedFiles[] = $fileName;
            }
        }
    }
    
    if (count($uploadedFiles) > 0) {
        return ['success' => true, 'files' => $uploadedFiles];
    } else {
        return ['success' => false, 'message' => 'Failed to upload files'];
    }
}

// Delete files or folders
function deleteItem($repoId, $path, $type) {
    global $conn;
    
    // Get repository path
    $sql = "SELECT path FROM repositories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Repository not found'];
    }
    
    $repo = $result->fetch_assoc();
    $repoPath = $repo['path'];
    
    // Build full path
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repoPath . '/' . $path;
    
    if (!file_exists($fullPath)) {
        return ['success' => false, 'message' => 'Item not found'];
    }
    
    if ($type === 'folder') {
        // Recursively delete directory
        function deleteDirectory($dir) {
            if (!is_dir($dir)) {
                return false;
            }
            
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? deleteDirectory($path) : unlink($path);
            }
            
            return rmdir($dir);
        }
        
        if (deleteDirectory($fullPath)) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'Failed to delete folder'];
        }
    } else {
        // Delete file
        if (unlink($fullPath)) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'Failed to delete file'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Explorer - CodeHub APS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --github-green: #2ea043;
            --github-blue: #0969da;
            --github-border: #d1d9e0;
            --github-bg: #ffffff;
            --github-hover-bg: #f6f8fa;
            --github-text: #24292f;
            --github-text-secondary: #656d76;
            --github-tab-active: #f6f8fa;
            --github-tab-border: #d1d9e0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans", Helvetica, Arial, sans-serif;
            color: var(--github-text);
            background-color: var(--github-bg);
            line-height: 1.5;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 16px;
        }

        /* Header Styles */
        header {
            background-color: var(--github-bg);
            border-bottom: 1px solid var(--github-border);
            padding: 16px 0;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .repo-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .repo-name {
            font-weight: 600;
            font-size: 20px;
            color: var(--github-text);
        }

        .repo-visibility {
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 12px;
            background-color: var(--github-hover-bg);
            color: var(--github-text-secondary);
        }

        .repo-actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 5px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid var(--github-border);
            background-color: var(--github-bg);
            color: var(--github-text);
            transition: all 0.2s;
        }

        .btn:hover {
            background-color: var(--github-hover-bg);
        }

        .btn-primary {
            background-color: var(--github-green);
            color: white;
            border-color: var(--github-green);
        }

        .btn-primary:hover {
            background-color: #2c974b;
        }

        .btn-danger {
            background-color: #d73a49;
            color: white;
            border-color: #d73a49;
        }

        .btn-danger:hover {
            background-color: #cb2431;
        }

        /* Tab Navigation */
        .tab-nav {
            display: flex;
            border-bottom: 1px solid var(--github-tab-border);
            margin-top: 16px;
            overflow-x: auto;
        }

        .tab {
            padding: 8px 16px;
            font-size: 14px;
            color: var(--github-text-secondary);
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .tab:hover {
            color: var(--github-text);
        }

        .tab.active {
            color: var(--github-text);
            border-bottom-color: #fd7e14;
            font-weight: 600;
        }

        /* File Explorer */
        .file-explorer {
            margin-top: 16px;
            border: 1px solid var(--github-border);
            border-radius: 6px;
            overflow: hidden;
        }

        .file-explorer-header {
            background-color: var(--github-hover-bg);
            padding: 8px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--github-border);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            flex-wrap: wrap;
        }

        .breadcrumb-item {
            color: var(--github-text-secondary);
            cursor: pointer;
        }

        .breadcrumb-item:hover {
            color: var(--github-text);
        }

        .breadcrumb-separator {
            color: var(--github-text-secondary);
        }

        .file-actions {
            display: flex;
            gap: 8px;
        }

        .file-list {
            background-color: var(--github-bg);
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 8px 16px;
            border-bottom: 1px solid var(--github-border);
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .file-item:hover {
            background-color: var(--github-hover-bg);
        }

        .file-item:last-child {
            border-bottom: none;
        }

        .file-icon {
            margin-right: 8px;
            color: var(--github-text-secondary);
            width: 16px;
            text-align: center;
        }

        .file-icon.folder {
            color: #79b8ff;
        }

        .file-icon.file {
            color: var(--github-text-secondary);
        }

        .file-icon.markdown {
            color: #42a5f5;
        }

        .file-name {
            flex-grow: 1;
            font-size: 14px;
        }

        .file-actions-menu {
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .file-item:hover .file-actions-menu {
            opacity: 1;
        }

        .file-action-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--github-text-secondary);
            padding: 4px;
            border-radius: 4px;
        }

        .file-action-btn:hover {
            background-color: var(--github-hover-bg);
            color: var(--github-text);
        }

        .file-message {
            font-size: 12px;
            color: var(--github-text-secondary);
            margin-right: 16px;
        }

        .file-time {
            font-size: 12px;
            color: var(--github-text-secondary);
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background-color: var(--github-bg);
            border-radius: 6px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 16px;
            border-bottom: 1px solid var(--github-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-weight: 600;
            font-size: 16px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--github-text-secondary);
        }

        .modal-body {
            padding: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--github-border);
            border-radius: 6px;
            font-size: 14px;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--github-blue);
            box-shadow: 0 0 0 3px rgba(9, 105, 218, 0.2);
        }

        .file-drop-area {
            border: 2px dashed var(--github-border);
            border-radius: 6px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .file-drop-area:hover, .file-drop-area.active {
            border-color: var(--github-blue);
            background-color: rgba(9, 105, 218, 0.05);
        }

        .file-drop-icon {
            font-size: 48px;
            color: var(--github-text-secondary);
            margin-bottom: 16px;
        }

        .file-drop-text {
            font-size: 16px;
            margin-bottom: 8px;
        }

        .file-drop-hint {
            font-size: 14px;
            color: var(--github-text-secondary);
        }

        .file-list-preview {
            margin-top: 16px;
            max-height: 200px;
            overflow-y: auto;
        }

        .file-preview-item {
            display: flex;
            align-items: center;
            padding: 8px;
            border-bottom: 1px solid var(--github-border);
        }

        .file-preview-item:last-child {
            border-bottom: none;
        }

        .file-preview-icon {
            margin-right: 8px;
            color: var(--github-text-secondary);
        }

        .file-preview-name {
            flex-grow: 1;
            font-size: 14px;
        }

        .file-preview-size {
            font-size: 12px;
            color: var(--github-text-secondary);
        }

        .modal-footer {
            padding: 16px;
            border-top: 1px solid var(--github-border);
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: var(--github-text);
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 8px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s;
            z-index: 2000;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast.success {
            background-color: var(--github-green);
        }

        .toast.error {
            background-color: #cf222e;
        }

        .toast.info {
            background-color: var(--github-blue);
        }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            padding: 40px;
            text-align: center;
            color: var(--github-text-secondary);
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .empty-state-title {
            font-size: 18px;
            margin-bottom: 8px;
        }

        .empty-state-description {
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .file-explorer-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .file-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .file-item {
                flex-wrap: wrap;
            }
            
            .file-message {
                width: 100%;
                margin-top: 4px;
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="repo-info">
                    <span class="repo-name" id="repo-name"><?php echo htmlspecialchars($repo['name']); ?></span>
                    <span class="repo-visibility" id="repo-visibility"><?php echo ucfirst($repo['visibility']); ?></span>
                </div>
                <div class="repo-actions">
                    <button class="btn" id="branch-btn">
                        <i class="fas fa-code-branch"></i> <span id="branch-name"><?php echo htmlspecialchars($repo['default_branch']); ?></span>
                    </button>
                    <button class="btn" id="code-btn">
                        <i class="fas fa-download"></i> Code
                    </button>
                </div>
            </div>
            <div class="tab-nav">
                <div class="tab active" data-tab="code">
                    <i class="fas fa-code"></i> Code
                </div>
                <div class="tab" data-tab="issues">
                    <i class="fas fa-exclamation-circle"></i> Issues
                </div>
                <div class="tab" data-tab="pull-requests">
                    <i class="fas fa-code-branch"></i> Pull requests
                </div>
                <div class="tab" data-tab="projects">
                    <i class="fas fa-project-diagram"></i> Projects
                </div>
                <div class="tab" data-tab="wiki">
                    <i class="fas fa-book"></i> Wiki
                </div>
                <div class="tab" data-tab="security">
                    <i class="fas fa-shield-alt"></i> Security
                </div>
                <div class="tab" data-tab="insights">
                    <i class="fas fa-chart-line"></i> Insights
                </div>
                <div class="tab" data-tab="settings">
                    <i class="fas fa-cog"></i> Settings
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="file-explorer">
            <div class="file-explorer-header">
                <div class="breadcrumb" id="breadcrumb">
                    <span class="breadcrumb-item" data-path=""><?php echo htmlspecialchars($repo['name']); ?></span>
                </div>
                <div class="file-actions">
                    <button class="btn" id="add-file-btn">
                        <i class="fas fa-file-plus"></i> Add file
                    </button>
                    <button class="btn" id="add-folder-btn">
                        <i class="fas fa-folder-plus"></i> Add folder
                    </button>
                    <button class="btn" id="upload-btn">
                        <i class="fas fa-upload"></i> Upload files
                    </button>
                </div>
            </div>
            <div class="file-list" id="file-list">
                <!-- Files will be loaded here -->
            </div>
        </div>
    </main>

    <!-- Add File Modal -->
    <div class="modal" id="add-file-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create new file</h3>
                <button class="modal-close" id="close-add-file-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name your file...</label>
                    <input type="text" class="form-input" id="new-file-name" placeholder="example.txt">
                </div>
                <div class="form-group">
                    <label class="form-label">File content</label>
                    <textarea class="form-input" id="new-file-content" rows="10" placeholder="Enter file content here..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" id="cancel-add-file">Cancel</button>
                <button class="btn btn-primary" id="create-new-file">Create new file</button>
            </div>
        </div>
    </div>

    <!-- Edit File Modal -->
    <div class="modal" id="edit-file-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit file</h3>
                <button class="modal-close" id="close-edit-file-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">File name</label>
                    <input type="text" class="form-input" id="edit-file-name" placeholder="example.txt">
                </div>
                <div class="form-group">
                    <label class="form-label">File content</label>
                    <textarea class="form-input" id="edit-file-content" rows="10" placeholder="Enter file content here..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" id="cancel-edit-file">Cancel</button>
                <button class="btn btn-primary" id="save-file">Save changes</button>
            </div>
        </div>
    </div>

    <!-- Add Folder Modal -->
    <div class="modal" id="add-folder-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create new folder</h3>
                <button class="modal-close" id="close-add-folder-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Folder name</label>
                    <input type="text" class="form-input" id="new-folder-name" placeholder="my-folder">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" id="cancel-add-folder">Cancel</button>
                <button class="btn btn-primary" id="create-new-folder">Create folder</button>
            </div>
        </div>
    </div>

    <!-- Upload Files Modal -->
    <div class="modal" id="upload-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Upload files</h3>
                <button class="modal-close" id="close-upload-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="file-drop-area" id="file-drop-area">
                    <div class="file-drop-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="file-drop-text">Drag files here to add them</div>
                    <div class="file-drop-hint">or <a href="#" id="browse-files">browse your files</a></div>
                    <input type="file" id="file-input" multiple style="display: none;">
                </div>
                <div class="file-list-preview" id="file-list-preview">
                    <!-- Selected files will be shown here -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" id="cancel-upload">Cancel</button>
                <button class="btn btn-primary" id="upload-files">
                    <span id="upload-text">Upload files</span>
                    <span class="spinner" id="upload-spinner" style="display: none;"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="delete-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm deletion</h3>
                <button class="modal-close" id="close-delete-modal">&times;</button>
            </div>
            <div class="modal-body">
                <p id="delete-message">Are you sure you want to delete this item?</p>
            </div>
            <div class="modal-footer">
                <button class="btn" id="cancel-delete">Cancel</button>
                <button class="btn btn-danger" id="confirm-delete">Delete</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <i class="fas fa-check-circle" id="toast-icon"></i>
        <span id="toast-message">Operation completed successfully</span>
    </div>

    <script>
        // DOM elements
        const repoName = document.getElementById('repo-name');
        const repoVisibility = document.getElementById('repo-visibility');
        const branchName = document.getElementById('branch-name');
        const fileList = document.getElementById('file-list');
        const breadcrumb = document.getElementById('breadcrumb');
        const addFileBtn = document.getElementById('add-file-btn');
        const addFolderBtn = document.getElementById('add-folder-btn');
        const uploadBtn = document.getElementById('upload-btn');
        const addFileModal = document.getElementById('add-file-modal');
        const editFileModal = document.getElementById('edit-file-modal');
        const addFolderModal = document.getElementById('add-folder-modal');
        const uploadModal = document.getElementById('upload-modal');
        const deleteModal = document.getElementById('delete-modal');
        const closeAddFileModal = document.getElementById('close-add-file-modal');
        const closeEditFileModal = document.getElementById('close-edit-file-modal');
        const closeAddFolderModal = document.getElementById('close-add-folder-modal');
        const closeUploadModal = document.getElementById('close-upload-modal');
        const closeDeleteModal = document.getElementById('close-delete-modal');
        const cancelAddFile = document.getElementById('cancel-add-file');
        const cancelEditFile = document.getElementById('cancel-edit-file');
        const cancelAddFolder = document.getElementById('cancel-add-folder');
        const cancelUpload = document.getElementById('cancel-upload');
        const cancelDelete = document.getElementById('cancel-delete');
        const createNewFile = document.getElementById('create-new-file');
        const saveFile = document.getElementById('save-file');
        const createNewFolder = document.getElementById('create-new-folder');
        const uploadFiles = document.getElementById('upload-files');
        const confirmDelete = document.getElementById('confirm-delete');
        const fileDropArea = document.getElementById('file-drop-area');
        const fileInput = document.getElementById('file-input');
        const browseFiles = document.getElementById('browse-files');
        const fileListPreview = document.getElementById('file-list-preview');
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-message');
        const toastIcon = document.getElementById('toast-icon');
        const uploadText = document.getElementById('upload-text');
        const uploadSpinner = document.getElementById('upload-spinner');
        const deleteMessage = document.getElementById('delete-message');

        // Current path and state
        let currentPath = [];
        let selectedFiles = [];
        let currentFileToEdit = null;
        let currentItemToDelete = null;
        let repoId = <?php echo $repoId; ?>;
        let repoData = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            initializeProject();
            loadFiles();
            setupEventListeners();
        });

        // Initialize project data
        function initializeProject() {
            // Fetch repository data from backend
            fetchRepoData(repoId);
        }

        // Fetch repository data from backend
        async function fetchRepoData(repoId) {
            try {
                const response = await fetch(`?action=get_repo_data&repo_id=${repoId}`);
                if (!response.ok) {
                    throw new Error('Failed to fetch repository data');
                }
                
                repoData = await response.json();
                
                // Update UI with repository data
                repoName.textContent = repoData.name;
                repoVisibility.textContent = repoData.visibility.charAt(0).toUpperCase() + repoData.visibility.slice(1);
                branchName.textContent = repoData.default_branch;
                
                // Update breadcrumb
                updateBreadcrumb();
            } catch (error) {
                console.error('Error fetching repository data:', error);
                showToast('Failed to load repository data', 'error');
            }
        }

        // Setup event listeners
        function setupEventListeners() {
            // Tab navigation
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    
                    // Show toast for non-code tabs
                    if (tab.dataset.tab !== 'code') {
                        showToast(`${tab.textContent.trim()} tab is not implemented in this demo`, 'info');
                    }
                });
            });

            // Add file button
            addFileBtn.addEventListener('click', () => {
                addFileModal.classList.add('active');
            });

            // Add folder button
            addFolderBtn.addEventListener('click', () => {
                addFolderModal.classList.add('active');
            });

            // Upload button
            uploadBtn.addEventListener('click', () => {
                uploadModal.classList.add('active');
            });

            // Close modals
            closeAddFileModal.addEventListener('click', () => {
                addFileModal.classList.remove('active');
            });

            closeEditFileModal.addEventListener('click', () => {
                editFileModal.classList.remove('active');
            });

            closeAddFolderModal.addEventListener('click', () => {
                addFolderModal.classList.remove('active');
            });

            closeUploadModal.addEventListener('click', () => {
                uploadModal.classList.remove('active');
                resetFileUpload();
            });

            closeDeleteModal.addEventListener('click', () => {
                deleteModal.classList.remove('active');
            });

            cancelAddFile.addEventListener('click', () => {
                addFileModal.classList.remove('active');
            });

            cancelEditFile.addEventListener('click', () => {
                editFileModal.classList.remove('active');
            });

            cancelAddFolder.addEventListener('click', () => {
                addFolderModal.classList.remove('active');
            });

            cancelUpload.addEventListener('click', () => {
                uploadModal.classList.remove('active');
                resetFileUpload();
            });

            cancelDelete.addEventListener('click', () => {
                deleteModal.classList.remove('active');
            });

            // Create new file
            createNewFile.addEventListener('click', async () => {
                const fileName = document.getElementById('new-file-name').value;
                const fileContent = document.getElementById('new-file-content').value;
                
                if (!fileName) {
                    showToast('Please enter a file name', 'error');
                    return;
                }
                
                // Show loading state
                createNewFile.disabled = true;
                createNewFile.innerHTML = '<span class="spinner"></span> Creating...';
                
                // Add file to current directory
                const pathString = currentPath.length > 0 ? currentPath.join('/') : '';
                
                const formData = new FormData();
                formData.append('action', 'create_file');
                formData.append('repo_id', repoId);
                formData.append('path', pathString);
                formData.append('file_name', fileName);
                formData.append('content', fileContent);
                
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        loadFiles();
                        showToast(`File "${fileName}" created successfully`, 'success');
                        
                        // Reset form and close modal
                        document.getElementById('new-file-name').value = '';
                        document.getElementById('new-file-content').value = '';
                        addFileModal.classList.remove('active');
                    } else {
                        showToast(data.message || 'Failed to create file', 'error');
                    }
                } catch (error) {
                    console.error('Error creating file:', error);
                    showToast('Failed to create file', 'error');
                } finally {
                    createNewFile.disabled = false;
                    createNewFile.innerHTML = 'Create new file';
                }
            });

            // Save edited file
            saveFile.addEventListener('click', async () => {
                const fileName = document.getElementById('edit-file-name').value;
                const fileContent = document.getElementById('edit-file-content').value;
                
                if (!fileName) {
                    showToast('Please enter a file name', 'error');
                    return;
                }
                
                // Show loading state
                saveFile.disabled = true;
                saveFile.innerHTML = '<span class="spinner"></span> Saving...';
                
                // Update file
                const formData = new FormData();
                formData.append('action', 'update_file');
                formData.append('repo_id', repoId);
                formData.append('old_path', currentFileToEdit);
                formData.append('new_name', fileName);
                formData.append('content', fileContent);
                
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        loadFiles();
                        showToast(`File "${fileName}" updated successfully`, 'success');
                        
                        // Reset form and close modal
                        document.getElementById('edit-file-name').value = '';
                        document.getElementById('edit-file-content').value = '';
                        editFileModal.classList.remove('active');
                        currentFileToEdit = null;
                    } else {
                        showToast(data.message || 'Failed to update file', 'error');
                    }
                } catch (error) {
                    console.error('Error updating file:', error);
                    showToast('Failed to update file', 'error');
                } finally {
                    saveFile.disabled = false;
                    saveFile.innerHTML = 'Save changes';
                }
            });

            // Create new folder
            createNewFolder.addEventListener('click', async () => {
                const folderName = document.getElementById('new-folder-name').value;
                
                if (!folderName) {
                    showToast('Please enter a folder name', 'error');
                    return;
                }
                
                // Show loading state
                createNewFolder.disabled = true;
                createNewFolder.innerHTML = '<span class="spinner"></span> Creating...';
                
                // Add folder to current directory
                const pathString = currentPath.length > 0 ? currentPath.join('/') : '';
                
                const formData = new FormData();
                formData.append('action', 'create_folder');
                formData.append('repo_id', repoId);
                formData.append('path', pathString);
                formData.append('folder_name', folderName);
                
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        loadFiles();
                        showToast(`Folder "${folderName}" created successfully`, 'success');
                        
                        // Reset form and close modal
                        document.getElementById('new-folder-name').value = '';
                        addFolderModal.classList.remove('active');
                    } else {
                        showToast(data.message || 'Failed to create folder', 'error');
                    }
                } catch (error) {
                    console.error('Error creating folder:', error);
                    showToast('Failed to create folder', 'error');
                } finally {
                    createNewFolder.disabled = false;
                    createNewFolder.innerHTML = 'Create folder';
                }
            });

            // File upload
            browseFiles.addEventListener('click', (e) => {
                e.preventDefault();
                fileInput.click();
            });

            fileInput.addEventListener('change', (e) => {
                handleFiles(e.target.files);
            });

            // Drag and drop
            fileDropArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileDropArea.classList.add('active');
            });

            fileDropArea.addEventListener('dragleave', () => {
                fileDropArea.classList.remove('active');
            });

            fileDropArea.addEventListener('drop', (e) => {
                e.preventDefault();
                fileDropArea.classList.remove('active');
                handleFiles(e.dataTransfer.files);
            });

            // Upload files
            uploadFiles.addEventListener('click', async () => {
                if (selectedFiles.length === 0) {
                    showToast('Please select files to upload', 'error');
                    return;
                }
                
                // Show loading state
                uploadText.textContent = 'Uploading...';
                uploadSpinner.style.display = 'inline-block';
                uploadFiles.disabled = true;
                
                // Upload files
                const pathString = currentPath.length > 0 ? currentPath.join('/') : '';
                const formData = new FormData();
                
                formData.append('action', 'upload_files');
                formData.append('repo_id', repoId);
                formData.append('path', pathString);
                
                for (let i = 0; i < selectedFiles.length; i++) {
                    formData.append('files[]', selectedFiles[i]);
                }
                
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        loadFiles();
                        resetFileUpload();
                        uploadModal.classList.remove('active');
                        showToast(`${selectedFiles.length} file(s) uploaded successfully`, 'success');
                    } else {
                        showToast(data.message || 'Failed to upload files', 'error');
                    }
                } catch (error) {
                    console.error('Error uploading files:', error);
                    showToast('Failed to upload files', 'error');
                } finally {
                    uploadText.textContent = 'Upload files';
                    uploadSpinner.style.display = 'none';
                    uploadFiles.disabled = false;
                }
            });

            // Confirm delete
            confirmDelete.addEventListener('click', async () => {
                if (currentItemToDelete) {
                    // Show loading state
                    confirmDelete.disabled = true;
                    confirmDelete.innerHTML = '<span class="spinner"></span> Deleting...';
                    
                    const formData = new FormData();
                    formData.append('action', 'delete_item');
                    formData.append('repo_id', repoId);
                    formData.append('path', currentItemToDelete.path);
                    formData.append('type', currentItemToDelete.type);
                    
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            loadFiles();
                            showToast(`${currentItemToDelete.type === 'folder' ? 'Folder' : 'File'} "${currentItemToDelete.name}" deleted successfully`, 'success');
                            deleteModal.classList.remove('active');
                            currentItemToDelete = null;
                        } else {
                            showToast(data.message || `Failed to delete ${currentItemToDelete.type}`, 'error');
                        }
                    } catch (error) {
                        console.error('Error deleting item:', error);
                        showToast(`Failed to delete ${currentItemToDelete.type}`, 'error');
                    } finally {
                        confirmDelete.disabled = false;
                        confirmDelete.innerHTML = 'Delete';
                    }
                }
            });

            // Branch button
            document.getElementById('branch-btn').addEventListener('click', () => {
                showToast('Branch switching is not implemented in this demo', 'info');
            });

            // Code button
            document.getElementById('code-btn').addEventListener('click', () => {
                showToast('Code download is not implemented in this demo', 'info');
            });
        }

        // Load files from server
        async function loadFiles() {
            if (!repoId) return;
            
            try {
                // Build the path string
                const pathString = currentPath.length > 0 ? currentPath.join('/') : '';
                
                const response = await fetch(`?action=get_repo_files&repo_id=${repoId}&path=${encodeURIComponent(pathString)}`);
                if (!response.ok) {
                    throw new Error('Failed to load files');
                }
                
                const files = await response.json();
                renderFiles(files);
            } catch (error) {
                console.error('Error loading files:', error);
                showToast('Failed to load files', 'error');
            }
        }

        // Render files in current directory
        function renderFiles(files) {
            if (!files || files.length === 0) {
                fileList.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <div class="empty-state-title">This directory is empty</div>
                        <div class="empty-state-description">
                            Create a file or upload files to get started.
                        </div>
                    </div>
                `;
                return;
            }
            
            fileList.innerHTML = files.map(file => `
                <div class="file-item" data-name="${file.name}" data-type="${file.type}" data-path="${file.path}">
                    <i class="fas ${file.type === 'folder' ? 'fa-folder' : 
                              file.name.endsWith('.md') ? 'fa-file-alt' : 
                              file.name.endsWith('.php') ? 'fa-file-code' :
                              file.name.endsWith('.js') ? 'fa-file-code' :
                              file.name.endsWith('.css') ? 'fa-file-code' :
                              file.name.endsWith('.html') ? 'fa-file-code' :
                              file.name.endsWith('.json') ? 'fa-file-code' :
                              'fa-file'} file-icon ${file.type === 'folder' ? 'folder' : 
                              file.name.endsWith('.md') ? 'markdown' : 'file'}"></i>
                    <span class="file-name">${file.name}</span>
                    <div class="file-actions-menu">
                        ${file.type === 'file' ? `
                            <button class="file-action-btn edit-file" title="Edit file">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="file-action-btn view-file" title="View file">
                                <i class="fas fa-eye"></i>
                            </button>
                        ` : ''}
                        <button class="file-action-btn delete-item" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <span class="file-message">Latest commit</span>
                    <span class="file-time">${formatTimeAgo(file.modified)}</span>
                </div>
            `).join('');
            
            // Add click handlers to file items
            document.querySelectorAll('.file-item').forEach(item => {
                const name = item.dataset.name;
                const type = item.dataset.type;
                const path = item.dataset.path;
                
                // Main click handler for navigation
                item.addEventListener('click', (e) => {
                    // Don't trigger navigation if clicking on action buttons
                    if (!e.target.closest('.file-actions-menu')) {
                        if (type === 'folder') {
                            // Navigate to folder
                            navigateToFolder(name);
                        } else {
                            // View file
                            viewFile(path, name);
                        }
                    }
                });
                
                // Edit file button
                const editBtn = item.querySelector('.edit-file');
                if (editBtn) {
                    editBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        editFile(path, name);
                    });
                }
                
                // View file button
                const viewBtn = item.querySelector('.view-file');
                if (viewBtn) {
                    viewBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        viewFile(path, name);
                    });
                }
                
                // Delete button
                const deleteBtn = item.querySelector('.delete-item');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        confirmDeleteItem(name, type, path);
                    });
                }
            });
        }

        // Navigate to a folder
        function navigateToFolder(folderName) {
            currentPath.push(folderName);
            updateBreadcrumb();
            loadFiles();
        }

        // View a file
        function viewFile(filePath, fileName) {
            // Open the file in the editor
            window.location.href = `editor.php?id=${repoId}&file=${encodeURIComponent(filePath)}`;
        }

        // Edit a file
        async function editFile(filePath, fileName) {
            try {
                const response = await fetch(`?action=get_file_content&repo_id=${repoId}&file=${encodeURIComponent(filePath)}`);
                if (!response.ok) {
                    throw new Error('Failed to load file content');
                }
                
                const data = await response.json();
                
                currentFileToEdit = filePath;
                document.getElementById('edit-file-name').value = fileName;
                document.getElementById('edit-file-content').value = data.content;
                editFileModal.classList.add('active');
            } catch (error) {
                console.error('Error loading file content:', error);
                showToast('Failed to load file content', 'error');
            }
        }

        // Update breadcrumb based on current path
        function updateBreadcrumb() {
            breadcrumb.innerHTML = '';
            
            // Add root
            const rootItem = document.createElement('span');
            rootItem.className = 'breadcrumb-item';
            rootItem.textContent = repoName ? repoName.textContent : 'Repository';
            rootItem.dataset.path = '';
            rootItem.addEventListener('click', () => {
                currentPath = [];
                updateBreadcrumb();
                loadFiles();
            });
            breadcrumb.appendChild(rootItem);
            
            // Add path segments
            for (let i = 0; i < currentPath.length; i++) {
                const separator = document.createElement('span');
                separator.className = 'breadcrumb-separator';
                separator.textContent = '/';
                breadcrumb.appendChild(separator);
                
                const pathItem = document.createElement('span');
                pathItem.className = 'breadcrumb-item';
                pathItem.textContent = currentPath[i];
                
                // Create a closure to capture the current path index
                pathItem.addEventListener('click', ((index) => {
                    return () => {
                        currentPath = currentPath.slice(0, index + 1);
                        updateBreadcrumb();
                        loadFiles();
                    };
                })(i));
                
                breadcrumb.appendChild(pathItem);
            }
        }

        // Handle selected files
        function handleFiles(files) {
            selectedFiles = Array.from(files);
            
            if (selectedFiles.length === 0) {
                fileListPreview.innerHTML = '';
                return;
            }
            
            fileListPreview.innerHTML = selectedFiles.map(file => `
                <div class="file-preview-item">
                    <i class="fas ${getFileIcon(file.name)} file-preview-icon"></i>
                    <span class="file-preview-name">${file.name}</span>
                    <span class="file-preview-size">${formatFileSize(file.size)}</span>
                </div>
            `).join('');
        }

        // Get file icon based on file extension
        function getFileIcon(fileName) {
            const extension = fileName.split('.').pop().toLowerCase();
            const iconMap = {
                'md': 'fa-file-alt',
                'php': 'fa-file-code',
                'js': 'fa-file-code',
                'css': 'fa-file-code',
                'html': 'fa-file-code',
                'json': 'fa-file-code',
                'py': 'fa-file-code',
                'java': 'fa-file-code',
                'cpp': 'fa-file-code',
                'c': 'fa-file-code',
                'h': 'fa-file-code',
                'txt': 'fa-file-alt',
                'pdf': 'fa-file-pdf',
                'zip': 'fa-file-archive',
                'jpg': 'fa-file-image',
                'jpeg': 'fa-file-image',
                'png': 'fa-file-image',
                'gif': 'fa-file-image',
                'svg': 'fa-file-image',
                'mp3': 'fa-file-audio',
                'mp4': 'fa-file-video',
                'avi': 'fa-file-video',
                'mov': 'fa-file-video'
            };
            
            return iconMap[extension] || 'fa-file';
        }

        // Reset file upload
        function resetFileUpload() {
            selectedFiles = [];
            fileInput.value = '';
            fileListPreview.innerHTML = '';
            uploadText.textContent = 'Upload files';
            uploadSpinner.style.display = 'none';
        }

        // Confirm deletion of an item
        function confirmDeleteItem(name, type, path) {
            currentItemToDelete = { name, type, path };
            deleteMessage.textContent = `Are you sure you want to delete ${type === 'folder' ? 'the folder' : 'the file'} "${name}"? This action cannot be undone.`;
            deleteModal.classList.add('active');
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Format time ago
        function formatTimeAgo(timestamp) {
            if (!timestamp) return 'Unknown';
            
            const now = new Date();
            const time = new Date(timestamp);
            const diffMs = now - time;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 1) return 'just now';
            if (diffMins < 60) return `${diffMins} minutes ago`;
            if (diffHours < 24) return `${diffHours} hours ago`;
            if (diffDays < 7) return `${diffDays} days ago`;
            
            return time.toLocaleDateString();
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            toastMessage.textContent = message;
            
            // Set icon and class based on type
            if (type === 'success') {
                toastIcon.className = 'fas fa-check-circle';
                toast.className = 'toast success';
            } else if (type === 'error') {
                toastIcon.className = 'fas fa-exclamation-circle';
                toast.className = 'toast error';
            } else {
                toastIcon.className = 'fas fa-info-circle';
                toast.className = 'toast info';
            }
            
            // Show toast
            toast.classList.add('show');
            
            // Hide after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>