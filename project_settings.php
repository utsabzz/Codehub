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
$sql = "SELECT r.*, u.username as owner_username, u.profile_image as owner_profile_image 
        FROM repositories r 
        JOIN users u ON r.owner_id = u.id 
        WHERE r.id = ?";
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

// Get user information
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT id, username, email, profile_image FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$current_user = $user_result->fetch_assoc();

// Check if current user is the repository owner
$is_owner = ($repo['owner_id'] == $user_id);

if (!$is_owner) {
    // User is not the owner, redirect to repository view
    header('Location: view_repo.php?id=' . $repo_id);
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle repository settings update
    if (isset($_POST['update_settings'])) {
        $repo_name = trim($_POST['repo_name']);
        $description = trim($_POST['description']);
        $visibility = $_POST['visibility'];
        $default_branch = trim($_POST['default_branch']);
        
        // Validate repository name
        if (!empty($repo_name) && $repo_name !== $repo['name']) {
            // Check if repository name already exists for this user
            $check_sql = "SELECT id FROM repositories WHERE owner_id = ? AND name = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("isi", $user_id, $repo_name, $repo_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "You already have a repository with this name.";
            } else {
                // Update repository name and path
                $new_path = 'projects/' . $current_user['username'] . '/' . $repo_name;
                $old_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repo['path'];
                $new_full_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $new_path;
                
                // Rename directory if it exists
                if (is_dir($old_path)) {
                    if (!rename($old_path, $new_full_path)) {
                        $error = "Failed to rename repository directory.";
                    }
                }
                
                // Update database
                $update_sql = "UPDATE repositories SET name = ?, description = ?, visibility = ?, default_branch = ?, path = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sssssi", $repo_name, $description, $visibility, $default_branch, $new_path, $repo_id);
                
                if ($update_stmt->execute()) {
                    $success = "Repository settings updated successfully!";
                    // Refresh repository data
                    $repo['name'] = $repo_name;
                    $repo['description'] = $description;
                    $repo['visibility'] = $visibility;
                    $repo['default_branch'] = $default_branch;
                    $repo['path'] = $new_path;
                } else {
                    $error = "Failed to update repository settings.";
                }
            }
        } else {
            // Update other settings without changing name
            $update_sql = "UPDATE repositories SET description = ?, visibility = ?, default_branch = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssi", $description, $visibility, $default_branch, $repo_id);
            
            if ($update_stmt->execute()) {
                $success = "Repository settings updated successfully!";
                $repo['description'] = $description;
                $repo['visibility'] = $visibility;
                $repo['default_branch'] = $default_branch;
            } else {
                $error = "Failed to update repository settings.";
            }
        }
    }
    
    // Handle collaboration request
    if (isset($_POST['send_collab_request'])) {
        $collaborator_username = trim($_POST['collaborator_username']);
        
        // Find user by username
        $user_search_sql = "SELECT id, username FROM users WHERE username = ? AND id != ?";
        $user_search_stmt = $conn->prepare($user_search_sql);
        $user_search_stmt->bind_param("si", $collaborator_username, $user_id);
        $user_search_stmt->execute();
        $user_search_result = $user_search_stmt->get_result();
        
        if ($user_search_result->num_rows > 0) {
            $collaborator = $user_search_result->fetch_assoc();
            $collaborator_id = $collaborator['id'];
            
            // Check if request already exists
            $check_request_sql = "SELECT id FROM collaboration_requests WHERE repository_id = ? AND to_user_id = ? AND status = 'pending'";
            $check_request_stmt = $conn->prepare($check_request_sql);
            $check_request_stmt->bind_param("ii", $repo_id, $collaborator_id);
            $check_request_stmt->execute();
            $check_request_result = $check_request_stmt->get_result();
            
            if ($check_request_result->num_rows === 0) {
                // Check if already a collaborator
                $check_collab_sql = "SELECT id FROM repository_shares WHERE repository_id = ? AND user_id = ?";
                $check_collab_stmt = $conn->prepare($check_collab_sql);
                $check_collab_stmt->bind_param("ii", $repo_id, $collaborator_id);
                $check_collab_stmt->execute();
                $check_collab_result = $check_collab_stmt->get_result();
                
                if ($check_collab_result->num_rows === 0) {
                    // Send collaboration request
                    $request_sql = "INSERT INTO collaboration_requests (repository_id, from_user_id, to_user_id, status, created_at) VALUES (?, ?, ?, 'pending', NOW())";
                    $request_stmt = $conn->prepare($request_sql);
                    $request_stmt->bind_param("iii", $repo_id, $user_id, $collaborator_id);
                    
                    if ($request_stmt->execute()) {
                        $collab_success = "Collaboration request sent to $collaborator_username!";
                    } else {
                        $collab_error = "Failed to send collaboration request.";
                    }
                } else {
                    $collab_error = "User is already a collaborator.";
                }
            } else {
                $collab_error = "A pending collaboration request already exists for this user.";
            }
        } else {
            $collab_error = "User '$collaborator_username' not found.";
        }
    }
    
    // Handle delete repository
    if (isset($_POST['delete_repository'])) {
        $confirmation_name = trim($_POST['confirmation_name']);
        
        if ($confirmation_name === $repo['name']) {
            // Delete repository files
            $repo_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repo['path'];
            if (is_dir($repo_path)) {
                deleteDirectory($repo_path);
            }
            
            // Delete from database
            $delete_sql = "DELETE FROM repositories WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $repo_id);
            
            if ($delete_stmt->execute()) {
                // Also delete related records
                $cleanup_sql = "DELETE FROM repository_shares WHERE repository_id = ?";
                $cleanup_stmt = $conn->prepare($cleanup_sql);
                $cleanup_stmt->bind_param("i", $repo_id);
                $cleanup_stmt->execute();
                
                $cleanup_sql2 = "DELETE FROM collaboration_requests WHERE repository_id = ?";
                $cleanup_stmt2 = $conn->prepare($cleanup_sql2);
                $cleanup_stmt2->bind_param("i", $repo_id);
                $cleanup_stmt2->execute();
                
                header('Location: dashboard.php?deleted=1');
                exit;
            } else {
                $delete_error = "Failed to delete repository from database.";
            }
        } else {
            $delete_error = "Repository name confirmation does not match.";
        }
    }
}

// Get pending collaboration requests
$pending_requests_sql = "SELECT cr.*, u.username as from_username, u.profile_image as from_profile_image, r.name as repo_name 
                        FROM collaboration_requests cr 
                        JOIN users u ON cr.from_user_id = u.id 
                        JOIN repositories r ON cr.repository_id = r.id 
                        WHERE cr.to_user_id = ? AND cr.status = 'pending' 
                        ORDER BY cr.created_at DESC";
$pending_requests_stmt = $conn->prepare($pending_requests_sql);
$pending_requests_stmt->bind_param("i", $user_id);
$pending_requests_stmt->execute();
$pending_requests_result = $pending_requests_stmt->get_result();
$pending_requests = $pending_requests_result->fetch_all(MYSQLI_ASSOC);

// Get current collaborators
$collaborators_sql = "SELECT u.id, u.username, u.profile_image, rs.created_at 
                     FROM repository_shares rs 
                     JOIN users u ON rs.user_id = u.id 
                     WHERE rs.repository_id = ? 
                     ORDER BY rs.created_at DESC";
$collaborators_stmt = $conn->prepare($collaborators_sql);
$collaborators_stmt->bind_param("i", $repo_id);
$collaborators_stmt->execute();
$collaborators_result = $collaborators_stmt->get_result();
$collaborators = $collaborators_result->fetch_all(MYSQLI_ASSOC);

// Get sent collaboration requests
$sent_requests_sql = "SELECT cr.*, u.username as to_username, u.profile_image as to_profile_image 
                     FROM collaboration_requests cr 
                     JOIN users u ON cr.to_user_id = u.id 
                     WHERE cr.repository_id = ? AND cr.status = 'pending' 
                     ORDER BY cr.created_at DESC";
$sent_requests_stmt = $conn->prepare($sent_requests_sql);
$sent_requests_stmt->bind_param("i", $repo_id);
$sent_requests_stmt->execute();
$sent_requests_result = $sent_requests_stmt->get_result();
$sent_requests = $sent_requests_result->fetch_all(MYSQLI_ASSOC);

// Helper function to delete directory recursively
function deleteDirectory($dir) {
    if (!is_dir($dir)) return true;
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo htmlspecialchars($repo['name']); ?> - CodeHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .sidebar-item {
            transition: all 0.2s ease;
        }
        
        .sidebar-item:hover {
            background: linear-gradient(90deg, rgba(9, 105, 218, 0.1) 0%, transparent 100%);
            border-left: 2px solid #0969da;
            padding-left: 14px;
        }
        
        .tab-active {
            border-bottom: 2px solid #f97316;
            color: #f97316;
        }
        
        .danger-zone {
            border: 2px solid #ef4444;
            background: linear-gradient(135deg, #fef2f2 0%, #fff 100%);
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
            margin: 10% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
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
        
        .notification-badge {
            animation: bounce 1s infinite;
        }
        
        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-3px);
            }
        }
        
        .collaborator-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e1e4e8;
        }
        
        .collaborator-item:last-child {
            border-bottom: none;
        }
        
        .request-item {
            padding: 12px;
            border: 1px solid #e1e4e8;
            border-radius: 8px;
            margin-bottom: 12px;
            background: #f8f9fa;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Left Section -->
                <div class="flex items-center space-x-4">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <i class="fas fa-code text-orange-500 text-2xl"></i>
                        <span class="ml-2 text-xl font-bold">CodeHub</span>
                    </div>
                    
                    <!-- Search Bar -->
                    <div class="hidden md:block">
                        <div class="relative">
                            <input type="text" 
                                   placeholder="Search CodeHub" 
                                   class="w-96 px-4 py-2 pl-10 pr-4 text-sm bg-gray-100 border border-gray-300 rounded-lg focus:outline-none focus:border-orange-500 focus:bg-white transition-all">
                            <i class="fas fa-search absolute left-3 top-2.5 text-gray-400"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Right Section -->
                <div class="flex items-center space-x-4">
                    <!-- Notifications -->
                    <button class="relative text-gray-600 hover:text-gray-900" onclick="toggleNotifications()">
                        <i class="fas fa-bell text-lg"></i>
                        <?php if (count($pending_requests) > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center notification-badge">
                            <?php echo count($pending_requests); ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Profile -->
                    <div class="relative group">
                        <button class="flex items-center space-x-2">
                            <img src="<?php echo !empty($current_user['profile_image']) ? $current_user['profile_image'] : 'https://picsum.photos/seed/user' . $current_user['id'] . '/32/32.jpg'; ?>" alt="Profile" class="h-8 w-8 rounded-full">
                            <i class="fas fa-chevron-down text-xs text-gray-600"></i>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="p-4 border-b border-gray-200">
                                <p class="font-semibold text-gray-900">Signed in as</p>
                                <p class="text-sm text-gray-600"><?php echo $current_user['email']; ?></p>
                            </div>
                            <div class="py-2">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your profile</a>
                                <a href="dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your repositories</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your projects</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your stars</a>
                            </div>
                            <div class="border-t border-gray-200 py-2">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                <a href="backend/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Notifications Dropdown -->
    <div id="notificationsDropdown" class="absolute right-4 top-16 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50 hidden">
        <div class="p-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-900">Notifications</h3>
        </div>
        <div class="max-h-96 overflow-y-auto">
            <?php if (empty($pending_requests)): ?>
                <div class="p-4 text-center text-gray-500">
                    <i class="fas fa-bell-slash text-2xl mb-2"></i>
                    <p>No new notifications</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending_requests as $request): ?>
                <div class="p-4 border-b border-gray-200">
                    <div class="flex items-start space-x-3">
                        <img src="<?php echo !empty($request['from_profile_image']) ? $request['from_profile_image'] : 'https://picsum.photos/seed/user' . $request['from_user_id'] . '/32/32.jpg'; ?>" alt="<?php echo htmlspecialchars($request['from_username']); ?>" class="h-8 w-8 rounded-full">
                        <div class="flex-1">
                            <p class="text-sm text-gray-900">
                                <strong><?php echo htmlspecialchars($request['from_username']); ?></strong> wants to collaborate on <strong><?php echo htmlspecialchars($request['repo_name']); ?></strong>
                            </p>
                            <p class="text-xs text-gray-500 mt-1"><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></p>
                            <div class="flex space-x-2 mt-2">
                                <form method="POST" action="handle_collaboration.php" class="m-0">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <button type="submit" class="px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700">
                                        Accept
                                    </button>
                                </form>
                                <form method="POST" action="handle_collaboration.php" class="m-0">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="p-4 border-t border-gray-200">
            <a href="notifications.php" class="text-sm text-blue-600 hover:text-blue-700">View all notifications</a>
        </div>
    </div>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r border-gray-200 min-h-screen">
            <div class="p-4">
                <!-- Repository Info -->
                <div class="mb-6">
                    <div class="flex items-center space-x-2 mb-2">
                        <i class="fas fa-<?php echo $repo['visibility'] === 'private' ? 'lock' : 'book'; ?> text-gray-400"></i>
                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($repo['name']); ?></span>
                    </div>
                    <p class="text-sm text-gray-600"><?php echo !empty($repo['description']) ? htmlspecialchars($repo['description']) : 'No description'; ?></p>
                </div>
                
                <!-- Navigation -->
                <nav class="space-y-1">
                    <a href="view_repo.php?id=<?php echo $repo_id; ?>" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-code text-gray-500"></i>
                        <span>Code</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-code-branch text-gray-500"></i>
                        <span>Pull Requests</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-exclamation-circle text-gray-500"></i>
                        <span>Issues</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-chart-line text-gray-500"></i>
                        <span>Insights</span>
                    </a>
                    <a href="settings.php?id=<?php echo $repo_id; ?>" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-blue-700 rounded-lg bg-blue-50">
                        <i class="fas fa-cog text-blue-600"></i>
                        <span>Settings</span>
                    </a>
                </nav>
                
                <!-- Settings Sub-navigation -->
                <div class="mt-8">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Settings</h3>
                    <nav class="space-y-1">
                        <a href="#general" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                            <i class="fas fa-cog text-gray-500"></i>
                            <span>General</span>
                        </a>
                        <a href="#collaboration" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                            <i class="fas fa-users text-gray-500"></i>
                            <span>Collaboration</span>
                        </a>
                        <a href="#danger" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-red-700 rounded-lg">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                            <span>Danger Zone</span>
                        </a>
                    </nav>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="max-w-4xl">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Repository Settings</h1>
                    <p class="text-gray-600">Manage your repository settings and collaboration options.</p>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($success)): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                        <p class="text-green-700"><?php echo $success; ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                        <p class="text-red-700"><?php echo $error; ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- General Settings -->
                <section id="general" class="bg-white rounded-lg border border-gray-200 p-6 mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">General Settings</h2>
                    
                    <form method="POST" action="">
                        <div class="space-y-6">
                            <!-- Repository Name -->
                            <div>
                                <label for="repo_name" class="block text-sm font-medium text-gray-700 mb-2">Repository Name</label>
                                <input type="text" id="repo_name" name="repo_name" value="<?php echo htmlspecialchars($repo['name']); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <p class="text-xs text-gray-500 mt-1">Great repository names are short and memorable.</p>
                            </div>
                            
                            <!-- Description -->
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea id="description" name="description" rows="3" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"><?php echo htmlspecialchars($repo['description']); ?></textarea>
                                <p class="text-xs text-gray-500 mt-1">Describe what your repository is about.</p>
                            </div>
                            
                            <!-- Visibility -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Visibility</label>
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="radio" name="visibility" value="public" <?php echo $repo['visibility'] === 'public' ? 'checked' : ''; ?> class="mr-3">
                                        <div>
                                            <span class="font-medium text-gray-900">Public</span>
                                            <p class="text-sm text-gray-500">Anyone on the internet can see this repository.</p>
                                        </div>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="visibility" value="private" <?php echo $repo['visibility'] === 'private' ? 'checked' : ''; ?> class="mr-3">
                                        <div>
                                            <span class="font-medium text-gray-900">Private</span>
                                            <p class="text-sm text-gray-500">You choose who can see and commit to this repository.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Default Branch -->
                            <div>
                                <label for="default_branch" class="block text-sm font-medium text-gray-700 mb-2">Default Branch</label>
                                <input type="text" id="default_branch" name="default_branch" value="<?php echo htmlspecialchars($repo['default_branch']); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <p class="text-xs text-gray-500 mt-1">The default branch name for this repository.</p>
                            </div>
                            
                            <!-- Update Button -->
                            <div class="flex justify-end">
                                <button type="submit" name="update_settings" class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 font-medium">
                                    Update Settings
                                </button>
                            </div>
                        </div>
                    </form>
                </section>

                <!-- Collaboration Settings -->
                <section id="collaboration" class="bg-white rounded-lg border border-gray-200 p-6 mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Collaboration</h2>
                    
                    <!-- Send Collaboration Request -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Send Collaboration Request</h3>
                        
                        <?php if (isset($collab_success)): ?>
                        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                <p class="text-green-700"><?php echo $collab_success; ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($collab_error)): ?>
                        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                                <p class="text-red-700"><?php echo $collab_error; ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" class="flex space-x-2">
                            <input type="text" name="collaborator_username" placeholder="Enter username" required 
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500" 
                                   placeholder="e.g., johndoe">
                            <button type="submit" name="send_collab_request" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                                Send Request
                            </button>
                        </form>
                        <p class="text-xs text-gray-500 mt-2">The user will receive a notification and can accept or reject your collaboration request.</p>
                    </div>
                    
                    <!-- Current Collaborators -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Current Collaborators</h3>
                        <?php if (empty($collaborators)): ?>
                            <p class="text-gray-500">No collaborators yet.</p>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($collaborators as $collab): ?>
                                <div class="collaborator-item">
                                    <div class="flex items-center space-x-3">
                                        <img src="<?php echo !empty($collab['profile_image']) ? $collab['profile_image'] : 'https://picsum.photos/seed/user' . $collab['id'] . '/32/32.jpg'; ?>" 
                                             alt="<?php echo htmlspecialchars($collab['username']); ?>" class="h-8 w-8 rounded-full">
                                        <div>
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($collab['username']); ?></p>
                                            <p class="text-xs text-gray-500">Added <?php echo date('M j, Y', strtotime($collab['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <form method="POST" action="remove_collaborator.php" class="m-0">
                                        <input type="hidden" name="user_id" value="<?php echo $collab['id']; ?>">
                                        <input type="hidden" name="repo_id" value="<?php echo $repo_id; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sent Requests -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Pending Requests</h3>
                        <?php if (empty($sent_requests)): ?>
                            <p class="text-gray-500">No pending collaboration requests.</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($sent_requests as $request): ?>
                                <div class="request-item">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <img src="<?php echo !empty($request['to_profile_image']) ? $request['to_profile_image'] : 'https://picsum.photos/seed/user' . $request['to_user_id'] . '/32/32.jpg'; ?>" 
                                                 alt="<?php echo htmlspecialchars($request['to_username']); ?>" class="h-8 w-8 rounded-full">
                                            <div>
                                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($request['to_username']); ?></p>
                                                <p class="text-xs text-gray-500">Sent <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></p>
                                            </div>
                                        </div>
                                        <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Danger Zone -->
                <section id="danger" class="danger-zone rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Danger Zone</h2>
                    
                    <!-- Delete Repository -->
                    <div class="flex items-center justify-between p-4 bg-red-50 rounded-lg">
                        <div>
                            <h3 class="font-medium text-gray-900">Delete this repository</h3>
                            <p class="text-sm text-gray-600 mt-1">Once you delete a repository, there is no going back. Please be certain.</p>
                        </div>
                        <button onclick="showDeleteModal()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">
                            Delete Repository
                        </button>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-lg font-semibold text-gray-900">Delete Repository</h3>
            </div>
            <div class="modal-body">
                <p class="text-gray-600 mb-4">This action <strong>cannot</strong> be undone. This will permanently delete the <strong><?php echo htmlspecialchars($repo['name']); ?></strong> repository, wiki, issues, comments, packages, and remove all collaborator associations.</p>
                
                <?php if (isset($delete_error)): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-red-700 text-sm"><?php echo $delete_error; ?></p>
                </div>
                <?php endif; ?>
                
                <p class="text-sm text-gray-600 mb-2">Please type <strong><?php echo htmlspecialchars($repo['name']); ?></strong> to confirm:</p>
                <form method="POST" action="" id="deleteForm">
                    <input type="text" name="confirmation_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Enter repository name">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hideDeleteModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                    Cancel
                </button>
                <button type="submit" form="deleteForm" name="delete_repository" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                    I understand, delete this repository
                </button>
            </div>
        </div>
    </div>

    <script>
        // Notifications dropdown
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationsDropdown');
            dropdown.classList.toggle('hidden');
        }
        
        // Close notifications when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationsDropdown');
            const button = event.target.closest('button');
            
            if (!dropdown.contains(event.target) && button !== document.querySelector('button[onclick="toggleNotifications()"]')) {
                dropdown.classList.add('hidden');
            }
        });
        
        // Delete modal functions
        function showDeleteModal() {
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>