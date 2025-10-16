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

// Get repository ID from URL
 $repo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get repository information
 $sql = "SELECT r.*, u.username as owner_username 
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

// Check if repository exists in filesystem
 $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $repo['path'];
if (!is_dir($fullPath)) {
    // Repository not found in filesystem
    header('Location: dashboard.php');
    exit;
}

// Get current path from URL (default: root)
 $currentPath = isset($_GET['path']) ? $_GET['path'] : '';

// Get repository content
 $content = getRepositoryContent($repo['path'], $currentPath);

// Find README file
 $readmeFile = findReadmeFile($repo['path'], $currentPath);
 $readmeContent = '';
if ($readmeFile) {
    $readmeContent = getFileContent($repo['path'], $readmeFile);
}

// Get repository stats
 $stats = [
    'branches' => 0,
    'tags' => 0,
    'commits' => 0,
    'contributors' => 0
];

// Try to get stats from filesystem (basic implementation)
if (is_dir($fullPath . '/.git')) {
    // This is a Git repository
    // For a real implementation, you would use git commands or a library
    // For now, we'll use placeholder values
    $stats['branches'] = 3;
    $stats['tags'] = 5;
    $stats['commits'] = 42;
    $stats['contributors'] = 2;
}

// Close connection
 $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $repo['name']; ?> - CodeHub APS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .file-row:hover {
            background-color: #f6f8fa;
        }
        
        .markdown-body {
            box-sizing: border-box;
            min-width: 200px;
            max-width: 980px;
            margin: 0 auto;
            padding: 45px;
        }
        
        .markdown-body h1 {
            padding-bottom: 0.3em;
            font-size: 2em;
            border-bottom: 1px solid #eaecef;
        }
        
        .markdown-body h2 {
            padding-bottom: 0.3em;
            font-size: 1.5em;
            border-bottom: 1px solid #eaecef;
        }
        
        .markdown-body h3 {
            font-size: 1.25em;
        }
        
        .markdown-body h4 {
            font-size: 1em;
        }
        
        .markdown-body h5 {
            font-size: 0.875em;
        }
        
        .markdown-body h6 {
            font-size: 0.85em;
            color: #6a737d;
        }
        
        .markdown-body p {
            margin-top: 0;
            margin-bottom: 16px;
        }
        
        .markdown-body ul, .markdown-body ol {
            padding-left: 2em;
            margin-bottom: 16px;
        }
        
        .markdown-body li {
            word-wrap: break-all;
        }
        
        .markdown-body li > p {
            margin-top: 16px;
        }
        
        .markdown-body li + li {
            margin-top: 0.25em;
        }
        
        .markdown-body code {
            padding: 0.2em 0.4em;
            margin: 0;
            font-size: 85%;
            background-color: rgba(27, 31, 35, 0.05);
            border-radius: 3px;
        }
        
        .markdown-body pre {
            word-wrap: normal;
            padding: 16px;
            overflow: auto;
            font-size: 85%;
            line-height: 1.45;
            background-color: #f6f8fa;
            border-radius: 3px;
        }
        
        .markdown-body pre code {
            display: inline;
            max-width: auto;
            padding: 0;
            margin: 0;
            overflow: visible;
            line-height: inherit;
            word-wrap: normal;
            background-color: transparent;
            border: 0;
        }
        
        .markdown-body blockquote {
            padding: 0 1em;
            color: #6a737d;
            border-left: 0.25em solid #dfe2e5;
            margin-bottom: 16px;
        }
        
        .markdown-body table {
            border-spacing: 0;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        
        .markdown-body table th {
            font-weight: 600;
        }
        
        .markdown-body table th, .markdown-body table td {
            padding: 6px 13px;
            border: 1px solid #dfe2e5;
        }
        
        .markdown-body table tr {
            background-color: #fff;
            border-top: 1px solid #c6cbd1;
        }
        
        .markdown-body table tr:nth-child(2n) {
            background-color: #f6f8fa;
        }
        
        .markdown-body img {
            max-width: 100%;
            box-sizing: content-box;
        }
        
        .markdown-body hr {
            height: 0.25em;
            padding: 0;
            margin: 24px 0;
            background-color: #e1e4e8;
            border: 0;
        }
        
        .markdown-body a {
            color: #0366d6;
            text-decoration: none;
        }
        
        .markdown-body a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb-separator::before {
            content: "/";
            margin: 0 0.25rem;
            color: #586069;
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
    </style>
</head>
<body class="bg-gray-50">
    <!-- Top Navigation -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Left Section -->
                <div class="flex items-center space-x-4">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <svg class="h-8 w-8 text-gray-900" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.30.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                        </svg>
                    </div>
                    
                    <!-- Repository Breadcrumb -->
                    <div class="flex items-center">
                        <a href="dashboard.php" class="text-gray-600 hover:text-gray-900">
                            <i class="fas fa-home"></i>
                        </a>
                        <span class="breadcrumb-separator"></span>
                        <a href="#" class="text-gray-600 hover:text-gray-900"><?php echo $repo['owner_username']; ?></a>
                        <span class="breadcrumb-separator"></span>
                        <span class="font-semibold text-gray-900"><?php echo $repo['name']; ?></span>
                    </div>
                </div>
                
                <!-- Right Section -->
                <div class="flex items-center space-x-4">
                    <!-- Search -->
                    <div class="relative">
                        <input type="text" 
                               placeholder="Go to file" 
                               class="w-64 px-4 py-2 pl-10 pr-4 text-sm bg-gray-100 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:bg-white transition-all">
                        <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    
                    <!-- Notifications -->
                    <button class="relative text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bell text-lg"></i>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">3</span>
                    </button>
                    
                    <!-- Profile -->
                    <div class="relative group">
                        <button class="flex items-center space-x-2">
                            <img src="https://picsum.photos/seed/user<?php echo $_SESSION['user_id']; ?>/32/32.jpg" alt="Profile" class="h-8 w-8 rounded-full">
                            <i class="fas fa-chevron-down text-xs text-gray-600"></i>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="p-4 border-b border-gray-200">
                                <p class="font-semibold text-gray-900">Signed in as</p>
                                <p class="text-sm text-gray-600"><?php echo $_SESSION['email']; ?></p>
                            </div>
                            <div class="py-2">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your profile</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your repositories</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your projects</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your stars</a>
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
    
    <!-- Repository Header -->
    <div class="bg-gray-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div class="mb-4 md:mb-0">
                    <div class="flex items-center space-x-2 mb-2">
                        <i class="fas fa-<?php echo $repo['visibility'] === 'private' ? 'lock' : 'book'; ?>"></i>
                        <h1 class="text-2xl font-bold"><?php echo $repo['name']; ?></h1>
                        <span class="px-2 py-1 text-xs bg-<?php echo $repo['visibility'] === 'private' ? 'gray-700' : 'green-600'; ?> rounded-full"><?php echo ucfirst($repo['visibility']); ?></span>
                    </div>
                    <p class="text-gray-300"><?php echo $repo['description']; ?></p>
                </div>
                
                <div class="flex items-center space-x-2">
                    <button class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium">
                        <i class="fas fa-code-branch mr-2"></i>
                        <span class="hidden sm:inline">Code</span>
                    </button>
                    <button class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span class="hidden sm:inline">Issues</span>
                    </button>
                    <button class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium">
                        <i class="fas fa-code-branch mr-2"></i>
                        <span class="hidden sm:inline">Pull requests</span>
                    </button>
                    <button class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium">
                        <i class="fas fa-play mr-2"></i>
                        <span class="hidden sm:inline">Actions</span>
                    </button>
                    <button class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium">
                        <i class="fas fa-project-diagram mr-2"></i>
                        <span class="hidden sm:inline">Projects</span>
                    </button>
                    <button class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium">
                        <i class="fas fa-book mr-2"></i>
                        <span class="hidden sm:inline">Wiki</span>
                    </button>
                    <button class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium">
                        <i class="fas fa-shield-alt mr-2"></i>
                        <span class="hidden sm:inline">Security</span>
                    </button>
                    <button class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium">
                        <i class="fas fa-chart-line mr-2"></i>
                        <span class="hidden sm:inline">Insights</span>
                    </button>
                    <button class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium">
                        <i class="fas fa-cog mr-2"></i>
                        <span class="hidden sm:inline">Settings</span>
                    </button>
                </div>
            </div>
            
            <!-- Repository Stats -->
            <div class="flex items-center space-x-6 text-sm text-gray-300 mt-4">
                <span class="flex items-center">
                    <i class="fas fa-circle text-<?php echo getLanguageColor($repo['language']); ?> text-xs mr-1"></i>
                    <?php echo $repo['language']; ?>
                </span>
                <span class="flex items-center">
                    <i class="fas fa-star mr-1"></i>
                    <?php echo $repo['stars']; ?>
                </span>
                <span class="flex items-center">
                    <i class="fas fa-code-branch mr-1"></i>
                    <?php echo $repo['forks']; ?>
                </span>
                <span class="flex items-center">
                    <i class="fas fa-eye mr-1"></i>
                    <?php echo $repo['views']; ?> watching
                </span>
                <span class="flex items-center">
                    <i class="fas fa-users mr-1"></i>
                    <?php echo $stats['contributors']; ?> contributors
                </span>
            </div>
        </div>
    </div>
    
    <!-- Repository Tabs -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-8">
                <a href="#" class="py-3 px-1 border-b-2 border-blue-500 text-sm font-medium text-blue-600">
                    <i class="fas fa-code-branch mr-2"></i>
                    <code><?php echo $repo['default_branch']; ?></code>
                </a>
                <a href="#" class="py-3 px-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    <i class="fas fa-code-branch mr-2"></i>
                    <?php echo $stats['branches']; ?> branches
                </a>
                <a href="#" class="py-3 px-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    <i class="fas fa-tags mr-2"></i>
                    <?php echo $stats['tags']; ?> tags
                </a>
            </div>
        </div>
    </div>
    
    <!-- Repository Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Main Content -->
            <div class="flex-1">
                <!-- Path Breadcrumb -->
                <div class="flex items-center space-x-2 mb-4 text-sm">
                    <a href="repository.php?id=<?php echo $repo_id; ?>" class="text-blue-600 hover:underline"><?php echo $repo['name']; ?></a>
                    <?php
                    if (!empty($currentPath)) {
                        $pathParts = explode('/', $currentPath);
                        $pathSoFar = '';
                        foreach ($pathParts as $i => $part) {
                            $pathSoFar .= ($i > 0 ? '/' : '') . $part;
                            echo '<span class="text-gray-500">/</span>';
                            echo '<a href="repository.php?id=' . $repo_id . '&path=' . urlencode($pathSoFar) . '" class="text-blue-600 hover:underline">' . $part . '</a>';
                        }
                    }
                    ?>
                </div>
                
                <!-- README Content -->
                <?php if (!empty($readmeContent)): ?>
                <div class="bg-white rounded-lg border border-gray-200 p-6 mb-6">
                    <div class="markdown-body">
                        <?php echo parseMarkdown($readmeContent); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- File List -->
                <div class="bg-white rounded-lg border border-gray-200">
                    <div class="p-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-folder text-gray-500"></i>
                                <span class="font-medium text-gray-900">
                                    <?php echo empty($currentPath) ? $repo['name'] : basename($currentPath); ?>
                                </span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md">
                                    <i class="fas fa-download mr-1"></i>
                                    Code
                                </button>
                                <button class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md">
                                    <i class="fas fa-plus mr-1"></i>
                                    Add file
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left p-4 text-sm font-medium text-gray-700">Name</th>
                                    <th class="text-left p-4 text-sm font-medium text-gray-700">Last commit</th>
                                    <th class="text-left p-4 text-sm font-medium text-gray-700">Commit time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Parent Directory Link -->
                                <?php if (!empty($currentPath)): ?>
                                <tr class="file-row border-b border-gray-100">
                                    <td class="p-4">
                                        <a href="repository.php?id=<?php echo $repo_id; ?>&path=<?php echo urlencode(dirname($currentPath)); ?>" class="flex items-center space-x-2 text-blue-600 hover:underline">
                                            <i class="fas fa-level-up-alt"></i>
                                            <span>..</span>
                                        </a>
                                    </td>
                                    <td class="p-4 text-sm text-gray-500"></td>
                                    <td class="p-4 text-sm text-gray-500"></td>
                                </tr>
                                <?php endif; ?>
                                
                                <!-- Files and Directories -->
                                <?php foreach ($content as $item): ?>
                                <tr class="file-row border-b border-gray-100">
                                    <td class="p-4">
                                        <a href="<?php echo $item['type'] === 'directory' ? 'repository.php?id=' . $repo_id . '&path=' . urlencode($item['path']) : '#'; ?>" class="flex items-center space-x-2 text-blue-600 hover:underline">
                                            <i class="<?php echo $item['type'] === 'directory' ? 'fas fa-folder text-yellow-500' : getFileIcon($item['extension']); ?>"></i>
                                            <span><?php echo $item['name']; ?></span>
                                        </a>
                                    </td>
                                    <td class="p-4 text-sm text-gray-500">
                                        <?php echo $item['type'] === 'directory' ? '—' : 'Initial commit'; ?>
                                    </td>
                                    <td class="p-4 text-sm text-gray-500">
                                        <?php echo getTimeAgo(date('Y-m-d H:i:s', $item['modified'])); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="lg:w-80">
                <!-- About Section -->
                <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">About</h3>
                    <p class="text-sm text-gray-600 mb-4"><?php echo $repo['description']; ?></p>
                    
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center">
                            <i class="fas fa-circle text-<?php echo getLanguageColor($repo['language']); ?> text-xs mr-2"></i>
                            <span class="text-gray-700"><?php echo $repo['language']; ?></span>
                        </div>
                        
                        <div class="flex items-center">
                            <i class="fas fa-code-branch text-gray-500 text-xs mr-2"></i>
                            <span class="text-gray-700"><?php echo $stats['branches']; ?> branches</span>
                        </div>
                        
                        <div class="flex items-center">
                            <i class="fas fa-tags text-gray-500 text-xs mr-2"></i>
                            <span class="text-gray-700"><?php echo $stats['tags']; ?> tags</span>
                        </div>
                        
                        <div class="flex items-center">
                            <i class="fas fa-shield-alt text-gray-500 text-xs mr-2"></i>
                            <span class="text-gray-700"><?php echo ucfirst($repo['visibility']); ?></span>
                        </div>
                        
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-gray-500 text-xs mr-2"></i>
                            <span class="text-gray-700">0 issues need help</span>
                        </div>
                        
                        <div class="flex items-center">
                            <i class="fas fa-code-branch text-gray-500 text-xs mr-2"></i>
                            <span class="text-gray-700"><?php echo $repo['forks']; ?> forks</span>
                        </div>
                        
                        <div class="flex items-center">
                            <i class="fas fa-star text-gray-500 text-xs mr-2"></i>
                            <span class="text-gray-700"><?php echo $repo['stars']; ?> stars</span>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="#" class="text-sm text-blue-600 hover:underline">Read more</a>
                    </div>
                </div>
                
                <!-- Releases Section -->
                <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-semibold text-gray-900">Releases</h3>
                        <a href="#" class="text-sm text-blue-600 hover:underline">View all</a>
                    </div>
                    
                    <div class="text-center py-8">
                        <i class="fas fa-tag text-gray-300 text-4xl mb-3"></i>
                        <p class="text-sm text-gray-500">There aren't any releases here</p>
                        <button class="mt-3 px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                            Create a release
                        </button>
                    </div>
                </div>
                
                <!-- Contributors Section -->
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-semibold text-gray-900">Contributors</h3>
                        <a href="#" class="text-sm text-blue-600 hover:underline">View all</a>
                    </div>
                    
                    <div class="flex -space-x-2">
                        <img src="https://picsum.photos/seed/user1/32/32.jpg" alt="Contributor" class="h-8 w-8 rounded-full border-2 border-white">
                        <img src="https://picsum.photos/seed/user2/32/32.jpg" alt="Contributor" class="h-8 w-8 rounded-full border-2 border-white">
                    </div>
                    
                    <p class="text-sm text-gray-500 mt-3"><?php echo $stats['contributors']; ?> contributors</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-typescript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <script>
        // Highlight code blocks
        document.addEventListener('DOMContentLoaded', function() {
            Prism.highlightAll();
        });
        
        // Copy file path functionality
        document.querySelectorAll('.file-row').forEach(row => {
            row.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                const filePath = this.querySelector('a').getAttribute('href');
                if (filePath && filePath !== '#') {
                    navigator.clipboard.writeText(filePath).then(() => {
                        // Show toast notification
                        const toast = document.createElement('div');
                        toast.className = 'fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-2 rounded-md shadow-lg z-50';
                        toast.textContent = 'Path copied to clipboard';
                        document.body.appendChild(toast);
                        
                        setTimeout(() => {
                            document.body.removeChild(toast);
                        }, 3000);
                    });
                }
            });
        });
    </script>
</body>
</html>