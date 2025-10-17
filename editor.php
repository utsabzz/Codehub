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

// Get user information for header
 $user_id = $_SESSION['user_id'];
 $user_sql = "SELECT id, username, email, profile_image FROM users WHERE id = ?";
 $user_stmt = $conn->prepare($user_sql);
 $user_stmt->bind_param("i", $user_id);
 $user_stmt->execute();
 $user_result = $user_stmt->get_result();
 $current_user = $user_result->fetch_assoc();

// Format file size function
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Get repository files WITHOUT content initially
function getRepoFilesFlat($repo_path) {
    // Remove 'projects/' prefix if it exists
    $clean_path = preg_replace('#^projects/#', '', $repo_path);
    $base_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $clean_path;
    
    // Debug: Log the actual path being used
    error_log("Looking for files in: " . $base_path);
    error_log("Repository path from DB: " . $repo_path);
    error_log("Cleaned path: " . $clean_path);
    
    if (!is_dir($base_path)) {
        error_log("Directory not found: " . $base_path);
        // List what's actually in the parent directory
        $parent_dir = dirname($base_path);
        if (is_dir($parent_dir)) {
            $contents = scandir($parent_dir);
            error_log("Contents of parent directory (" . $parent_dir . "): " . print_r($contents, true));
        }
        return [];
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $files = [];
    
    foreach ($iterator as $file) {
        $relative_path = str_replace($base_path . '/', '', $file->getPathname());
        
        if (!$file->isDir()) {
            $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            $is_text_file = in_array($extension, ['txt', 'html', 'css', 'js', 'php', 'py', 'json', 'md', 'xml', 'yml', 'yaml', 'sql', 'sh', 'ts', 'jsx', 'tsx', 'vue', 'scss', 'less', 'sass']);
            
            $files[] = [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'relative_path' => $relative_path,
                'content' => $is_text_file ? file_get_contents($file->getPathname()) : null,
                'size' => $file->getSize(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                'is_dir' => false,
                'extension' => $extension,
                'is_text_file' => $is_text_file
            ];
        }
    }
    
    // Sort files by name
    usort($files, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    error_log("Found " . count($files) . " files in repository");
    return $files;
}

// Handle file save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_file') {
        $file_path = $_POST['file_path'];
        $content = $_POST['content'];
        
        if (file_exists($file_path)) {
            if (is_writable($file_path)) {
                file_put_contents($file_path, $content);
                echo json_encode(['success' => true, 'message' => 'File saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'File is not writable']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found']);
        }
        exit;
    }
    
    // Handle file content loading
    if ($_POST['action'] === 'load_file') {
        $file_path = $_POST['file_path'];
        
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            echo json_encode(['success' => true, 'content' => $content]);
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found']);
        }
        exit;
    }
    
    // Handle AI code suggestion
    if ($_POST['action'] === 'ai_suggest') {
        $code = $_POST['code'];
        $language = $_POST['language'];
        $cursor_position = $_POST['cursor_position'];
        
        // Extract context around cursor
        $lines = explode("\n", $code);
        $cursor_line = 0;
        $cursor_char = 0;
        
        // Parse cursor position
        if (preg_match('/(\d+):(\d+)/', $cursor_position, $matches)) {
            $cursor_line = min(intval($matches[1]) - 1, count($lines) - 1);
            $cursor_char = min(intval($matches[2]), strlen($lines[$cursor_line] ?? ''));
        }
        
        // Get context lines (5 before and after cursor)
        $start_line = max(0, $cursor_line - 5);
        $end_line = min(count($lines) - 1, $cursor_line + 5);
        $context_lines = [];
        
        for ($i = $start_line; $i <= $end_line; $i++) {
            $prefix = $i === $cursor_line ? '> ' : '  ';
            $context_lines[] = $prefix . $lines[$i];
        }
        
        $context = implode("\n", $context_lines);
        
        // Prepare prompt for AI
        $prompt = "You are an expert programmer assistant. Based on the following code context, suggest appropriate code completion. The cursor is at the line marked with >.\n\nLanguage: $language\n\nCode:\n$context\n\nSuggestion:";
        
        // Call OpenRouter API
        $api_key = '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';
        $url = 'https://openrouter.ai/api/v1/chat/completions';
        
        $data = [
            'model' => 'meta-llama/llama-3.2-8b-instruct:free',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert programmer assistant. Provide concise, helpful code suggestions based on the context.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 500,
            'temperature' => 0.2
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
            'HTTP-Referer: https://localhost',
            'X-Title: CodeHub Editor'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("CURL Error: " . $error);
            echo json_encode(['success' => false, 'message' => 'Network error: ' . $error]);
        } elseif ($http_code === 200) {
            $result = json_decode($response, true);
            $suggestion = $result['choices'][0]['message']['content'] ?? '';
            echo json_encode(['success' => true, 'suggestion' => $suggestion]);
        } else {
            error_log("API Error - HTTP Code: $http_code, Response: $response");
            echo json_encode(['success' => false, 'message' => "API error (HTTP $http_code)"]);
        }
        exit;
    }
    
    // Handle AI code generation
    if ($_POST['action'] === 'ai_generate') {
        $prompt = $_POST['prompt'];
        $language = $_POST['language'];
        
        // Prepare prompt for AI
        $full_prompt = "Generate $language code based on the following request:\n\n$prompt\n\nCode:";
        
        // Call OpenRouter API
        $api_key = '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';
        $url = 'https://openrouter.ai/api/v1/chat/completions';
        
        $data = [
            'model' => 'meta-llama/llama-3.2-8b-instruct:free',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert programmer. Generate clean, well-commented code based on the user\'s request.'
                ],
                [
                    'role' => 'user',
                    'content' => $full_prompt
                ]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.3
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
            'HTTP-Referer: https://localhost',
            'X-Title: CodeHub Editor'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("CURL Error: " . $error);
            echo json_encode(['success' => false, 'message' => 'Network error: ' . $error]);
        } elseif ($http_code === 200) {
            $result = json_decode($response, true);
            $code = $result['choices'][0]['message']['content'] ?? '';
            echo json_encode(['success' => true, 'code' => $code]);
        } else {
            error_log("API Error - HTTP Code: $http_code, Response: $response");
            echo json_encode(['success' => false, 'message' => "API error (HTTP $http_code)"]);
        }
        exit;
    }
}

// DEBUG: Check the actual file system structure
 $repo_path_from_db = $repo['path'];
 $clean_path = preg_replace('#^projects/#', '', $repo_path_from_db);
 $full_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $clean_path;

error_log("=== DEBUG FILE PATHS ===");
error_log("Database path: " . $repo_path_from_db);
error_log("Cleaned path: " . $clean_path);
error_log("Full constructed path: " . $full_path);
error_log("Directory exists: " . (is_dir($full_path) ? 'YES' : 'NO'));
error_log("Directory readable: " . (is_readable($full_path) ? 'YES' : 'NO'));

if (is_dir($full_path)) {
    $contents = scandir($full_path);
    error_log("Directory contents: " . print_r($contents, true));
}
error_log("=== END DEBUG ===");

// Get repository files
 $repo_files = getRepoFilesFlat($repo['path']);

// Ensure files array is properly structured for JavaScript
 $files_js = [];
foreach ($repo_files as $file) {
    $files_js[] = [
        'name' => $file['name'],
        'path' => $file['path'],
        'relative_path' => $file['relative_path'],
        'content' => $file['content'],
        'size' => $file['size'],
        'modified' => $file['modified'],
        'is_dir' => $file['is_dir'],
        'extension' => $file['extension'],
        'is_text_file' => $file['is_text_file']
    ];
}

// Debug output
error_log("Repository path: " . $repo['path']);
error_log("Base path: " . $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . preg_replace('#^projects/#', '', $repo['path']));
error_log("Files found: " . count($repo_files));

// Close connection
 $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeHub Editor - <?php echo htmlspecialchars($repo['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Monaco Editor -->
    <link rel="stylesheet" data-name="vs/editor/editor.main" href="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs/editor/editor.main.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .editor-container {
            height: calc(100vh - 64px);
        }
        
        .file-tree {
            height: calc(100% - 40px);
            overflow-y: auto;
        }
        
        .file-item {
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #d1d5db;
            font-size: 14px;
            border-radius: 4px;
            margin: 2px 8px;
        }
        
        .file-item:hover {
            background: rgba(55, 65, 81, 0.5);
            color: #fff;
        }
        
        .file-item.active {
            background: rgba(59, 130, 246, 0.2);
            color: #fff;
            border-left: 3px solid #3b82f6;
        }
        
        .tab {
            padding: 10px 16px;
            background: #1f2937;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #9ca3af;
            font-size: 14px;
            transition: all 0.2s;
            border-right: 1px solid #374151;
            position: relative;
        }
        
        .tab:hover {
            background: #374151;
        }
        
        .tab.active {
            background: #111827;
            color: #fff;
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: #3b82f6;
        }
        
        .status-bar {
            height: 24px;
            background: #111827;
            border-top: 1px solid #374151;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            font-size: 12px;
            color: #9ca3af;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #1f2937;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #4b5563;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateX(0);
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        
        .notification.hide {
            transform: translateX(120%);
            opacity: 0;
        }
        
        .notification.success {
            background-color: #10b981;
        }
        
        .notification.error {
            background-color: #ef4444;
        }
        
        .notification.info {
            background-color: #3b82f6;
        }
        
        .image-viewer {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: #1f2937;
        }
        
        .image-viewer img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        
        .binary-file-message {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #9ca3af;
            text-align: center;
        }
        
        .editor-tabs {
            display: flex;
            background: #1f2937;
            overflow-x: auto;
            scrollbar-width: thin;
        }
        
        .editor-tabs::-webkit-scrollbar {
            height: 4px;
        }
        
        .editor-content {
            height: calc(100% - 40px);
            position: relative;
            display: flex;
            width: 100%;
        }
        
        .editor-pane {
            width: 100%;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        
        .preview-pane {
            width: 0;
            background: white;
            border-left: 1px solid #374151;
            transition: width 0.3s ease;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        
        .preview-pane.open {
            width: 50%;
        }
        
        .preview-header {
            background: #f3f4f6;
            padding: 8px 16px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #9ca3af;
        }
        
        .spinner {
            border: 3px solid #374151;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin-bottom: 16px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Resizer styles - FIXED */
        .resizer {
            width: 5px;
            background: #374151;
            cursor: col-resize;
            position: relative;
            z-index: 10;
            flex-shrink: 0;
            user-select: none;
        }
        
        .resizer:hover {
            background: #4b5563;
        }
        
        .resizer::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 40px;
            background: linear-gradient(to right, 
                transparent 0%, 
                transparent 40%, 
                #6b7280 40%, 
                #6b7280 45%, 
                transparent 45%, 
                transparent 55%, 
                #6b7280 55%, 
                #6b7280 60%, 
                transparent 60%, 
                transparent 100%);
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .resizer:hover::after {
            opacity: 1;
        }
        
        /* AI Assistant Panel */
        .ai-panel {
            position: absolute;
            bottom: 30px;
            right: 20px;
            width: 350px;
            max-height: 400px;
            background: #1f2937;
            border: 1px solid #374151;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            z-index: 100;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }
        
        .ai-panel.hidden {
            transform: translateY(calc(100% + 40px));
        }
        
        .ai-panel-header {
            padding: 12px 16px;
            background: #374151;
            border-bottom: 1px solid #4b5563;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .ai-panel-content {
            padding: 16px;
            overflow-y: auto;
            flex: 1;
        }
        
        .ai-panel-footer {
            padding: 12px 16px;
            background: #374151;
            border-top: 1px solid #4b5563;
            border-radius: 0 0 8px 8px;
        }
        
        .ai-suggestion {
            background: #2d3748;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
            position: relative;
        }
        
        .ai-suggestion pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 13px;
            color: #e2e8f0;
            margin: 0;
            overflow-x: auto;
        }
        
        .ai-suggestion-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        
        .ai-suggestion-actions button {
            padding: 4px 8px;
            font-size: 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .ai-suggestion-actions .accept {
            background: #10b981;
            color: white;
        }
        
        .ai-suggestion-actions .accept:hover {
            background: #059669;
        }
        
        .ai-suggestion-actions .reject {
            background: #ef4444;
            color: white;
        }
        
        .ai-suggestion-actions .reject:hover {
            background: #dc2626;
        }
        
        .ai-input-group {
            display: flex;
            gap: 8px;
        }
        
        .ai-input {
            flex: 1;
            padding: 8px 12px;
            background: #2d3748;
            border: 1px solid #4b5563;
            border-radius: 4px;
            color: #e2e8f0;
            font-size: 14px;
        }
        
        .ai-input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        
        .ai-button {
            padding: 8px 12px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .ai-button:hover {
            background: #2563eb;
        }
        
        .ai-button:disabled {
            background: #4b5563;
            cursor: not-allowed;
        }
        
        .ai-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .ai-loading-spinner {
            width: 24px;
            height: 24px;
            border: 2px solid #4b5563;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 overflow-hidden">
    <!-- Header -->
    <header class="bg-gray-800 border-b border-gray-700 h-16 flex items-center justify-between px-6">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-code text-blue-500 text-xl"></i>
                <span class="font-semibold text-lg">CodeHub Editor</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <span class="text-gray-400">Project:</span>
                <span class="text-white font-medium"><?php echo htmlspecialchars($repo['name']); ?></span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="runCode()" class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-md text-sm font-medium flex items-center gap-2 transition">
                <i class="fas fa-play"></i>
                Run
            </button>
            <button onclick="togglePreview()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-md text-sm font-medium flex items-center gap-2 transition">
                <i class="fas fa-eye"></i>
                Preview
            </button>
            <button onclick="toggleAIPanel()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-md text-sm font-medium flex items-center gap-2 transition">
                <i class="fas fa-robot"></i>
                CodeHub Pilot
            </button>
            <button onclick="saveFile()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-md text-sm font-medium flex items-center gap-2 transition">
                <i class="fas fa-save"></i>
                Comit Changes
            </button>
            <a href="view_repo.php?id=<?php echo $repo_id; ?>" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 rounded-md text-sm font-medium flex items-center gap-2 transition">
                <i class="fas fa-arrow-left"></i>
                Back to Repo
            </a>
        </div>
    </header>

    <div class="editor-container flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-gray-800 border-r border-gray-700 flex flex-col">
            <div class="p-4 border-b border-gray-700">
                <div class="text-xs text-gray-500 uppercase tracking-wider mb-2">Files</div>
                <div class="relative">
                    <input type="text" id="fileSearch" placeholder="Search files..." class="w-full px-3 py-2 bg-gray-700 text-white rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <i class="fas fa-search absolute right-3 top-2.5 text-gray-400"></i>
                </div>
            </div>
            <div class="file-tree" id="fileTree">
                <?php if (!empty($repo_files)): ?>
                    <?php foreach ($repo_files as $file): ?>
                        <?php
                        $extension = strtolower($file['extension']);
                        $file_icons = [
                            'php' => 'fab fa-php text-purple-500',
                            'js' => 'fab fa-js-square text-yellow-500',
                            'css' => 'fab fa-css3-alt text-blue-500',
                            'html' => 'fab fa-html5 text-orange-500',
                            'py' => 'fab fa-python text-blue-600',
                            'json' => 'fas fa-code text-yellow-600',
                            'md' => 'fab fa-markdown text-blue-400',
                            'txt' => 'fas fa-file-alt text-gray-500',
                            'jpg' => 'fas fa-file-image text-green-500',
                            'jpeg' => 'fas fa-file-image text-green-500',
                            'png' => 'fas fa-file-image text-green-500',
                            'gif' => 'fas fa-file-image text-green-500',
                            'svg' => 'fas fa-file-image text-green-500',
                            'pdf' => 'fas fa-file-pdf text-red-500',
                            'gitignore' => 'fas fa-eye-slash text-gray-500',
                            'license' => 'fas fa-balance-scale text-gray-500',
                            'sql' => 'fas fa-database text-orange-600',
                            'xml' => 'fas fa-code text-blue-400',
                            'yml' => 'fas fa-cog text-gray-500',
                            'yaml' => 'fas fa-cog text-gray-500',
                            'sh' => 'fas fa-terminal text-green-600',
                            'ts' => 'fab fa-js-square text-blue-500',
                            'jsx' => 'fab fa-react text-cyan-500',
                            'tsx' => 'fab fa-react text-cyan-500',
                            'vue' => 'fab fa-vuejs text-green-500',
                            'scss' => 'fab fa-sass text-pink-500',
                            'less' => 'fab fa-less text-blue-500',
                            'sass' => 'fab fa-sass text-pink-500'
                        ];
                        $icon_class = $file_icons[$extension] ?? 'fas fa-file text-gray-400';
                        ?>
                        <div class="file-item" data-file-name="<?php echo strtolower($file['name']); ?>" 
                             data-file-path="<?php echo htmlspecialchars($file['path']); ?>" 
                             data-file-extension="<?php echo $file['extension']; ?>"
                             data-is-text="<?php echo $file['is_text_file'] ? 'true' : 'false'; ?>"
                             onclick="openFile(this)">
                            <i class="<?php echo $icon_class; ?>"></i>
                            <span><?php echo htmlspecialchars($file['name']); ?></span>
                            <span class="ml-auto text-xs text-gray-500"><?php echo formatFileSize($file['size']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-gray-500">
                        <i class="fas fa-folder-open text-3xl mb-2"></i>
                        <p class="text-sm">No files found</p>
                        <p class="text-xs mt-2">Check debug panel above for path information</p>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Main Editor Area -->
        <main class="flex-1 flex flex-col">
            <!-- Tabs -->
            <div class="editor-tabs" id="tabsContainer">
                <!-- Tabs will be dynamically added here -->
            </div>

            <!-- Editor Content -->
            <div class="editor-content" id="editorContent">
                <!-- Editor Pane -->
                <div class="editor-pane" id="editorPane">
                    <div id="monacoEditor" style="height: 100%; width: 100%;"></div>
                    
                    <!-- Loading indicator -->
                    <div class="loading hidden" id="loadingIndicator">
                        <div>
                            <div class="spinner"></div>
                            <p>Loading file...</p>
                        </div>
                    </div>
                    
                    <!-- Image Viewer -->
                    <div class="image-viewer hidden" id="imageViewer">
                        <img id="imageDisplay" src="" alt="Image Preview">
                    </div>
                    
                    <!-- Binary File Message -->
                    <div class="binary-file-message hidden" id="binaryFileMessage">
                        <i class="fas fa-file text-5xl mb-4"></i>
                        <p class="text-lg mb-2">Binary File</p>
                        <p class="text-sm">This file cannot be edited in the text editor.</p>
                    </div>
                    
                    <!-- Welcome Screen -->
                    <div class="flex flex-col items-center justify-center h-full text-gray-400" id="welcomeScreen">
                        <i class="fas fa-code text-6xl mb-4"></i>
                        <h2 class="text-xl font-medium mb-2">Welcome to CodeHub Editor</h2>
                        <p class="text-sm mb-6">Select a file from the sidebar to start editing</p>
                        <div class="flex gap-4">
                            <div class="text-center">
                                <i class="fas fa-keyboard text-2xl mb-2"></i>
                                <p class="text-xs">Ctrl+S to save</p>
                            </div>
                            <div class="text-center">
                                <i class="fas fa-search text-2xl mb-2"></i>
                                <p class="text-xs">Ctrl+F to find</p>
                            </div>
                            <div class="text-center">
                                <i class="fas fa-code-branch text-2xl mb-2"></i>
                                <p class="text-xs">Syntax highlighting</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Resizer -->
                <div class="resizer hidden" id="resizer"></div>
                
                <!-- Preview Pane -->
                <div class="preview-pane" id="previewPane">
                    <div class="preview-header">
                        <span class="text-sm font-medium text-gray-700">Live Preview</span>
                        <div class="flex gap-2">
                            <button onclick="refreshPreview()" class="text-gray-600 hover:text-gray-900">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button onclick="togglePreview()" class="text-gray-600 hover:text-gray-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <iframe class="w-full h-full" id="previewFrame"></iframe>
                </div>
                
                <!-- AI Assistant Panel -->
                <div class="ai-panel hidden" id="aiPanel">
                    <div class="ai-panel-header">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-robot text-purple-400"></i>
                            <span class="font-medium">CodeHub Pilot</span>
                        </div>
                        <button onclick="toggleAIPanel()" class="text-gray-400 hover:text-white">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="ai-panel-content" id="aiPanelContent">
                        <div class="text-gray-400 text-sm mb-4">
                            Ask me to generate code, explain concepts, or help with debugging.
                        </div>
                        <div id="aiSuggestions"></div>
                    </div>
                    <div class="ai-panel-footer">
                        <div class="ai-input-group">
                            <input type="text" id="aiInput" class="ai-input" placeholder="Ask for code help..." onkeypress="handleAIKeyPress(event)">
                            <button id="aiSendButton" class="ai-button" onclick="sendAIRequest()">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Bar -->
            <div class="status-bar">
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1">
                        <i class="fas fa-code-branch text-xs"></i>
                        <?php echo htmlspecialchars($repo['default_branch']); ?>
                    </span>
                    <span id="fileType">No file open</span>
                    <span id="encoding">UTF-8</span>
                    <span id="eol">LF</span>
                </div>
                <div class="flex items-center gap-4">
                    <span id="cursorPosition">Ln 1, Col 1</span>
                    <span id="saveStatus">Ready</span>
                </div>
            </div>
        </main>
    </div>

    <!-- Monaco Editor -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs/loader.js"></script>
    
    <script>
    // File content storage - use the properly structured array
    const files = <?php echo json_encode($files_js); ?>;
    const repoId = <?php echo $repo_id; ?>;
    
    console.log('Files loaded:', files); // Debug log
    
    let currentFile = null;
    let currentFilePath = null;
    let currentFileExtension = null;
    let editor = null;
    let unsavedChanges = false;
    let openTabs = new Map();
    let previewOpen = false;
    let isResizing = false;
    let aiPanelOpen = false;
    let currentLanguage = 'plaintext';
    let suggestionWidget = null;
    let currentSuggestion = null;

    // File types that support syntax highlighting
    const textFileExtensions = ['txt', 'html', 'css', 'js', 'php', 'py', 'json', 'md', 'xml', 'yml', 'yaml', 'sql', 'sh', 'ts', 'jsx', 'tsx', 'vue', 'scss', 'less', 'sass'];
    const imageFileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'bmp', 'webp'];
    const binaryFileExtensions = ['pdf', 'doc', 'docx', 'zip', 'rar', 'exe', 'dll'];

    // Initialize Monaco Editor
    require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs' } });
    require(['vs/editor/editor.main'], function() {
        editor = monaco.editor.create(document.getElementById('monacoEditor'), {
            value: '',
            language: 'plaintext',
            theme: 'vs-dark',
            automaticLayout: true,
            minimap: { enabled: true },
            scrollBeyondLastLine: false,
            fontSize: 14,
            tabSize: 4,
            wordWrap: 'on',
            lineNumbers: 'on',
            folding: true,
            renderWhitespace: 'selection',
            renderControlCharacters: false,
            renderIndentGuides: true,
            rulers: [80, 120],
            suggestOnTriggerCharacters: true,
            quickSuggestions: true,
            parameterHints: { enabled: true }
        });
        
        // Add event listeners
        editor.onDidChangeModelContent(() => {
            unsavedChanges = true;
            updateSaveStatus('• Modified');
            
            // Trigger AI suggestion after a pause in typing
            clearTimeout(window.suggestionTimeout);
            window.suggestionTimeout = setTimeout(() => {
                if (aiPanelOpen) {
                    requestAISuggestion();
                }
            }, 2000);
        });
        
        editor.onDidChangeCursorPosition(() => {
            updateCursorPosition();
        });
        
        editor.onDidChangeModelLanguage(() => {
            currentLanguage = editor.getModel().getLanguageId();
        });
        
        // Add save command
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, function() {
            saveFile();
        });
        
        // Add AI suggestion command
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.Space, function() {
            if (aiPanelOpen) {
                requestAISuggestion();
            }
        });
        
        // Create custom suggest provider
        monaco.languages.registerCompletionItemProvider('*', {
            provideCompletionItems: function(model, position) {
                if (!aiPanelOpen) return { suggestions: [] };
                
                // Trigger AI suggestion
                setTimeout(() => requestAISuggestion(), 500);
                
                return { suggestions: [] };
            }
        });
    });

    // Initialize resizer functionality - FIXED
    function initResizer() {
        const resizer = document.getElementById('resizer');
        const editorPane = document.getElementById('editorPane');
        const previewPane = document.getElementById('previewPane');
        const editorContent = document.getElementById('editorContent');
        
        resizer.addEventListener('mousedown', (e) => {
            isResizing = true;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            e.preventDefault();
            
            document.addEventListener('mousemove', handleMouseMove);
            document.addEventListener('mouseup', handleMouseUp);
        });
        
        function handleMouseMove(e) {
            if (!isResizing) return;
            
            const containerRect = editorContent.getBoundingClientRect();
            const percentage = ((e.clientX - containerRect.left) / containerRect.width) * 100;
            
            // Limit the range to 20% - 80%
            const limitedPercentage = Math.max(20, Math.min(80, percentage));
            
            // FIXED: Use width instead of flex for better control
            editorPane.style.width = `${limitedPercentage}%`;
            previewPane.style.width = `${100 - limitedPercentage}%`;
            
            // Refresh Monaco editor layout
            if (editor) {
                editor.layout();
            }
        }
        
        function handleMouseUp() {
            isResizing = false;
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            
            document.removeEventListener('mousemove', handleMouseMove);
            document.removeEventListener('mouseup', handleMouseUp);
        }
    }

    // Toggle AI Panel
    function toggleAIPanel() {
        const aiPanel = document.getElementById('aiPanel');
        aiPanelOpen = !aiPanelOpen;
        
        if (aiPanelOpen) {
            aiPanel.classList.remove('hidden');
        } else {
            aiPanel.classList.add('hidden');
        }
    }

    // Handle AI input key press
    function handleAIKeyPress(event) {
        if (event.key === 'Enter') {
            sendAIRequest();
        }
    }

    // Send AI request
    function sendAIRequest() {
        const input = document.getElementById('aiInput');
        const prompt = input.value.trim();
        
        if (!prompt) return;
        
        const sendButton = document.getElementById('aiSendButton');
        sendButton.disabled = true;
        sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        const formData = new FormData();
        formData.append('action', 'ai_generate');
        formData.append('prompt', prompt);
        formData.append('language', currentLanguage);
        
        fetch('editor.php?id=' + repoId, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            sendButton.disabled = false;
            sendButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
            
            if (data.success) {
                addAISuggestion(data.code, prompt);
                input.value = '';
            } else {
                showNotification('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            sendButton.disabled = false;
            sendButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
            showNotification('Error generating code', 'error');
        });
    }

    // Request AI suggestion
    function requestAISuggestion() {
        if (!editor || !currentFilePath) return;
        
        const position = editor.getPosition();
        const code = editor.getValue();
        const cursorPosition = `${position.lineNumber}:${position.column}`;
        
        const formData = new FormData();
        formData.append('action', 'ai_suggest');
        formData.append('code', code);
        formData.append('language', currentLanguage);
        formData.append('cursor_position', cursorPosition);
        
        fetch('editor.php?id=' + repoId, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.suggestion) {
                showInlineSuggestion(data.suggestion);
            }
        })
        .catch(error => {
            console.error('Error getting suggestion:', error);
        });
    }

    // Show inline suggestion
    function showInlineSuggestion(suggestion) {
        if (!editor) return;
        
        // Remove existing suggestion widget
        if (suggestionWidget) {
            suggestionWidget.dispose();
        }
        
        // Create a decoration for the suggestion
        const position = editor.getPosition();
        const model = editor.getModel();
        
        // Create a widget to show the suggestion
        suggestionWidget = {
            getId: () => 'ai.suggestion.widget',
            getDomNode: () => {
                const domNode = document.createElement('div');
                domNode.className = 'ai-suggestion-widget';
                domNode.style.position = 'absolute';
                domNode.style.background = '#2d3748';
                domNode.style.border = '1px solid #4b5563';
                domNode.style.borderRadius = '4px';
                domNode.style.padding = '8px';
                domNode.style.color = '#e2e8f0';
                domNode.style.fontSize = '12px';
                domNode.style.fontFamily = 'monospace';
                domNode.style.zIndex = '1000';
                domNode.style.maxWidth = '400px';
                domNode.style.overflow = 'auto';
                domNode.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.3)';
                
                const suggestionText = document.createElement('div');
                suggestionText.textContent = suggestion;
                domNode.appendChild(suggestionText);
                
                const actions = document.createElement('div');
                actions.style.marginTop = '8px';
                actions.style.display = 'flex';
                actions.style.gap = '8px';
                
                const acceptButton = document.createElement('button');
                acceptButton.textContent = 'Accept';
                acceptButton.style.background = '#10b981';
                acceptButton.style.color = 'white';
                acceptButton.style.border = 'none';
                acceptButton.style.borderRadius = '4px';
                acceptButton.style.padding = '4px 8px';
                acceptButton.style.fontSize = '12px';
                acceptButton.style.cursor = 'pointer';
                acceptButton.onclick = () => {
                    editor.executeEdits('ai-suggestion', [{
                        range: new monaco.Range(
                            position.lineNumber,
                            position.column,
                            position.lineNumber,
                            position.column
                        ),
                        text: suggestion
                    }]);
                    suggestionWidget.dispose();
                    suggestionWidget = null;
                };
                
                const rejectButton = document.createElement('button');
                rejectButton.textContent = 'Reject';
                rejectButton.style.background = '#ef4444';
                rejectButton.style.color = 'white';
                rejectButton.style.border = 'none';
                rejectButton.style.borderRadius = '4px';
                rejectButton.style.padding = '4px 8px';
                rejectButton.style.fontSize = '12px';
                rejectButton.style.cursor = 'pointer';
                rejectButton.onclick = () => {
                    suggestionWidget.dispose();
                    suggestionWidget = null;
                };
                
                actions.appendChild(acceptButton);
                actions.appendChild(rejectButton);
                domNode.appendChild(actions);
                
                return domNode;
            },
            getPosition: () => {
                return {
                    position: position,
                    preference: [monaco.editor.ContentWidgetPositionPreference.ABOVE]
                };
            },
            dispose: () => {
                if (suggestionWidget && editor) {
                    editor.removeContentWidget(suggestionWidget);
                    suggestionWidget = null;
                }
            }
        };
        
        editor.addContentWidget(suggestionWidget);
        currentSuggestion = suggestion;
    }

    // Add AI suggestion to panel
    function addAISuggestion(code, prompt) {
        const suggestionsContainer = document.getElementById('aiSuggestions');
        
        const suggestionDiv = document.createElement('div');
        suggestionDiv.className = 'ai-suggestion';
        
        const promptDiv = document.createElement('div');
        promptDiv.className = 'text-xs text-gray-400 mb-2';
        promptDiv.textContent = `Prompt: ${prompt}`;
        
        const codePre = document.createElement('pre');
        codePre.textContent = code;
        
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'ai-suggestion-actions';
        
        const acceptButton = document.createElement('button');
        acceptButton.className = 'accept';
        acceptButton.textContent = 'Accept';
        acceptButton.onclick = () => {
            if (editor) {
                const position = editor.getPosition();
                editor.executeEdits('ai-suggestion', [{
                    range: new monaco.Range(
                        position.lineNumber,
                        position.column,
                        position.lineNumber,
                        position.column
                    ),
                    text: code
                }]);
                editor.focus();
            }
        };
        
        const rejectButton = document.createElement('button');
        rejectButton.className = 'reject';
        rejectButton.textContent = 'Reject';
        rejectButton.onclick = () => {
            suggestionDiv.remove();
        };
        
        actionsDiv.appendChild(acceptButton);
        actionsDiv.appendChild(rejectButton);
        
        suggestionDiv.appendChild(promptDiv);
        suggestionDiv.appendChild(codePre);
        suggestionDiv.appendChild(actionsDiv);
        
        suggestionsContainer.appendChild(suggestionDiv);
        
        // Scroll to bottom
        suggestionsContainer.scrollTop = suggestionsContainer.scrollHeight;
    }

    // Open file
    function openFile(element) {
        console.log('openFile called with element:', element);
        console.log('File path:', element.dataset.filePath);
        console.log('File name:', element.querySelector('span').textContent);
        console.log('File extension:', element.dataset.fileExtension);
        console.log('Is text file:', element.dataset.isText);
        
        const filePath = element.dataset.filePath;
        const fileName = element.querySelector('span').textContent;
        const fileExtension = element.dataset.fileExtension;
        const isTextFile = element.dataset.isText === 'true';
        
        // Check if file data exists in files array
        const fileData = files.find(f => f.path === filePath);
        console.log('Found file data:', fileData);
        
        // Save current file content if there are unsaved changes
        if (unsavedChanges && currentFile) {
            if (!confirm('You have unsaved changes. Do you want to save before switching?')) {
                return;
            }
            saveFile();
        }
        
        // Update active file
        currentFile = fileName;
        currentFilePath = filePath;
        currentFileExtension = fileExtension;
        
        // Hide welcome screen
        document.getElementById('welcomeScreen').classList.add('hidden');
        
        // Show appropriate viewer based on file type
        if (imageFileExtensions.includes(fileExtension.toLowerCase())) {
            // Show image viewer
            document.getElementById('monacoEditor').style.display = 'none';
            document.getElementById('imageViewer').classList.remove('hidden');
            document.getElementById('binaryFileMessage').classList.add('hidden');
            document.getElementById('loadingIndicator').classList.add('hidden');
            
            // For images, use the get_file.php
            const imageUrl = 'get_file.php?repo_id=' + repoId + '&file=' + encodeURIComponent(fileData.relative_path);
            document.getElementById('imageDisplay').src = imageUrl;
            
        } else if (binaryFileExtensions.includes(fileExtension.toLowerCase())) {
            // Show binary file message
            document.getElementById('monacoEditor').style.display = 'none';
            document.getElementById('imageViewer').classList.add('hidden');
            document.getElementById('binaryFileMessage').classList.remove('hidden');
            document.getElementById('loadingIndicator').classList.add('hidden');
            
        } else if (isTextFile) {
            // Show text editor
            document.getElementById('monacoEditor').style.display = 'block';
            document.getElementById('imageViewer').classList.add('hidden');
            document.getElementById('binaryFileMessage').classList.add('hidden');
            
            // Load file content
            loadFileContent(filePath, fileName, fileExtension);
            
        } else {
            // Try to load as text file anyway
            document.getElementById('monacoEditor').style.display = 'block';
            document.getElementById('imageViewer').classList.add('hidden');
            document.getElementById('binaryFileMessage').classList.add('hidden');
            
            // Load file content from server
            loadFileContentFromServer(filePath, fileName, fileExtension);
        }
        
        // Update UI - activate the clicked file in sidebar
        document.querySelectorAll('.file-item').forEach(item => {
            item.classList.remove('active');
        });
        element.classList.add('active');
        
        // Update tab
        updateActiveTab(fileName, filePath, fileExtension);
        
        // Update status
        updateFileType(fileName, fileExtension);
        
        unsavedChanges = false;
    }

    // Load file content from preloaded data
    function loadFileContent(filePath, fileName, fileExtension) {
        const fileData = files.find(f => f.path === filePath);
        
        if (fileData && fileData.content !== null) {
            // Content is already loaded
            if (editor) {
                editor.setValue(fileData.content);
                
                // Set language based on file extension
                const languageMap = {
                    'html': 'html',
                    'css': 'css',
                    'js': 'javascript',
                    'php': 'php',
                    'py': 'python',
                    'json': 'json',
                    'md': 'markdown',
                    'xml': 'xml',
                    'yml': 'yaml',
                    'yaml': 'yaml',
                    'sql': 'sql',
                    'sh': 'shell',
                    'ts': 'typescript',
                    'jsx': 'javascript',
                    'tsx': 'typescript',
                    'vue': 'html',
                    'scss': 'scss',
                    'less': 'less',
                    'sass': 'sass'
                };
                
                const language = languageMap[fileExtension.toLowerCase()] || 'plaintext';
                monaco.editor.setModelLanguage(editor.getModel(), language);
                currentLanguage = language;
            }
            
            updateSaveStatus('Saved');
        } else {
            // Load from server
            loadFileContentFromServer(filePath, fileName, fileExtension);
        }
    }

    // Load file content from server
    function loadFileContentFromServer(filePath, fileName, fileExtension) {
        // Show loading indicator
        document.getElementById('loadingIndicator').classList.remove('hidden');
        document.getElementById('monacoEditor').style.display = 'none';
        
        const formData = new FormData();
        formData.append('action', 'load_file');
        formData.append('file_path', filePath);
        
        fetch('editor.php?id=' + repoId, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('loadingIndicator').classList.add('hidden');
            document.getElementById('monacoEditor').style.display = 'block';
            
            if (data.success) {
                if (editor) {
                    editor.setValue(data.content || '');
                    
                    // Set language based on file extension
                    const languageMap = {
                        'html': 'html',
                        'css': 'css',
                        'js': 'javascript',
                        'php': 'php',
                        'py': 'python',
                        'json': 'json',
                        'md': 'markdown',
                        'xml': 'xml',
                        'yml': 'yaml',
                        'yaml': 'yaml',
                        'sql': 'sql',
                        'sh': 'shell',
                        'ts': 'typescript',
                        'jsx': 'javascript',
                        'tsx': 'typescript',
                        'vue': 'html',
                        'scss': 'scss',
                        'less': 'less',
                        'sass': 'sass'
                    };
                    
                    const language = languageMap[fileExtension.toLowerCase()] || 'plaintext';
                    monaco.editor.setModelLanguage(editor.getModel(), language);
                    currentLanguage = language;
                }
                
                updateSaveStatus('Saved');
                
                // Update files array with loaded content
                const fileIndex = files.findIndex(f => f.path === filePath);
                if (fileIndex !== -1) {
                    files[fileIndex].content = data.content;
                }
            } else {
                showNotification('Error loading file: ' + data.message, 'error');
                updateSaveStatus('Error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('loadingIndicator').classList.add('hidden');
            document.getElementById('monacoEditor').style.display = 'block';
            showNotification('Error loading file', 'error');
            updateSaveStatus('Error');
        });
    }

    // Update active tab
    function updateActiveTab(fileName, filePath, fileExtension) {
        // Check if tab already exists
        if (!openTabs.has(filePath)) {
            // Create new tab
            const tab = document.createElement('div');
            tab.className = 'tab';
            tab.dataset.filePath = filePath;
            tab.dataset.fileName = fileName;
            tab.dataset.fileExtension = fileExtension;
            
            const fileIcons = {
                'php': 'fab fa-php text-purple-500',
                'js': 'fab fa-js-square text-yellow-500',
                'css': 'fab fa-css3-alt text-blue-500',
                'html': 'fab fa-html5 text-orange-500',
                'py': 'fab fa-python text-blue-600',
                'json': 'fas fa-code text-yellow-600',
                'md': 'fab fa-markdown text-blue-400',
                'txt': 'fas fa-file-alt text-gray-500',
                'jpg': 'fas fa-file-image text-green-500',
                'jpeg': 'fas fa-file-image text-green-500',
                'png': 'fas fa-file-image text-green-500',
                'gif': 'fas fa-file-image text-green-500',
                'svg': 'fas fa-file-image text-green-500',
                'pdf': 'fas fa-file-pdf text-red-500',
                'sql': 'fas fa-database text-orange-600',
                'xml': 'fas fa-code text-blue-400',
                'yml': 'fas fa-cog text-gray-500',
                'yaml': 'fas fa-cog text-gray-500',
                'sh': 'fas fa-terminal text-green-600',
                'ts': 'fab fa-js-square text-blue-500',
                'jsx': 'fab fa-react text-cyan-500',
                'tsx': 'fab fa-react text-cyan-500',
                'vue': 'fab fa-vuejs text-green-500',
                'scss': 'fab fa-sass text-pink-500',
                'less': 'fab fa-less text-blue-500',
                'sass': 'fab fa-sass text-pink-500'
            };
            const iconClass = fileIcons[fileExtension] || 'fas fa-file text-gray-400';
            
            tab.innerHTML = `
                <i class="${iconClass}"></i>
                <span>${fileName}</span>
                <i class="fas fa-times text-xs hover:text-red-400 ml-2" onclick="closeTab('${filePath}', event)"></i>
            `;
            
            tab.addEventListener('click', (e) => {
                if (!e.target.classList.contains('fa-times')) {
                    // Find the file item and click it
                    const fileItem = document.querySelector(`[data-file-path="${filePath}"]`);
                    if (fileItem) {
                        openFile(fileItem);
                    }
                }
            });
            
            document.getElementById('tabsContainer').appendChild(tab);
            openTabs.set(filePath, tab);
        }
        
        // Update active state
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        openTabs.get(filePath).classList.add('active');
    }

    // Close tab
    function closeTab(filePath, event) {
        event.stopPropagation();
        
        if (unsavedChanges && filePath === currentFilePath) {
            if (!confirm('You have unsaved changes. Do you want to save before closing?')) {
                return;
            }
            saveFile();
        }
        
        const tab = openTabs.get(filePath);
        if (tab) {
            tab.remove();
            openTabs.delete(filePath);
            
            // If we're closing the current tab, open another one or clear editor
            if (filePath === currentFilePath) {
                if (openTabs.size > 0) {
                    const firstTab = Array.from(openTabs.values())[0];
                    const fileItem = document.querySelector(`[data-file-path="${firstTab.dataset.filePath}"]`);
                    if (fileItem) {
                        openFile(fileItem);
                    }
                } else {
                    currentFile = null;
                    currentFilePath = null;
                    currentFileExtension = null;
                    
                    if (editor) {
                        editor.setValue('');
                    }
                    
                    updateSaveStatus('Ready');
                    updateFileType('No file open');
                    
                    // Hide all viewers and show welcome screen
                    document.getElementById('monacoEditor').style.display = 'none';
                    document.getElementById('imageViewer').classList.add('hidden');
                    document.getElementById('binaryFileMessage').classList.add('hidden');
                    document.getElementById('welcomeScreen').classList.remove('hidden');
                    
                    // Clear active file in sidebar
                    document.querySelectorAll('.file-item').forEach(item => {
                        item.classList.remove('active');
                    });
                }
            }
        }
    }

    // Save file
    function saveFile() {
        if (!currentFilePath) {
            showNotification('No file is open to save.', 'error');
            return;
        }
        
        // Don't try to save images or binary files
        if (imageFileExtensions.includes(currentFileExtension) || binaryFileExtensions.includes(currentFileExtension)) {
            showNotification('This file type cannot be saved from the editor.', 'error');
            return;
        }
        
        const content = editor ? editor.getValue() : '';
        
        const formData = new FormData();
        formData.append('action', 'save_file');
        formData.append('file_path', currentFilePath);
        formData.append('content', content);
        
        fetch('editor.php?id=' + repoId, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateSaveStatus('Saved');
                unsavedChanges = false;
                
                // Update files array
                const fileIndex = files.findIndex(f => f.path === currentFilePath);
                if (fileIndex !== -1) {
                    files[fileIndex].content = content;
                }
                
                // Show success message
                showNotification('File saved successfully!', 'success');
            } else {
                showNotification('Error saving file: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error saving file', 'error');
        });
    }

    // Show notification
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('hide');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }

    // Update save status
    function updateSaveStatus(status) {
        document.getElementById('saveStatus').textContent = status;
    }

    // Update file type
    function updateFileType(fileName, fileExtension = '') {
        const fileTypes = {
            'php': 'PHP',
            'js': 'JavaScript',
            'css': 'CSS',
            'html': 'HTML',
            'py': 'Python',
            'json': 'JSON',
            'md': 'Markdown',
            'txt': 'Plain Text',
            'jpg': 'JPEG Image',
            'jpeg': 'JPEG Image',
            'png': 'PNG Image',
            'gif': 'GIF Image',
            'svg': 'SVG Image',
            'pdf': 'PDF Document',
            'sql': 'SQL',
            'xml': 'XML',
            'yml': 'YAML',
            'yaml': 'YAML',
            'sh': 'Shell Script',
            'ts': 'TypeScript',
            'jsx': 'React JSX',
            'tsx': 'React TSX',
            'vue': 'Vue Component',
            'scss': 'SCSS',
            'less': 'LESS',
            'sass': 'SASS'
        };
        document.getElementById('fileType').textContent = fileTypes[fileExtension] || 'Unknown';
    }

    // Update cursor position
    function updateCursorPosition() {
        if (editor) {
            const position = editor.getPosition();
            document.getElementById('cursorPosition').textContent = `Ln ${position.lineNumber}, Col ${position.column}`;
        }
    }

    // Run code
    function runCode() {
        if (currentFile && (currentFile.endsWith('.html') || currentFile.endsWith('.htm'))) {
            const content = editor ? editor.getValue() : '';
            const frame = document.getElementById('previewFrame');
            
            if (!previewOpen) {
                togglePreview();
            }
            
            frame.srcdoc = content;
        } else {
            showNotification('Preview is only available for HTML files', 'info');
        }
    }

    // Toggle preview - FIXED
    function togglePreview() {
        const previewPane = document.getElementById('previewPane');
        const resizer = document.getElementById('resizer');
        const editorPane = document.getElementById('editorPane');
        
        previewOpen = !previewOpen;
        
        if (previewOpen) {
            // Show preview pane
            previewPane.classList.add('open');
            resizer.classList.remove('hidden');
            
            // Set initial sizes - FIXED: Use width instead of flex
            editorPane.style.width = '50%';
            previewPane.style.width = '50%';
            
            // Run the code if it's an HTML file
            if (currentFile && (currentFile.endsWith('.html') || currentFile.endsWith('.htm'))) {
                const content = editor ? editor.getValue() : '';
                document.getElementById('previewFrame').srcdoc = content;
            }
            
            // Refresh Monaco editor layout
            if (editor) {
                editor.layout();
            }
        } else {
            // Hide preview pane
            previewPane.classList.remove('open');
            resizer.classList.add('hidden');
            
            // Reset editor pane to full width - FIXED: Use width instead of flex
            editorPane.style.width = '100%';
            previewPane.style.width = '0';
            
            // Refresh Monaco editor layout
            if (editor) {
                editor.layout();
            }
        }
    }

    // Refresh preview
    function refreshPreview() {
        if (previewOpen && currentFile && (currentFile.endsWith('.html') || currentFile.endsWith('.htm'))) {
            const content = editor ? editor.getValue() : '';
            document.getElementById('previewFrame').srcdoc = content;
        }
    }

    // File search functionality
    document.getElementById('fileSearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const fileItems = document.querySelectorAll('.file-item');
        
        fileItems.forEach(item => {
            const fileName = item.dataset.fileName;
            if (fileName.includes(searchTerm)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    });

    // Auto-save every 30 seconds
    setInterval(() => {
        if (unsavedChanges && currentFilePath && 
            !imageFileExtensions.includes(currentFileExtension) && 
            !binaryFileExtensions.includes(currentFileExtension)) {
            saveFile();
        }
    }, 30000);

    // Warn before leaving with unsaved changes
    window.addEventListener('beforeunload', (e) => {
        if (unsavedChanges) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });

    // Debug function to test file opening
    function debugFileOpening() {
        const firstFile = document.querySelector('.file-item');
        if (firstFile) {
            console.log('First file element:', firstFile);
            console.log('First file data:', firstFile.dataset);
        }
    }

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Page loaded, files:', files);
        debugFileOpening();
        initResizer();
    });
    </script>
</body>
</html>