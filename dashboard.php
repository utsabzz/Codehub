<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

// Include database connection and helper functions
require_once 'db_connection.php';
require_once 'repository_helper.php';

// Get user information
$user_id = $_SESSION['user_id'];
$sql = "SELECT id, username, email, profile_image FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User not found, redirect to login
    session_destroy();
    header('Location: index.html');
    exit;
}

$user = $result->fetch_assoc();
$username = $user['username'];

// Get user statistics
$repos_count = 0;
$stars_count = 0;
$following_count = 0;

// Count repositories
$sql = "SELECT COUNT(*) as count FROM repositories WHERE owner_username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$repos_count = $result->fetch_assoc()['count'];

// Count stars received
$sql = "SELECT COUNT(*) as count FROM repository_stars rs 
        JOIN repositories r ON rs.repository_id = r.id 
        WHERE r.owner_username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$stars_count = $result->fetch_assoc()['count'];

// Count following
$sql = "SELECT COUNT(*) as count FROM user_following WHERE follower_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$following_count = $result->fetch_assoc()['count'];

// Get user's repositories - FIXED: Show all repositories even if directory doesn't exist
$sql = "SELECT r.*, 
        (SELECT COUNT(*) FROM repository_stars WHERE repository_id = r.id) as stars,
        (SELECT COUNT(*) FROM repository_forks WHERE source_repository_id = r.id) as forks
        FROM repositories r 
        WHERE r.owner_username = ? 
        ORDER BY r.updated_at DESC 
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$repositories = [];

while ($row = $result->fetch_assoc()) {
    // Check if repository exists in filesystem - FIXED PATH
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $row['path'];
    
    if (is_dir($fullPath)) {
        // Get last modified time from filesystem
        $row['last_commit_date'] = date('Y-m-d H:i:s', filemtime($fullPath));
        
        // Try to find README file
        $readmeFile = findReadmeFile($row['path']);
        if ($readmeFile) {
            $readmeContent = getFileContent($row['path'], $readmeFile);
            if ($readmeContent) {
                // Extract first line as description if no description is set
                if (empty($row['description'])) {
                    $lines = explode("\n", $readmeContent);
                    $row['description'] = substr($lines[0], 0, 100);
                    if (strlen($row['description']) === 100) {
                        $row['description'] .= '...';
                    }
                }
            }
        }
    } else {
        // If directory doesn't exist, use database updated_at
        $row['last_commit_date'] = $row['updated_at'];
    }
    
    $repositories[] = $row;
}

// Get pull requests
$sql = "SELECT pr.*, r.name as repo_name 
        FROM pull_requests pr 
        JOIN repositories r ON pr.repository_id = r.id 
        WHERE r.owner_username = ? 
        ORDER BY pr.updated_at DESC 
        LIMIT 3";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$pull_requests = [];

while ($row = $result->fetch_assoc()) {
    $pull_requests[] = $row;
}

// Get issues
$sql = "SELECT i.*, r.name as repo_name 
        FROM issues i 
        JOIN repositories r ON i.repository_id = r.id 
        WHERE r.owner_username = ? 
        ORDER BY i.updated_at DESC 
        LIMIT 3";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$issues = [];

while ($row = $result->fetch_assoc()) {
    $issues[] = $row;
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CodeHub APS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        body {
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
        
        .repo-card {
            transition: all 0.3s ease;
        }
        
        .repo-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }
        
        .pulse-dot {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(34, 197, 94, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
            }
        }
        
        .tab-active {
            border-bottom: 2px solid #0969da;
            color: #0969da;
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
        
        .progress-ring {
            transform: rotate(-90deg);
        }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .code-block {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2px;
            border-radius: 8px;
        }
        
        .code-block-inner {
            background: #0d1117;
            border-radius: 6px;
            padding: 1rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Top Navigation -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Left Section -->
                <div class="flex items-center space-x-4">
                    <!-- Menu Toggle -->
                    <button id="sidebarToggle" class="text-gray-600 hover:text-gray-900 lg:hidden">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    
                    <!-- Logo -->
                    <div class="flex items-center">
                        <svg class="h-8 w-8 text-gray-900" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.30.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                        </svg>
                    </div>
                    
                    <!-- Search Bar -->
                    <div class="hidden md:block">
                        <div class="relative">
                            <input type="text" 
                                   placeholder="Search CodeHub APS" 
                                   class="w-96 px-4 py-2 pl-10 pr-4 text-sm bg-gray-100 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:bg-white transition-all">
                            <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <div class="absolute right-2 top-1.5">
                                <kbd class="px-2 py-1 text-xs bg-gray-200 border border-gray-300 rounded">⌘K</kbd>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Section -->
                <div class="flex items-center space-x-4">
                    <!-- Pull Requests -->
                    <button class="relative text-gray-600 hover:text-gray-900">
                        <i class="fas fa-code-branch text-lg"></i>
                        <span class="absolute -top-1 -right-1 bg-orange-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center"><?php echo count($pull_requests); ?></span>
                    </button>
                    
                    <!-- Issues -->
                    <button class="relative text-gray-600 hover:text-gray-900">
                        <i class="fas fa-exclamation-circle text-lg"></i>
                        <span class="absolute -top-1 -right-1 bg-green-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center"><?php echo count($issues); ?></span>
                    </button>
                    
                    <!-- Marketplace -->
                    <button class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-shopping-cart text-lg"></i>
                    </button>
                    
                    <!-- Notifications -->
                    <button class="relative text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bell text-lg"></i>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center notification-badge">12</span>
                    </button>
                    
                    <!-- Plus Icon -->
                    <button class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-plus-circle text-lg"></i>
                    </button>
                    
                    <!-- Profile -->
                    <div class="relative group">
                        <button class="flex items-center space-x-2">
                            <img src="<?php echo !empty($user['profile_image']) ? $user['profile_image'] : 'https://picsum.photos/seed/user' . $user['id'] . '/32/32.jpg'; ?>" alt="Profile" class="h-8 w-8 rounded-full">
                            <i class="fas fa-chevron-down text-xs text-gray-600"></i>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="p-4 border-b border-gray-200">
                                <p class="font-semibold text-gray-900">Signed in as</p>
                                <p class="text-sm text-gray-600"><?php echo $user['email']; ?></p>
                            </div>
                            <div class="py-2">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your profile</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your repositories</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your projects</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your stars</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your sponsors</a>
                            </div>
                            <div class="border-t border-gray-200 py-2">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">CodeHub APS Desktop</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Support</a>
                            </div>
                            <div class="border-t border-gray-200 py-2">
                                <a href="backend/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="flex h-screen pt-16">
        <!-- Sidebar -->
        <aside id="sidebar" class="w-64 bg-white border-r border-gray-200 overflow-y-auto scrollbar-thin hidden lg:block">
            <div class="p-4">
                <!-- User Info -->
                <div class="flex items-center space-x-3 mb-6 p-3 bg-gray-50 rounded-lg">
                    <img src="<?php echo !empty($user['profile_image']) ? $user['profile_image'] : 'https://picsum.photos/seed/user' . $user['id'] . '/40/40.jpg'; ?>" alt="User" class="h-10 w-10 rounded-full">
                    <div>
                        <p class="font-semibold text-gray-900"><?php echo $user['username']; ?></p>
                        <p class="text-xs text-gray-600">@<?php echo $user['username']; ?></p>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="grid grid-cols-3 gap-2 mb-6">
                    <div class="text-center p-2 bg-blue-50 rounded-lg">
                        <p class="text-lg font-bold text-blue-600"><?php echo $repos_count; ?></p>
                        <p class="text-xs text-gray-600">Repos</p>
                    </div>
                    <div class="text-center p-2 bg-green-50 rounded-lg">
                        <p class="text-lg font-bold text-green-600"><?php echo $stars_count; ?></p>
                        <p class="text-xs text-gray-600">Stars</p>
                    </div>
                    <div class="text-center p-2 bg-purple-50 rounded-lg">
                        <p class="text-lg font-bold text-purple-600"><?php echo $following_count; ?></p>
                        <p class="text-xs text-gray-600">Following</p>
                    </div>
                </div>
                
                <!-- Navigation -->
                <nav class="space-y-1">
                    <a href="#" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-home text-gray-500"></i>
                        <span>Home</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-inbox text-gray-500"></i>
                        <span>Inbox</span>
                        <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">3</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-exclamation-circle text-gray-500"></i>
                        <span>Issues</span>
                        <span class="ml-auto bg-green-500 text-white text-xs px-2 py-1 rounded-full"><?php echo count($issues); ?></span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-code-branch text-gray-500"></i>
                        <span>Pull Requests</span>
                        <span class="ml-auto bg-orange-500 text-white text-xs px-2 py-1 rounded-full"><?php echo count($pull_requests); ?></span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-comments text-gray-500"></i>
                        <span>Discussions</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-rocket text-gray-500"></i>
                        <span>Actions</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-project-diagram text-gray-500"></i>
                        <span>Projects</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-book text-gray-500"></i>
                        <span>Wiki</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-shield-alt text-gray-500"></i>
                        <span>Security</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-chart-line text-gray-500"></i>
                        <span>Insights</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center space-x-3 px-3 py-2 text-gray-700 rounded-lg">
                        <i class="fas fa-cog text-gray-500"></i>
                        <span>Settings</span>
                    </a>
                </nav>
                
                <!-- Recent Repositories -->
                <div class="mt-8">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Recent Repositories</h3>
                    <div class="space-y-1">
                        <?php foreach ($repositories as $repo): ?>
                        <a href="view_repo.php?id=<?php echo $repo['id']; ?>" class="flex items-center space-x-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">
                            <i class="fas fa-<?php echo $repo['visibility'] === 'private' ? 'lock' : 'book'; ?> text-gray-400"></i>
                            <span><?php echo $repo['name']; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <button onclick="window.location.href='create_repository.php'" class="w-full mt-3 px-3 py-2 text-sm text-blue-600 hover:text-blue-700 font-medium">
                        <i class="fas fa-plus mr-2"></i>New repository
                    </button>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto scrollbar-thin">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Welcome Section -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome back, <?php echo $user['username']; ?>! 👋</h1>
                    <p class="text-gray-600">Here's what's happening with your repositories today.</p>
                </div>
                
                <!-- Quick Actions -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <button onclick="window.location.href='create_repository.php'" class="bg-white p-4 rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-md transition-all">
                        <i class="fas fa-plus-circle text-blue-600 text-2xl mb-2"></i>
                        <p class="font-semibold text-gray-900">New Repository</p>
                        <p class="text-xs text-gray-600">Create a new repo</p>
                    </button>
                    <button class="bg-white p-4 rounded-lg border border-gray-200 hover:border-green-500 hover:shadow-md transition-all">
                        <i class="fas fa-users text-green-600 text-2xl mb-2"></i>
                        <p class="font-semibold text-gray-900">New Team</p>
                        <p class="text-xs text-gray-600">Invite collaborators</p>
                    </button>
                    <button class="bg-white p-4 rounded-lg border border-gray-200 hover:border-purple-500 hover:shadow-md transition-all">
                        <i class="fas fa-file-import text-purple-600 text-2xl mb-2"></i>
                        <p class="font-semibold text-gray-900">Import Repository</p>
                        <p class="text-xs text-gray-600">From Git/SVN</p>
                    </button>
                    <button class="bg-white p-4 rounded-lg border border-gray-200 hover:border-orange-500 hover:shadow-md transition-all">
                        <i class="fas fa-code text-orange-600 text-2xl mb-2"></i>
                        <p class="font-semibold text-gray-900">New Gist</p>
                        <p class="text-xs text-gray-600">Share code snippets</p>
                    </button>
                </div>
                
                <!-- Tabs -->
                <div class="border-b border-gray-200 mb-6">
                    <nav class="flex space-x-8">
                        <button class="tab-active py-2 px-1 text-sm font-medium">Overview</button>
                        <button class="py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">Repositories</button>
                        <button class="py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">Projects</button>
                        <button class="py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">Packages</button>
                        <button class="py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">Stars</button>
                    </nav>
                </div>
                
                <!-- Main Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Column -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Your Repositories -->
                        <section>
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-semibold text-gray-900">Your repositories</h2>
                                <button class="text-sm text-blue-600 hover:text-blue-700">View all</button>
                            </div>
                            
                            <div class="space-y-4">
                                <?php if (empty($repositories)): ?>
                                    <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                                        <i class="fas fa-folder-open text-4xl text-gray-300 mb-4"></i>
                                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No repositories yet</h3>
                                        <p class="text-gray-600 mb-4">Get started by creating your first repository.</p>
                                        <button onclick="window.location.href='create_repository.php'" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                            Create repository
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($repositories as $repo): ?>
                                    <!-- Repository Card -->
                                    <div class="repo-card bg-white rounded-lg border border-gray-200 p-4">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-2 mb-2">
                                                    <i class="fas fa-<?php echo $repo['visibility'] === 'private' ? 'lock' : 'book'; ?> text-gray-400"></i>
                                                    <a href="view_repo.php?id=<?php echo $repo['id']; ?>" class="font-semibold text-blue-600 hover:underline"><?php echo $repo['name']; ?></a>
                                                    <span class="px-2 py-1 text-xs bg-<?php echo $repo['visibility'] === 'private' ? 'gray' : 'green'; ?>-100 text-<?php echo $repo['visibility'] === 'private' ? 'gray' : 'green'; ?>-700 rounded-full"><?php echo ucfirst($repo['visibility']); ?></span>
                                                    <span class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded-full"><?php echo $repo['default_branch']; ?></span>
                                                </div>
                                                <p class="text-sm text-gray-600 mb-3"><?php echo !empty($repo['description']) ? $repo['description'] : 'No description provided'; ?></p>
                                                <div class="flex items-center space-x-4 text-sm text-gray-500">
                                                    <?php if (!empty($repo['language'])): ?>
                                                    <span class="flex items-center">
                                                        <i class="fas fa-circle text-<?php echo getLanguageColor($repo['language']); ?> text-xs mr-1 pulse-dot"></i>
                                                        <?php echo $repo['language']; ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <span class="flex items-center">
                                                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                                                        <?php echo $repo['stars']; ?>
                                                    </span>
                                                    <span class="flex items-center">
                                                        <i class="fas fa-code-branch mr-1"></i>
                                                        <?php echo $repo['forks']; ?>
                                                    </span>
                                                    <span class="flex items-center">
                                                        <i class="fas fa-eye mr-1"></i>
                                                        <?php echo $repo['views']; ?>
                                                    </span>
                                                    <span>Updated <?php echo getTimeAgo($repo['last_commit_date']); ?></span>
                                                </div>
                                            </div>
                                            <button class="text-gray-400 hover:text-gray-600">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- Contribution Graph -->
                        <section class="bg-white rounded-lg border border-gray-200 p-4">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Contribution Activity</h3>
                            <div class="grid grid-cols-7 gap-1 mb-3">
                                <!-- Generate contribution squares -->
                                <?php
                                for ($i = 0; $i < 35; $i++) {
                                    $level = rand(0, 4);
                                    $colors = ['bg-gray-100', 'bg-green-100', 'bg-green-300', 'bg-green-500', 'bg-green-700'];
                                    echo '<div class="w-3 h-3 ' . $colors[$level] . ' rounded-sm"></div>';
                                }
                                ?>
                            </div>
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span>Less</span>
                                <div class="flex space-x-1">
                                    <div class="w-3 h-3 bg-gray-100 rounded-sm"></div>
                                    <div class="w-3 h-3 bg-green-100 rounded-sm"></div>
                                    <div class="w-3 h-3 bg-green-300 rounded-sm"></div>
                                    <div class="w-3 h-3 bg-green-500 rounded-sm"></div>
                                    <div class="w-3 h-3 bg-green-700 rounded-sm"></div>
                                </div>
                                <span>More</span>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <p class="text-sm text-gray-600">
                                    <span class="font-semibold text-gray-900">127</span> contributions in the last year
                                </p>
                            </div>
                        </section>
                        
                        <!-- Pull Requests -->
                        <section class="bg-white rounded-lg border border-gray-200 p-4">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900">Pull Requests</h3>
                                <span class="px-2 py-1 text-xs bg-orange-100 text-orange-700 rounded-full"><?php echo count($pull_requests); ?></span>
                            </div>
                            
                            <div class="space-y-3">
                                <?php if (empty($pull_requests)): ?>
                                    <p class="text-sm text-gray-500 text-center">No pull requests</p>
                                <?php else: ?>
                                    <?php foreach ($pull_requests as $pr): ?>
                                    <div class="p-3 bg-<?php echo getStatusColor($pr['status']); ?>-50 border border-<?php echo getStatusColor($pr['status']); ?>-200 rounded-lg">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-xs font-semibold text-<?php echo getStatusColor($pr['status']); ?>-700"><?php echo getStatusText($pr['status']); ?></span>
                                            <span class="text-xs text-gray-500"><?php echo getTimeAgo($pr['updated_at']); ?></span>
                                        </div>
                                        <p class="text-sm font-medium text-gray-900 mb-1"><?php echo $pr['title']; ?></p>
                                        <p class="text-xs text-gray-600"><?php echo $pr['repo_name']; ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                        
                        <!-- Issues -->
                        <section class="bg-white rounded-lg border border-gray-200 p-4">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900">Issues</h3>
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full"><?php echo count($issues); ?></span>
                            </div>
                            
                            <div class="space-y-3">
                                <?php if (empty($issues)): ?>
                                    <p class="text-sm text-gray-500 text-center">No issues</p>
                                <?php else: ?>
                                    <?php foreach ($issues as $issue): ?>
                                    <div class="p-3 bg-<?php echo getTypeColor($issue['type']); ?>-50 border border-<?php echo getTypeColor($issue['type']); ?>-200 rounded-lg">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-xs font-semibold text-<?php echo getTypeColor($issue['type']); ?>-700"><?php echo strtoupper($issue['type']); ?></span>
                                            <span class="text-xs text-gray-500"><?php echo getTimeAgo($issue['updated_at']); ?></span>
                                        </div>
                                        <p class="text-sm font-medium text-gray-900 mb-1"><?php echo $issue['title']; ?></p>
                                        <p class="text-xs text-gray-600"><?php echo $issue['repo_name']; ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                        
                        <!-- Code Snippet -->
                        <section class="code-block">
                            <div class="code-block-inner">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs text-gray-400">Recent Commit</span>
                                    <button class="text-xs text-gray-400 hover:text-white">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                                <pre class="text-xs text-green-400 font-mono overflow-x-auto"><code>commit 7f3a2b1
Author: <?php echo $user['username']; ?> &lt;<?php echo $user['email']; ?>&gt;
Date:   Mon Dec 18 10:30:00 2023

    feat: Add new authentication system

    - Implement JWT tokens
    - Add refresh token logic
    - Update API endpoints</code></pre>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Floating Action Button -->
    <button onclick="window.location.href='create_repository.php'" class="fixed bottom-6 right-6 bg-blue-600 text-white p-4 rounded-full shadow-lg hover:bg-blue-700 transition-all transform hover:scale-110 z-30">
        <i class="fas fa-plus text-xl"></i>
    </button>
    
    <script>
        // Sidebar toggle for mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('hidden');
        });
        
        // Tab switching
        const tabs = document.querySelectorAll('nav button');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => t.classList.remove('tab-active'));
                this.classList.add('tab-active');
            });
        });
        
        // Search functionality
        const searchInput = document.querySelector('input[placeholder="Search CodeHub APS"]');
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                console.log('Searching for:', this.value);
            }
        });
        
        // Keyboard shortcut for search (Cmd/Ctrl + K)
        document.addEventListener('keydown', function(e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
            }
        });
        
        // Add hover effect to repository cards
        const repoCards = document.querySelectorAll('.repo-card');
        repoCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Copy code snippet functionality
        const copyButton = document.querySelector('.fa-copy').parentElement;
        copyButton.addEventListener('click', function() {
            const codeText = this.closest('.code-block-inner').querySelector('code').textContent;
            navigator.clipboard.writeText(codeText).then(() => {
                this.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(() => {
                    this.innerHTML = '<i class="fas fa-copy"></i>';
                }, 2000);
            });
        });
    </script>
</body>
</html>