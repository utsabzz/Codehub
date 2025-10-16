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

// Get repository files in a simple flat structure
function getRepoFilesFlat($repo_path) {
    $base_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repo_path;
    $files = [];
    
    if (!is_dir($base_path)) {
        return $files;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        $relative_path = str_replace($base_path . '/', '', $file->getPathname());
        
        if (!$file->isDir()) {
            $files[] = [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'relative_path' => $relative_path,
                'content' => file_get_contents($file->getPathname()),
                'size' => $file->getSize(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                'is_dir' => false,
                'extension' => pathinfo($file->getFilename(), PATHINFO_EXTENSION)
            ];
        }
    }
    
    // Sort files by name
    usort($files, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
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
}

// Get repository files
$repo_files = getRepoFilesFlat($repo['path']);

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
    <!-- Prism.js for syntax highlighting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-json.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markdown.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;600&family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .code-font {
            font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
        }
        
        .editor-container {
            height: calc(100vh - 60px);
        }
        
        .sidebar {
            width: 250px;
            background: #1e1e1e;
            border-right: 1px solid #333;
        }
        
        .file-item {
            padding: 6px 12px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #9ca3af;
            font-size: 13px;
        }
        
        .file-item:hover {
            background: #2a2a2a;
            color: #fff;
        }
        
        .file-item.active {
            background: #2d3748;
            color: #fff;
            border-left: 3px solid #f97316;
        }
        
        .tab {
            padding: 8px 16px;
            background: #2d3748;
            border-right: 1px solid #1a202c;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #9ca3af;
            font-size: 13px;
            transition: all 0.2s;
            border-top: 2px solid transparent;
        }
        
        .tab:hover {
            background: #374151;
        }
        
        .tab.active {
            background: #1e1e1e;
            color: #fff;
            border-top-color: #f97316;
        }
        
        .editor-wrapper {
            position: relative;
            height: 100%;
            background: #1e1e1e;
        }
        
        .line-numbers {
            position: absolute;
            left: 0;
            top: 0;
            width: 50px;
            height: 100%;
            background: #1a1a1a;
            color: #6b7280;
            padding: 12px 0;
            text-align: right;
            user-select: none;
            font-size: 13px;
            line-height: 1.5;
            overflow: hidden;
            z-index: 1;
        }
        
        .code-editor {
            width: 100%;
            height: 100%;
            padding: 12px 12px 12px 62px;
            background: #1e1e1e;
            color: #e5e7eb;
            border: none;
            outline: none;
            resize: none;
            font-size: 14px;
            line-height: 1.5;
            tab-size: 4;
            white-space: pre;
            overflow-x: auto;
            overflow-y: auto;
            font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
        }
        
        .code-highlight {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            padding: 12px 12px 12px 62px;
            background: transparent;
            color: transparent;
            border: none;
            pointer-events: none;
            font-size: 14px;
            line-height: 1.5;
            tab-size: 4;
            white-space: pre;
            overflow: hidden;
            font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
            z-index: 0;
        }
        
        .preview-frame {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
        }
        
        .status-bar {
            height: 24px;
            background: #1a1a1a;
            border-top: 1px solid #333;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 12px;
            font-size: 11px;
            color: #9ca3af;
        }
        
        .image-viewer {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: #1e1e1e;
        }
        
        .image-viewer img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
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
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: #1a1a1a;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #4a5568;
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #718096;
        }
        
        /* Prism theme adjustments */
        .code-highlight .token.comment,
        .code-highlight .token.prolog,
        .code-highlight .token.doctype,
        .code-highlight .token.cdata {
            color: #6a9955;
        }
        
        .code-highlight .token.punctuation {
            color: #d4d4d4;
        }
        
        .code-highlight .token.property,
        .code-highlight .token.tag,
        .code-highlight .token.boolean,
        .code-highlight .token.number,
        .code-highlight .token.constant,
        .code-highlight .token.symbol,
        .code-highlight .token.deleted {
            color: #b5cea8;
        }
        
        .code-highlight .token.selector,
        .code-highlight .token.attr-name,
        .code-highlight .token.string,
        .code-highlight .token.char,
        .code-highlight .token.builtin,
        .code-highlight .token.inserted {
            color: #ce9178;
        }
        
        .code-highlight .token.operator,
        .code-highlight .token.entity,
        .code-highlight .token.url,
        .code-highlight .language-css .token.string,
        .code-highlight .style .token.string {
            color: #d4d4d4;
        }
        
        .code-highlight .token.atrule,
        .code-highlight .token.attr-value,
        .code-highlight .token.keyword {
            color: #c586c0;
        }
        
        .code-highlight .token.function,
        .code-highlight .token.class-name {
            color: #dcdcaa;
        }
        
        .code-highlight .token.regex,
        .code-highlight .token.important,
        .code-highlight .token.variable {
            color: #d16969;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 overflow-hidden">
    <!-- Header -->
    <header class="bg-gray-800 border-b border-gray-700 h-14 flex items-center justify-between px-4">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-code text-orange-500 text-xl"></i>
                <span class="font-semibold text-lg">CodeHub Editor</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <span class="text-gray-400">Project:</span>
                <span class="text-white"><?php echo htmlspecialchars($repo['name']); ?></span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="runCode()" class="px-3 py-1 bg-green-600 hover:bg-green-700 rounded text-sm font-medium flex items-center gap-2 transition">
                <i class="fas fa-play"></i>
                Run
            </button>
            <button onclick="togglePreview()" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 rounded text-sm font-medium flex items-center gap-2 transition">
                <i class="fas fa-eye"></i>
                Preview
            </button>
            <button onclick="saveFile()" class="px-3 py-1 bg-orange-600 hover:bg-orange-700 rounded text-sm font-medium flex items-center gap-2 transition">
                <i class="fas fa-save"></i>
                Save
            </button>
            <a href="view_repo.php?id=<?php echo $repo_id; ?>" class="px-3 py-1 bg-gray-600 hover:bg-gray-700 rounded text-sm font-medium flex items-center gap-2 transition">
                <i class="fas fa-arrow-left"></i>
                Back to Repo
            </a>
        </div>
    </header>

    <div class="editor-container flex">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="p-3 border-b border-gray-700">
                <div class="text-xs text-gray-500 uppercase tracking-wider mb-2">Files</div>
            </div>
            <div class="overflow-y-auto" style="height: calc(100% - 40px);">
                <?php if (!empty($repo_files)): ?>
                    <?php foreach ($repo_files as $file): ?>
                        <?php
                        $extension = $file['extension'];
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
                            'jpeg' => 'fas fa-file-image text-green-500',
                            'png' => 'fas fa-file-image text-green-500',
                            'gif' => 'fas fa-file-image text-green-500',
                            'svg' => 'fas fa-file-image text-green-500',
                            'gitignore' => 'fas fa-eye-slash text-gray-500',
                            'license' => 'fas fa-balance-scale text-gray-500'
                        ];
                        $icon_class = $file_icons[$extension] ?? 'fas fa-file text-gray-400';
                        ?>
                        <div class="file-item" onclick="openFile('<?php echo htmlspecialchars($file['path']); ?>', '<?php echo htmlspecialchars($file['name']); ?>', '<?php echo $file['extension']; ?>', this)">
                            <i class="<?php echo $icon_class; ?>"></i>
                            <span><?php echo htmlspecialchars($file['name']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-gray-500">
                        <i class="fas fa-folder-open text-2xl mb-2"></i>
                        <p class="text-sm">No files found</p>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Main Editor Area -->
        <main class="flex-1 flex flex-col">
            <!-- Tabs -->
            <div class="flex bg-gray-800 border-b border-gray-700" id="tabsContainer">
                <!-- Tabs will be dynamically added here -->
            </div>

            <!-- Editor Content -->
            <div class="flex-1 flex">
                <div class="flex-1 flex">
                    <div class="editor-wrapper flex-1" id="editorWrapper">
                        <div class="line-numbers code-font" id="lineNumbers">1</div>
                        <textarea class="code-editor code-font" id="codeEditor" spellcheck="false" 
                                  oninput="updateLineNumbers(); handleInput(event); updateHighlight();" 
                                  onkeydown="handleKeyDown(event);"
                                  onscroll="syncScroll()"></textarea>
                        <pre class="code-highlight" id="codeHighlight"><code class="language-plaintext" id="highlightContent"></code></pre>
                        
                        <!-- Image Viewer -->
                        <div class="image-viewer hidden" id="imageViewer">
                            <img id="imageDisplay" src="" alt="Image Preview">
                        </div>
                        
                        <!-- Binary File Message -->
                        <div class="binary-file-message hidden" id="binaryFileMessage">
                            <i class="fas fa-file text-4xl mb-4"></i>
                            <p class="text-lg mb-2">Binary File</p>
                            <p class="text-sm">This file cannot be edited in the text editor.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Preview Panel -->
                <div class="w-1/2 bg-white border-l border-gray-700 hidden" id="previewPanel">
                    <div class="bg-gray-100 px-3 py-2 border-b border-gray-300 flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700">Live Preview</span>
                        <button onclick="refreshPreview()" class="text-gray-600 hover:text-gray-900">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <iframe class="preview-frame" id="previewFrame"></iframe>
                </div>
            </div>

            <!-- Status Bar -->
            <div class="status-bar">
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1">
                        <i class="fas fa-code-branch text-xs"></i>
                        <?php echo htmlspecialchars($repo['default_branch']); ?>
                    </span>
                    <span>UTF-8</span>
                    <span id="fileType">No file open</span>
                    <span>LF</span>
                </div>
                <div class="flex items-center gap-4">
                    <span id="cursorPosition">Ln 1, Col 1</span>
                    <span>Spaces: 4</span>
                    <span id="saveStatus">No file open</span>
                </div>
            </div>
        </main>
    </div>

    <script>
    // File content storage
    const files = <?php echo json_encode($repo_files); ?>;
    const repoId = <?php echo $repo_id; ?>;
    
    let currentFile = null;
    let currentFilePath = null;
    let currentFileExtension = null;
    let editor = document.getElementById('codeEditor');
    let lineNumbers = document.getElementById('lineNumbers');
    let codeHighlight = document.getElementById('codeHighlight');
    let highlightContent = document.getElementById('highlightContent');
    let imageViewer = document.getElementById('imageViewer');
    let imageDisplay = document.getElementById('imageDisplay');
    let binaryFileMessage = document.getElementById('binaryFileMessage');
    let unsavedChanges = false;
    let openTabs = new Map();

    // File types that support syntax highlighting
    const textFileExtensions = ['txt', 'html', 'css', 'js', 'php', 'py', 'json', 'md', 'xml', 'yml', 'yaml'];
    const imageFileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'bmp', 'webp'];
    const binaryFileExtensions = ['pdf', 'doc', 'docx', 'zip', 'rar', 'exe', 'dll'];

    // Language mapping for syntax highlighting
    const languageMap = {
        'html': 'markup',
        'css': 'css',
        'js': 'javascript',
        'php': 'php',
        'py': 'python',
        'json': 'json',
        'md': 'markdown',
        'xml': 'markup',
        'yml': 'yaml',
        'yaml': 'yaml'
    };

    // Initialize editor
    function initEditor() {
        updateLineNumbers();
        updateCursorPosition();
        
        // Open the first file if available
        if (files.length > 0) {
            const firstFile = files[0];
            openFile(firstFile.path, firstFile.name, firstFile.extension);
        }
    }

    // Update line numbers
    function updateLineNumbers() {
        const lines = editor.value.split('\n').length;
        let numbers = '';
        for (let i = 1; i <= lines; i++) {
            numbers += i + '\n';
        }
        lineNumbers.textContent = numbers;
    }

    // Update syntax highlighting
    function updateHighlight() {
        if (!currentFileExtension) return;
        
        const language = languageMap[currentFileExtension] || 'plaintext';
        highlightContent.className = `language-${language}`;
        highlightContent.textContent = editor.value;
        Prism.highlightElement(highlightContent);
    }

    // Sync scroll between editor and highlight
    function syncScroll() {
        codeHighlight.scrollTop = editor.scrollTop;
        codeHighlight.scrollLeft = editor.scrollLeft;
        lineNumbers.scrollTop = editor.scrollTop;
    }

    // Open file - SIMPLIFIED VERSION
    function openFile(filePath, fileName, fileExtension, element = null) {
        console.log('Opening file:', {filePath, fileName, fileExtension});
        
        // Save current file content if there are unsaved changes
        if (unsavedChanges && currentFile) {
            if (!confirm('You have unsaved changes. Do you want to save before switching?')) {
                return;
            }
            saveFile();
        }
        
        // Find file content - SIMPLE DIRECT MATCH
        const fileData = files.find(f => f.path === filePath);
        
        if (!fileData) {
            console.error('File not found in files array:', filePath);
            console.log('Available files:', files);
            alert('File not found: ' + fileName);
            return;
        }
        
        console.log('Found file data:', fileData);
        
        // Update active file
        currentFile = fileData.name;
        currentFilePath = fileData.path;
        currentFileExtension = fileData.extension;
        
        // Show appropriate viewer based on file type
        if (imageFileExtensions.includes(fileData.extension.toLowerCase())) {
            // Show image viewer
            editor.classList.add('hidden');
            codeHighlight.classList.add('hidden');
            imageViewer.classList.remove('hidden');
            binaryFileMessage.classList.add('hidden');
            
            // For images, use the get_file.php
            const imageUrl = 'get_file.php?repo_id=' + repoId + '&file=' + encodeURIComponent(fileData.relative_path);
            console.log('Loading image from:', imageUrl);
            imageDisplay.src = imageUrl;
            
        } else if (binaryFileExtensions.includes(fileData.extension.toLowerCase())) {
            // Show binary file message
            editor.classList.add('hidden');
            codeHighlight.classList.add('hidden');
            imageViewer.classList.add('hidden');
            binaryFileMessage.classList.remove('hidden');
            
        } else {
            // Show text editor with syntax highlighting
            editor.classList.remove('hidden');
            codeHighlight.classList.remove('hidden');
            imageViewer.classList.add('hidden');
            binaryFileMessage.classList.add('hidden');
            
            console.log('Setting editor content, length:', fileData.content?.length);
            editor.value = fileData.content || '';
            updateLineNumbers();
            updateHighlight();
        }
        
        // Update UI - activate the clicked file in sidebar
        document.querySelectorAll('.file-item').forEach(item => {
            item.classList.remove('active');
        });
        
        if (element) {
            element.classList.add('active');
        } else {
            // Find and activate the file item by matching the filename
            const fileItems = document.querySelectorAll('.file-item');
            fileItems.forEach(item => {
                const itemFileName = item.querySelector('span').textContent;
                if (itemFileName === fileName) {
                    item.classList.add('active');
                }
            });
        }
        
        // Update tab
        updateActiveTab(fileName, filePath, fileExtension);
        
        // Update status
        updateSaveStatus('Saved');
        updateFileType(fileName, fileExtension);
        
        unsavedChanges = false;
        
        console.log('File opened successfully:', fileName);
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
                'js': 'fab fa-js text-yellow-500',
                'css': 'fab fa-css3-alt text-blue-500',
                'html': 'fab fa-html5 text-orange-500',
                'py': 'fab fa-python text-blue-600',
                'json': 'fas fa-code text-yellow-600',
                'md': 'fas fa-markdown text-blue-400',
                'txt': 'fas fa-file-alt text-gray-500',
                'jpg': 'fas fa-file-image text-green-500',
                'jpeg': 'fas fa-file-image text-green-500',
                'png': 'fas fa-file-image text-green-500',
                'gif': 'fas fa-file-image text-green-500',
                'svg': 'fas fa-file-image text-green-500'
            };
            const iconClass = fileIcons[fileExtension] || 'fas fa-file text-gray-400';
            
            tab.innerHTML = `
                <i class="${iconClass}"></i>
                <span>${fileName}</span>
                <i class="fas fa-times text-xs hover:text-red-400 ml-2" onclick="closeTab('${filePath}', event)"></i>
            `;
            
            tab.addEventListener('click', (e) => {
                if (!e.target.classList.contains('fa-times')) {
                    openFile(filePath, fileName, fileExtension);
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
                    openFile(firstTab.dataset.filePath, firstTab.dataset.fileName, firstTab.dataset.fileExtension);
                } else {
                    currentFile = null;
                    currentFilePath = null;
                    currentFileExtension = null;
                    editor.value = '';
                    updateLineNumbers();
                    updateHighlight();
                    updateSaveStatus('No file open');
                    updateFileType('No file open');
                    
                    // Hide all viewers
                    editor.classList.remove('hidden');
                    codeHighlight.classList.remove('hidden');
                    imageViewer.classList.add('hidden');
                    binaryFileMessage.classList.add('hidden');
                    
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
            alert('No file is open to save.');
            return;
        }
        
        // Don't try to save images or binary files
        if (imageFileExtensions.includes(currentFileExtension) || binaryFileExtensions.includes(currentFileExtension)) {
            alert('This file type cannot be saved from the editor.');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'save_file');
        formData.append('file_path', currentFilePath);
        formData.append('content', editor.value);
        
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
                    files[fileIndex].content = editor.value;
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
        notification.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white ${
            type === 'success' ? 'bg-green-600' : 
            type === 'error' ? 'bg-red-600' : 'bg-blue-600'
        } z-50`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
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
            'svg': 'SVG Image'
        };
        document.getElementById('fileType').textContent = fileTypes[fileExtension] || 'Unknown';
    }

    // Run code
    function runCode() {
        if (currentFile && currentFile.endsWith('.html')) {
            const preview = document.getElementById('previewPanel');
            const frame = document.getElementById('previewFrame');
            
            preview.classList.remove('hidden');
            frame.srcdoc = editor.value;
        } else {
            alert('Preview is only available for HTML files');
        }
    }

    // Toggle preview
    function togglePreview() {
        const preview = document.getElementById('previewPanel');
        preview.classList.toggle('hidden');
        
        if (!preview.classList.contains('hidden')) {
            runCode();
        }
    }

    // Refresh preview
    function refreshPreview() {
        runCode();
    }

    // Handle input
    function handleInput(event) {
        unsavedChanges = true;
        updateSaveStatus('• Modified');
    }

    // Handle keydown
    function handleKeyDown(event) {
        // Tab key handling
        if (event.key === 'Tab') {
            event.preventDefault();
            const start = editor.selectionStart;
            const end = editor.selectionEnd;
            const value = editor.value;
            
            editor.value = value.substring(0, start) + '    ' + value.substring(end);
            editor.selectionStart = editor.selectionEnd = start + 4;
            
            // Update highlight after tab
            setTimeout(updateHighlight, 0);
        }
        
        // Ctrl/Cmd + S to save
        if ((event.ctrlKey || event.metaKey) && event.key === 's') {
            event.preventDefault();
            saveFile();
        }
        
        // Update cursor position
        updateCursorPosition();
    }

    // Update cursor position
    function updateCursorPosition() {
        const lines = editor.value.substring(0, editor.selectionStart).split('\n');
        const line = lines.length;
        const col = lines[lines.length - 1].length + 1;
        
        document.getElementById('cursorPosition').textContent = `Ln ${line}, Col ${col}`;
    }

    // Track cursor position on mouse up
    editor.addEventListener('mouseup', updateCursorPosition);
    editor.addEventListener('keyup', updateCursorPosition);
    editor.addEventListener('scroll', syncScroll);

    // Initialize on load
    window.addEventListener('load', initEditor);

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

    // Add console logging for debugging
    console.log('Editor initialized with files:', files);
</script>
</body>
</html>