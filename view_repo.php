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

// Get user information for header
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT id, username, email, profile_image FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$current_user = $user_result->fetch_assoc();

// Get repository files and folder structure
function getRepoStructure($repo_path) {
    $base_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repo_path;
    $structure = [];
    
    if (!is_dir($base_path)) {
        return $structure;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        $relative_path = str_replace($base_path . '/', '', $file->getPathname());
        $is_dir = $file->isDir();
        
        $structure[] = [
            'path' => $relative_path,
            'name' => $file->getFilename(),
            'is_dir' => $is_dir,
            'size' => $is_dir ? 0 : $file->getSize(),
            'modified' => date('Y-m-d H:i:s', $file->getMTime())
        ];
    }
    
    // Sort: directories first, then files
    usort($structure, function($a, $b) {
        if ($a['is_dir'] && !$b['is_dir']) return -1;
        if (!$a['is_dir'] && $b['is_dir']) return 1;
        return strcmp($a['name'], $b['name']);
    });
    
    return $structure;
}

// Get README content
function getReadmeContent($repo_path) {
    $base_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repo_path;
    $readme_files = ['README.md', 'readme.md', 'README.txt', 'readme.txt'];
    
    foreach ($readme_files as $file) {
        $readme_path = $base_path . '/' . $file;
        if (file_exists($readme_path)) {
            return [
                'filename' => $file,
                'content' => file_get_contents($readme_path),
                'path' => $readme_path
            ];
        }
    }
    return null;
}

// Get repository statistics
function getRepoStats($repo_path) {
    $base_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repo_path;
    $stats = [
        'files' => 0,
        'folders' => 0,
        'total_size' => 0,
        'languages' => []
    ];
    
    if (!is_dir($base_path)) {
        return $stats;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_path, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    $language_extensions = [
        'php' => 'PHP',
        'js' => 'JavaScript',
        'css' => 'CSS',
        'html' => 'HTML',
        'py' => 'Python',
        'java' => 'Java',
        'cpp' => 'C++',
        'c' => 'C',
        'ts' => 'TypeScript',
        'rb' => 'Ruby',
        'go' => 'Go',
        'rs' => 'Rust',
        'sql' => 'SQL'
    ];
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $stats['files']++;
            $stats['total_size'] += $file->getSize();
            
            $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (isset($language_extensions[$extension])) {
                $language = $language_extensions[$extension];
                if (!isset($stats['languages'][$language])) {
                    $stats['languages'][$language] = 0;
                }
                $stats['languages'][$language]++;
            }
        } else {
            $stats['folders']++;
        }
    }
    
    return $stats;
}

// Simple markdown parser
function parseMarkdown($content) {
    // Simple markdown parser for basic formatting
    $content = htmlspecialchars($content);
    
    // Headers
    $content = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $content);
    $content = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $content);
    $content = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $content);
    
    // Code blocks
    $content = preg_replace('/```(\w+)?\n(.*?)```/s', '<pre><code>$2</code></pre>', $content);
    
    // Inline code
    $content = preg_replace('/`([^`]+)`/', '<code>$1</code>', $content);
    
    // Links
    $content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $content);
    
    // Lists
    $content = preg_replace('/^- (.*$)/m', '<li>$1</li>', $content);
    $content = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $content);
    
    // Line breaks
    $content = nl2br($content);
    
    return $content;
}

// Local helper functions to avoid dependency issues
function localFormatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function localGetLanguageColorCode($language) {
    $colors = [
        'PHP' => '#787CB5',
        'JavaScript' => '#F7DF1E',
        'Python' => '#3776AB',
        'Java' => '#ED8B00',
        'HTML' => '#E34F26',
        'CSS' => '#1572B6',
        'TypeScript' => '#3178C6',
        'C++' => '#00599C',
        'C' => '#555555',
        'Ruby' => '#CC342D',
        'Go' => '#00ADD8',
        'Rust' => '#000000',
        'SQL' => '#336791'
    ];
    return $colors[$language] ?? '#6B7280';
}

function localGetTimeAgo($date) {
    $timestamp = strtotime($date);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
    return floor($diff / 31536000) . ' years ago';
}

// Get repository data
$repo_structure = getRepoStructure($repo['path']);
$readme = getReadmeContent($repo['path']);
$repo_stats = getRepoStats($repo['path']);

// Calculate language percentages
$total_files = array_sum($repo_stats['languages']);
$language_percentages = [];
foreach ($repo_stats['languages'] as $language => $count) {
    $language_percentages[$language] = round(($count / $total_files) * 100, 1);
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($repo['name']); ?> - CodeHub</title>
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
        
        .code-font {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        }
        
        .dropdown-content {
            display: none;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .branch-dropdown:hover .branch-dropdown-content {
            display: block;
        }
        
        .branch-dropdown-content {
            display: none;
        }
        
        .language-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .contributor-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid white;
            margin-left: -8px;
        }
        
        .contributor-avatar:first-child {
            margin-left: 0;
        }
        
        .markdown-body h1 {
            font-size: 2em;
            font-weight: 600;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #d0d7de;
        }
        
        .markdown-body h2 {
            font-size: 1.5em;
            font-weight: 600;
            margin-top: 24px;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #d0d7de;
        }
        
        .markdown-body p {
            margin-bottom: 16px;
        }
        
        .markdown-body code {
            background-color: #f6f8fa;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 85%;
        }
        
        .markdown-body pre {
            background-color: #f6f8fa;
            padding: 16px;
            border-radius: 6px;
            overflow-x: auto;
            margin-bottom: 16px;
        }
        
        .markdown-body ul {
            margin-bottom: 16px;
            padding-left: 32px;
        }
        
        .markdown-body li {
            margin-bottom: 4px;
        }
        
        .markdown-body a {
            color: #0969da;
            text-decoration: none;
        }
        
        .markdown-body a:hover {
            text-decoration: underline;
        }
        
        .file-icon {
            width: 16px;
            height: 16px;
            margin-right: 8px;
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
                                <a href="dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your repositories</a>
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

    <!-- Repository Header -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-900 hover-underline"><?php echo htmlspecialchars($repo['owner_username']); ?></a>
                    <span class="text-gray-400">/</span>
                    <h1 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($repo['name']); ?></h1>
                    <span class="px-2 py-1 text-xs font-semibold text-gray-600 bg-gray-100 rounded"><?php echo ucfirst($repo['visibility']); ?></span>
                </div>
                <div class="flex items-center space-x-2">
                    <button class="btn-secondary px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2">
                        <i class="fas fa-eye"></i>
                        <span>Watch</span>
                        <span class="text-gray-600"><?php echo $repo['views']; ?></span>
                    </button>
                    <button class="btn-secondary px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2">
                        <i class="fas fa-code-branch"></i>
                        <span>Fork</span>
                        <span class="text-gray-600"><?php echo $repo['forks']; ?></span>
                    </button>
                    <button class="btn-primary px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2">
                        <i class="fas fa-star"></i>
                        <span>Star</span>
                        <span class="text-gray-200"><?php echo $repo['stars']; ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-8">
                <a href="#" class="py-4 px-1 text-sm font-medium tab-active">Code</a>
                <a href="#" class="py-4 px-1 text-sm font-medium text-gray-600 hover:text-gray-900">Issues</a>
                <a href="#" class="py-4 px-1 text-sm font-medium text-gray-600 hover:text-gray-900">Pull requests</a>
                <a href="#" class="py-4 px-1 text-sm font-medium text-gray-600 hover:text-gray-900">Actions</a>
                <a href="#" class="py-4 px-1 text-sm font-medium text-gray-600 hover:text-gray-900">Projects</a>
                <a href="#" class="py-4 px-1 text-sm font-medium text-gray-600 hover:text-gray-900">Security</a>
                <a href="#" class="py-4 px-1 text-sm font-medium text-gray-600 hover:text-gray-900">Insights</a>
                <a href="#" class="py-4 px-1 text-sm font-medium text-gray-600 hover:text-gray-900">Settings</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex space-x-6">
            <!-- Left Column -->
            <div class="flex-1">
                <!-- File Browser Header -->
                <div class="bg-white rounded-lg border border-gray-200 mb-4">
                    <div class="flex items-center justify-between p-4">
                        <div class="flex items-center space-x-4">
                            <div class="branch-dropdown relative">
                                <button class="flex items-center space-x-2 px-3 py-1 bg-gray-100 rounded-lg hover:bg-gray-200">
                                    <i class="fas fa-code-branch text-gray-600"></i>
                                    <span class="text-sm font-medium"><?php echo htmlspecialchars($repo['default_branch']); ?></span>
                                    <i class="fas fa-chevron-down text-xs text-gray-600"></i>
                                </button>
                                <div class="branch-dropdown-content absolute left-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                    <div class="p-2">
                                        <input type="text" placeholder="Find or create a branch..." class="w-full px-3 py-2 text-sm border border-gray-200 rounded focus:outline-none focus:ring-2 focus:ring-orange-500">
                                        <div class="mt-2 py-2 border-t border-gray-200">
                                            <div class="px-3 py-2 hover:bg-gray-100 cursor-pointer">
                                                <div class="flex items-center space-x-2">
                                                    <i class="fas fa-code-branch text-gray-600"></i>
                                                    <span class="text-sm font-medium"><?php echo htmlspecialchars($repo['default_branch']); ?></span>
                                                </div>
                                            </div>
                                            <div class="px-3 py-2 hover:bg-gray-100 cursor-pointer">
                                                <div class="flex items-center space-x-2">
                                                    <i class="fas fa-tag text-gray-600"></i>
                                                    <span class="text-sm">Tags</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2 text-sm text-gray-600">
                                <span><?php echo htmlspecialchars($repo['owner_username']); ?></span>
                                <span>/</span>
                                <span><?php echo htmlspecialchars($repo['name']); ?></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button class="px-3 py-1 text-sm text-gray-600 hover:text-gray-900" id="copyLinkBtn">
                                <i class="fas fa-link"></i>
                            </button>
                            <button class="px-3 py-1 text-sm text-gray-600 hover:text-gray-900">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="btn-primary px-4 py-1 rounded text-sm font-medium">
                                <i class="fas fa-code-branch mr-2"></i>
                                Code
                            </button>
                        </div>
                    </div>

                    <!-- File Browser -->
                    <div class="border-t border-gray-200">
                        <?php if (!empty($repo_structure)): ?>
                            <div class="p-4">
                                <div class="space-y-1">
                                    <?php foreach ($repo_structure as $item): ?>
                                        <div class="file-row flex items-center space-x-3 px-3 py-2 rounded hover:bg-gray-50 cursor-pointer">
                                            <?php if ($item['is_dir']): ?>
                                                <i class="fas fa-folder text-blue-500 file-icon"></i>
                                            <?php else: ?>
                                                <?php
                                                $extension = pathinfo($item['name'], PATHINFO_EXTENSION);
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
                                            <?php endif; ?>
                                            <span class="text-sm text-gray-800"><?php echo htmlspecialchars($item['name']); ?></span>
                                            <?php if (!$item['is_dir']): ?>
                                                <span class="text-xs text-gray-500 ml-auto"><?php echo localFormatFileSize($item['size']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="p-8 text-center">
                                <i class="fas fa-folder-open text-4xl text-gray-300 mb-4"></i>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Empty repository</h3>
                                <p class="text-gray-600">This repository doesn't have any files yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- README Content -->
                <?php if ($readme): ?>
                <div class="bg-white rounded-lg border border-gray-200">
                    <div class="border-b border-gray-200 px-4 py-3">
                        <h2 class="text-lg font-semibold"><?php echo htmlspecialchars($readme['filename']); ?></h2>
                    </div>
                    <div class="p-6 markdown-body">
                        <?php echo parseMarkdown($readme['content']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <div class="w-80">
                <!-- About Section -->
                <div class="bg-white rounded-lg border border-gray-200 p-4 mb-4">
                    <h3 class="font-semibold mb-3">About</h3>
                    <p class="text-sm text-gray-600 mb-3"><?php echo !empty($repo['description']) ? htmlspecialchars($repo['description']) : 'No description provided'; ?></p>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-code-branch text-gray-400 w-4"></i>
                            <span class="text-gray-600"><?php echo htmlspecialchars($repo['default_branch']); ?></span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-clock text-gray-400 w-4"></i>
                            <span class="text-gray-600">Updated <?php echo localGetTimeAgo($repo['updated_at']); ?></span>
                        </div>
                        <?php if (!empty($repo_stats['languages'])): ?>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-code text-gray-400 w-4"></i>
                            <span class="text-gray-600">
                                <?php 
                                $primary_language = array_key_first($repo_stats['languages']);
                                echo $primary_language . ' ' . $language_percentages[$primary_language] . '%';
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Languages -->
                <?php if (!empty($language_percentages)): ?>
                <div class="bg-white rounded-lg border border-gray-200 p-4 mb-4">
                    <h3 class="font-semibold mb-3">Languages</h3>
                    <div class="space-y-2">
                        <?php foreach ($language_percentages as $language => $percentage): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="language-color" style="background-color: <?php echo localGetLanguageColorCode($language); ?>"></span>
                                <span class="text-sm"><?php echo $language; ?></span>
                            </div>
                            <span class="text-sm text-gray-600"><?php echo $percentage; ?>%</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Repository Stats -->
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <h3 class="font-semibold mb-3">Repository Stats</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Files:</span>
                            <span class="font-medium"><?php echo $repo_stats['files']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Folders:</span>
                            <span class="font-medium"><?php echo $repo_stats['folders']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Size:</span>
                            <span class="font-medium"><?php echo localFormatFileSize($repo_stats['total_size']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add interactive functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            const tabs = document.querySelectorAll('a[href="#"]');
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    if (this.parentElement.classList.contains('flex') && this.parentElement.classList.contains('space-x-8')) {
                        e.preventDefault();
                        tabs.forEach(t => t.classList.remove('tab-active'));
                        this.classList.add('tab-active');
                    }
                });
            });

            // Copy link functionality
            const copyLinkBtn = document.getElementById('copyLinkBtn');
            copyLinkBtn.addEventListener('click', function() {
                navigator.clipboard.writeText(window.location.href);
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i class="fas fa-check text-green-600"></i>';
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                }, 2000);
            });

            // File click handlers
            const fileRows = document.querySelectorAll('.file-row');
            fileRows.forEach(row => {
                row.addEventListener('click', function() {
                    const fileName = this.querySelector('span').textContent;
                    alert('Clicked on: ' + fileName);
                    // In a real implementation, this would navigate to the file view
                });
            });
        });
    </script>
</body>
</html>