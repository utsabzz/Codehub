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

// Get user information
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT id, username, email, profile_image FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$current_user = $user_result->fetch_assoc();

// Get user's repositories
$repo_sql = "SELECT * FROM repositories WHERE owner_id = ? ORDER BY created_at DESC";
$repo_stmt = $conn->prepare($repo_sql);
$repo_stmt->bind_param("i", $user_id);
$repo_stmt->execute();
$repo_result = $repo_stmt->get_result();
$repositories = $repo_result->fetch_all(MYSQLI_ASSOC);

// Handle file operations
$current_path = isset($_GET['path']) ? $_GET['path'] : '';
$repo_id = isset($_GET['repo_id']) ? intval($_GET['repo_id']) : 0;

// Get repository info if repo_id is provided
$current_repo = null;
if ($repo_id > 0) {
    $repo_info_sql = "SELECT * FROM repositories WHERE id = ? AND owner_id = ?";
    $repo_info_stmt = $conn->prepare($repo_info_sql);
    $repo_info_stmt->bind_param("ii", $repo_id, $user_id);
    $repo_info_stmt->execute();
    $repo_info_result = $repo_info_stmt->get_result();
    $current_repo = $repo_info_result->fetch_assoc();
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_files'])) {
    if ($current_repo && isset($_FILES['files'])) {
        $repo_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $current_repo['path'] . '/' . $current_path;
        
        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            $file_name = $_FILES['files']['name'][$key];
            $target_path = $repo_path . $file_name;
            
            if (move_uploaded_file($tmp_name, $target_path)) {
                // File uploaded successfully
            }
        }
        
        // Refresh page
        header("Location: projects.php?repo_id=" . $repo_id . "&path=" . urlencode($current_path));
        exit;
    }
}

// Handle folder creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_folder'])) {
    if ($current_repo && !empty($_POST['folder_name'])) {
        $folder_name = trim($_POST['folder_name']);
        $repo_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $current_repo['path'] . '/' . $current_path;
        $folder_path = $repo_path . $folder_name;
        
        if (!file_exists($folder_path)) {
            mkdir($folder_path, 0755, true);
        }
        
        // Refresh page
        header("Location: projects.php?repo_id=" . $repo_id . "&path=" . urlencode($current_path));
        exit;
    }
}

// Handle file/folder deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    if ($current_repo && !empty($_POST['item_path'])) {
        $item_path = $_POST['item_path'];
        $full_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $current_repo['path'] . '/' . $item_path;
        
        if (file_exists($full_path)) {
            if (is_dir($full_path)) {
                deleteDirectory($full_path);
            } else {
                unlink($full_path);
            }
        }
        
        // Refresh page
        header("Location: projects.php?repo_id=" . $repo_id . "&path=" . urlencode($current_path));
        exit;
    }
}

// Handle file creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_file'])) {
    if ($current_repo && !empty($_POST['file_name'])) {
        $file_name = trim($_POST['file_name']);
        $repo_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $current_repo['path'] . '/' . $current_path;
        $file_path = $repo_path . $file_name;
        
        if (!file_exists($file_path)) {
            file_put_contents($file_path, '');
        }
        
        // Refresh page
        header("Location: projects.php?repo_id=" . $repo_id . "&path=" . urlencode($current_path));
        exit;
    }
}

// Helper function to delete directory recursively
function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    
    return rmdir($dir);
}

// Get current directory contents
$current_files = [];
$current_folders = [];

if ($current_repo) {
    $repo_full_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $current_repo['path'] . '/' . $current_path;
    
    if (is_dir($repo_full_path)) {
        $items = scandir($repo_full_path);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $item_path = $repo_full_path . $item;
            $relative_path = $current_path . $item;
            
            if (is_dir($item_path)) {
                $current_folders[] = [
                    'name' => $item,
                    'path' => $relative_path,
                    'modified' => filemtime($item_path)
                ];
            } else {
                $current_files[] = [
                    'name' => $item,
                    'path' => $relative_path,
                    'size' => filesize($item_path),
                    'modified' => filemtime($item_path)
                ];
            }
        }
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeHub - Projects</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        .hover-underline:hover {
            text-decoration: underline;
        }
        
        .file-row:hover {
            background-color: #f6f8fa;
        }
        
        .tab-active {
            border-bottom: 2px solid #f97316;
            color: #f97316;
        }
        
        .btn-primary {
            background-color: #2da44e;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2c974b;
        }
        
        .btn-secondary {
            background-color: #f6f8fa;
            color: #24292f;
            border: 1px solid #d0d7de;
        }
        
        .btn-secondary:hover {
            background-color: #f3f4f6;
        }
        
        .btn-orange {
            background-color: #f97316;
            color: white;
        }
        
        .btn-orange:hover {
            background-color: #ea580c;
        }
        
        .code-font {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        }
        
        .dropdown-content {
            display: none;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .file-icon {
            width: 16px;
            height: 16px;
            margin-right: 8px;
        }
        
        .folder-icon {
            color: #54aeff;
        }
        
        .file-link {
            color: #0969da;
        }
        
        .file-link:hover {
            text-decoration: underline;
        }
        
        .breadcrumb-separator {
            color: #57606a;
            margin: 0 4px;
        }
        
        .file-row {
            display: flex;
            align-items: center;
            padding: 8px 16px;
            border-bottom: 1px solid #e1e4e8;
        }
        
        .file-row:last-child {
            border-bottom: none;
        }
        
        .file-name {
            flex: 1;
            display: flex;
            align-items: center;
        }
        
        .file-size {
            color: #57606a;
            font-size: 12px;
            width: 100px;
            text-align: right;
        }
        
        .file-age {
            color: #57606a;
            font-size: 12px;
            width: 125px;
            text-align: right;
        }
        
        .folder-row {
            font-weight: 600;
        }
        
        .folder-row .file-name {
            color: #0969da;
        }
        
        .back-link {
            color: #0969da;
            font-weight: 600;
            display: flex;
            align-items: center;
            padding: 8px 16px;
            border-bottom: 1px solid #e1e4e8;
        }
        
        .back-link:hover {
            background-color: #f6f8fa;
        }
        
        .empty-repo {
            padding: 32px;
            text-align: center;
            color: #57606a;
        }
        
        .empty-repo h3 {
            font-size: 20px;
            margin-bottom: 8px;
            color: #24292f;
        }
        
        .empty-repo p {
            margin-bottom: 16px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 0;
            border-radius: 12px;
            width: 400px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-code-branch text-orange-500 text-2xl"></i>
                        <span class="text-xl font-bold">CodeHub</span>
                    </div>
                    <nav class="hidden md:flex space-x-6">
                        <a href="dashboard.php" class="text-gray-700 hover:text-gray-900 hover-underline">Dashboard</a>
                        <a href="projects.php" class="text-gray-700 hover:text-gray-900 hover-underline tab-active">Projects</a>
                        <a href="#" class="text-gray-700 hover:text-gray-900 hover-underline">Pull requests</a>
                        <a href="#" class="text-gray-700 hover:text-gray-900 hover-underline">Issues</a>
                        <a href="#" class="text-gray-700 hover:text-gray-900 hover-underline">Explore</a>
                    </nav>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <input type="text" placeholder="Search CodeHub" class="w-64 px-3 py-1 bg-gray-100 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div class="dropdown relative">
                        <button class="text-gray-700 hover:text-gray-900">
                            <i class="fas fa-bell text-lg"></i>
                        </button>
                        <div class="dropdown-content absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                            <div class="p-4">
                                <h3 class="font-semibold mb-2">Notifications</h3>
                                <p class="text-sm text-gray-600">You have no new notifications</p>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown relative">
                        <button class="flex items-center space-x-2">
                            <img src="<?php echo !empty($current_user['profile_image']) ? $current_user['profile_image'] : 'https://picsum.photos/seed/user' . $current_user['id'] . '/32/32.jpg'; ?>" alt="User" class="w-8 h-8 rounded-full">
                            <i class="fas fa-chevron-down text-xs text-gray-600"></i>
                        </button>
                        <div class="dropdown-content absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                            <div class="py-2">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Signed in as <?php echo $current_user['username']; ?></a>
                                <hr class="my-2">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your profile</a>
                                <a href="projects.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your repositories</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your projects</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your stars</a>
                                <hr class="my-2">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                <a href="backend/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex space-x-6">
            <!-- Left Column - Repositories List -->
            <div class="w-80">
                <div class="bg-white rounded-lg border border-gray-200 p-4 mb-4">
                    <h2 class="text-lg font-semibold mb-4">Your Projects</h2>
                    <div class="space-y-2">
                        <?php if (empty($repositories)): ?>
                            <p class="text-gray-600 text-sm">No projects yet. <a href="create_repository.php" class="text-blue-600 hover:underline">Create your first project</a></p>
                        <?php else: ?>
                            <?php foreach ($repositories as $repo): ?>
                                <a href="projects.php?repo_id=<?php echo $repo['id']; ?>" 
                                   class="block p-3 rounded-lg hover:bg-gray-50 border border-transparent hover:border-gray-200 <?php echo $current_repo && $current_repo['id'] == $repo['id'] ? 'bg-blue-50 border-blue-200' : ''; ?>">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($repo['name']); ?></div>
                                    <div class="text-sm text-gray-600"><?php echo htmlspecialchars($repo['description'] ?: 'No description'); ?></div>
                                    <div class="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                                        <span><?php echo ucfirst($repo['visibility']); ?></span>
                                        <span><?php echo $repo['stars']; ?> stars</span>
                                        <span><?php echo $repo['forks']; ?> forks</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <a href="create_repository.php" class="w-full btn-primary px-4 py-2 rounded-lg text-sm font-medium flex items-center justify-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>New Project</span>
                    </a>
                </div>
            </div>

            <!-- Right Column - File Explorer -->
            <div class="flex-1">
                <?php if (!$current_repo): ?>
                    <!-- No repository selected -->
                    <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                        <i class="fas fa-folder-open text-4xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">No Project Selected</h3>
                        <p class="text-gray-600 mb-6">Select a project from the left or create a new one to start managing files.</p>
                        <a href="create_repository.php" class="btn-primary px-6 py-2 rounded-lg text-sm font-medium">
                            Create New Project
                        </a>
                    </div>
                <?php else: ?>
                    <!-- File Browser Header -->
                    <div class="bg-white rounded-lg border border-gray-200 mb-4">
                        <div class="flex items-center justify-between p-4">
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-2 text-sm text-gray-600">
                                    <span><?php echo htmlspecialchars($current_repo['owner_username']); ?></span>
                                    <span>/</span>
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($current_repo['name']); ?></span>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <button onclick="showUploadModal()" class="btn-secondary px-4 py-2 rounded text-sm font-medium flex items-center space-x-2">
                                    <i class="fas fa-upload"></i>
                                    <span>Upload</span>
                                </button>
                                <button onclick="showCreateFolderModal()" class="btn-secondary px-4 py-2 rounded text-sm font-medium flex items-center space-x-2">
                                    <i class="fas fa-folder-plus"></i>
                                    <span>New Folder</span>
                                </button>
                                <button onclick="showCreateFileModal()" class="btn-secondary px-4 py-2 rounded text-sm font-medium flex items-center space-x-2">
                                    <i class="fas fa-file-plus"></i>
                                    <span>New File</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- File Explorer -->
                    <div class="bg-white rounded-lg border border-gray-200">
                        <!-- Breadcrumb -->
                        <div class="border-b border-gray-200 px-4 py-3">
                            <div class="flex items-center space-x-2 text-sm">
                                <a href="projects.php?repo_id=<?php echo $repo_id; ?>" class="text-gray-600 hover:text-gray-900"><?php echo htmlspecialchars($current_repo['name']); ?></a>
                                <?php if ($current_path): ?>
                                    <?php $path_parts = explode('/', trim($current_path, '/')); ?>
                                    <?php $current_breadcrumb = ''; ?>
                                    <?php foreach ($path_parts as $part): ?>
                                        <?php $current_breadcrumb .= $part . '/'; ?>
                                        <span class="text-gray-400">/</span>
                                        <a href="projects.php?repo_id=<?php echo $repo_id; ?>&path=<?php echo urlencode($current_breadcrumb); ?>" class="text-gray-600 hover:text-gray-900"><?php echo htmlspecialchars($part); ?></a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div id="fileExplorer">
                            <!-- Back Link -->
                            <?php if ($current_path): ?>
                                <?php $parent_path = dirname($current_path); ?>
                                <?php if ($parent_path !== '.') : ?>
                                    <a href="projects.php?repo_id=<?php echo $repo_id; ?>&path=<?php echo urlencode($parent_path . '/'); ?>" class="back-link">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        <span>..</span>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- Folders -->
                            <?php foreach ($current_folders as $folder): ?>
                                <div class="file-row folder-row">
                                    <div class="file-name">
                                        <i class="fas fa-folder folder-icon file-icon"></i>
                                        <a href="projects.php?repo_id=<?php echo $repo_id; ?>&path=<?php echo urlencode($folder['path'] . '/'); ?>" class="file-link"><?php echo htmlspecialchars($folder['name']); ?></a>
                                    </div>
                                    <div class="file-size">-</div>
                                    <div class="file-age"><?php echo date('M j, Y', $folder['modified']); ?></div>
                                    <div class="w-20 text-right">
                                        <button onclick="showDeleteModal('<?php echo urlencode($folder['path']); ?>', 'folder')" class="text-red-600 hover:text-red-800 text-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <!-- Files -->
                            <?php foreach ($current_files as $file): ?>
                                <div class="file-row">
                                    <div class="file-name">
                                        <?php
                                        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                                        $file_icons = [
                                            'php' => 'fab fa-php text-purple-500',
                                            'js' => 'fab fa-js text-yellow-500',
                                            'css' => 'fab fa-css3-alt text-blue-500',
                                            'html' => 'fab fa-html5 text-orange-500',
                                            'py' => 'fab fa-python text-blue-600',
                                            'json' => 'fas fa-code text-yellow-600',
                                            'md' => 'fas fa-markdown text-blue-400',
                                            'txt' => 'fas fa-file-alt text-gray-500',
                                            'jpg' => 'fas fa-file-image text-green-500',
                                            'png' => 'fas fa-file-image text-green-500',
                                            'gitignore' => 'fas fa-eye-slash text-gray-500',
                                            'license' => 'fas fa-balance-scale text-gray-500'
                                        ];
                                        $icon_class = $file_icons[$extension] ?? 'fas fa-file text-gray-400';
                                        ?>
                                        <i class="<?php echo $icon_class; ?> file-icon"></i>
                                        <a href="editor.php?repo_id=<?php echo $repo_id; ?>&file=<?php echo urlencode($file['path']); ?>" class="file-link"><?php echo htmlspecialchars($file['name']); ?></a>
                                    </div>
                                    <div class="file-size"><?php echo formatFileSize($file['size']); ?></div>
                                    <div class="file-age"><?php echo date('M j, Y', $file['modified']); ?></div>
                                    <div class="w-20 text-right">
                                        <button onclick="showDeleteModal('<?php echo urlencode($file['path']); ?>', 'file')" class="text-red-600 hover:text-red-800 text-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($current_folders) && empty($current_files)): ?>
                                <div class="empty-repo">
                                    <i class="fas fa-folder-open text-4xl text-gray-300 mb-4"></i>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Empty directory</h3>
                                    <p class="text-gray-600">This directory doesn't have any files or folders yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-lg font-semibold text-gray-900">Upload Files</h3>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" class="modal-body">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select files to upload</label>
                    <input type="file" name="files[]" multiple class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideUploadModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                        Cancel
                    </button>
                    <button type="submit" name="upload_files" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                        Upload Files
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Folder Modal -->
    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-lg font-semibold text-gray-900">Create New Folder</h3>
            </div>
            <form method="POST" action="" class="modal-body">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Folder Name</label>
                    <input type="text" name="folder_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" placeholder="Enter folder name">
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideCreateFolderModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                        Cancel
                    </button>
                    <button type="submit" name="create_folder" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                        Create Folder
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create File Modal -->
    <div id="createFileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-lg font-semibold text-gray-900">Create New File</h3>
            </div>
            <form method="POST" action="" class="modal-body">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">File Name</label>
                    <input type="text" name="file_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" placeholder="e.g., index.html, style.css">
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideCreateFileModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                        Cancel
                    </button>
                    <button type="submit" name="create_file" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                        Create File
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-lg font-semibold text-gray-900">Confirm Delete</h3>
            </div>
            <form method="POST" action="" class="modal-body">
                <input type="hidden" name="item_path" id="deleteItemPath">
                <p class="text-gray-600 mb-4" id="deleteMessage">Are you sure you want to delete this item?</p>
                <div class="modal-footer">
                    <button type="button" onclick="hideDeleteModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                        Cancel
                    </button>
                    <button type="submit" name="delete_item" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                        Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Modal functions
        function showUploadModal() {
            document.getElementById('uploadModal').style.display = 'block';
        }

        function hideUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
        }

        function showCreateFolderModal() {
            document.getElementById('createFolderModal').style.display = 'block';
        }

        function hideCreateFolderModal() {
            document.getElementById('createFolderModal').style.display = 'none';
        }

        function showCreateFileModal() {
            document.getElementById('createFileModal').style.display = 'block';
        }

        function hideCreateFileModal() {
            document.getElementById('createFileModal').style.display = 'none';
        }

        function showDeleteModal(itemPath, type) {
            document.getElementById('deleteItemPath').value = itemPath;
            const message = type === 'folder' 
                ? 'Are you sure you want to delete this folder and all its contents? This action cannot be undone.'
                : 'Are you sure you want to delete this file? This action cannot be undone.';
            document.getElementById('deleteMessage').textContent = message;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['uploadModal', 'createFolderModal', 'createFileModal', 'deleteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Add interactive functionality
        document.addEventListener('DOMContentLoaded', function() {
            // File row click handling
            const fileRows = document.querySelectorAll('.file-row');
            fileRows.forEach(row => {
                row.addEventListener('click', function(e) {
                    if (!e.target.closest('a') && !e.target.closest('button')) {
                        const link = this.querySelector('a');
                        if (link) {
                            window.location.href = link.href;
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>

<?php
// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>