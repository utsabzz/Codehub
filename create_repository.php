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
 $sql = "SELECT id, username, email, first_name, last_name, profile_image FROM users WHERE id = ?";
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

// Close connection
 $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create a new repository - CodeHub APS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .tab-active {
            border-bottom: 2px solid #0969da;
            color: #0969da;
        }
        
        .file-drop-area {
            border: 2px dashed #d1d5db;
            transition: all 0.3s ease;
        }
        
        .file-drop-area.dragover {
            border-color: #0969da;
            background-color: #f0f7ff;
        }
        
        .file-item {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .loading-spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #0969da;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
        
        .file-preview {
            max-height: 200px;
            overflow-y: auto;
            background: #f6f8fa;
            border: 1px solid #e1e4e8;
            border-radius: 6px;
            padding: 8px;
        }
        
        .file-preview pre {
            margin: 0;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
        }
        
        .pdf-preview {
            width: 100%;
            height: 200px;
            border: 1px solid #e1e4e8;
            border-radius: 6px;
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
                        <span class="text-gray-500 mx-2">/</span>
                        <span class="font-semibold text-gray-900">New repository</span>
                    </div>
                </div>
                
                <!-- Right Section -->
                <div class="flex items-center space-x-4">
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
    
    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">Create a new repository</h1>
            
            <!-- Error/Success Messages -->
            <div id="errorMessage" class="hidden mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg fade-in">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span id="errorText">Error occurred</span>
                </div>
            </div>
            
            <div id="successMessage" class="hidden mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg fade-in">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span id="successText">Repository created successfully</span>
                </div>
            </div>
            
            <!-- Repository Creation Form -->
            <form id="createRepoForm" class="space-y-6">
                <!-- Repository Name -->
                <div>
                    <label for="repoName" class="block text-sm font-medium text-gray-700 mb-2">
                        Repository name <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500"><?php echo $username; ?>/</span>
                        </div>
                        <input type="text" 
                               id="repoName" 
                               name="repoName" 
                               required
                               class="input-focus w-full pl-20 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="awesome-project">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Great repository names are short and memorable. Need inspiration? <a href="#" class="text-blue-600 hover:underline">Try name generator</a></p>
                </div>
                
                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description <span class="text-gray-400">(optional)</span>
                    </label>
                    <textarea id="description" 
                              name="description" 
                              rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Short and sweet helps people understand your project."></textarea>
                </div>
                
                <!-- Visibility -->
                <div>
                    <div class="text-sm font-medium text-gray-700 mb-2">Visibility</div>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" name="visibility" value="public" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                            <div class="ml-2">
                                <div class="font-medium text-gray-900">Public</div>
                                <div class="text-sm text-gray-500">Anyone on the internet can see this repository. You choose who can commit.</div>
                            </div>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="visibility" value="private" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                            <div class="ml-2">
                                <div class="font-medium text-gray-900">Private</div>
                                <div class="text-sm text-gray-500">You choose who can see and commit to this repository.</div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8">
                        <button type="button" id="uploadTab" class="tab-active py-2 px-1 text-sm font-medium">Upload Files</button>
                        <button type="button" id="initializeTab" class="py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700">Initialize Repository</button>
                    </nav>
                </div>
                
                <!-- Upload Files Tab -->
                <div id="uploadTabContent" class="mt-4">
                    <div class="file-drop-area p-8 rounded-lg text-center" id="fileDropArea">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                        <p class="text-lg font-medium text-gray-900 mb-2">Drag and drop your files here</p>
                        <p class="text-sm text-gray-500 mb-4">or</p>
                        <button type="button" id="browseFilesBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Browse Files
                        </button>
                        <input type="file" id="fileInput" multiple class="hidden">
                        <p class="text-xs text-gray-500 mt-4">You can upload multiple files at once. Large files may take longer to upload.</p>
                    </div>
                    
                    <!-- File List -->
                    <div id="fileList" class="mt-4 hidden">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Files to upload</h3>
                        <div id="fileItems" class="space-y-2 max-h-60 overflow-y-auto scrollbar-thin">
                            <!-- File items will be added here dynamically -->
                        </div>
                    </div>
                </div>
                
                <!-- Initialize Repository Tab -->
                <div id="initializeTabContent" class="mt-4 hidden">
                    <div class="space-y-4">
                        <!-- Add README -->
                        <div class="flex items-center">
                            <input type="checkbox" id="addReadme" name="addReadme" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="addReadme" class="ml-2 text-sm text-gray-700">
                                Add a README file
                            </label>
                        </div>
                        
                        <!-- Add .gitignore -->
                        <div class="flex items-center">
                            <input type="checkbox" id="addGitignore" name="addGitignore" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="addGitignore" class="ml-2 text-sm text-gray-700">
                                Add a .gitignore file
                            </label>
                        </div>
                        
                        <!-- Choose License -->
                        <div>
                            <label for="license" class="block text-sm font-medium text-gray-700 mb-2">
                                Choose a license
                            </label>
                            <select id="license" name="license" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">None</option>
                                <option value="mit">MIT License</option>
                                <option value="apache-2.0">Apache License 2.0</option>
                                <option value="gpl-3.0">GNU General Public License v3.0</option>
                                <option value="bsd-3-clause">BSD 3-Clause License</option>
                                <option value="bsd-2-clause">BSD 2-Clause License</option>
                                <option value="isc">ISC License</option>
                                <option value="lgpl-3.0">GNU Lesser General Public License v3.0</option>
                                <option value="mpl-2.0">Mozilla Public License 2.0</option>
                                <option value="unlicense">The Unlicense</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="button" id="cancelBtn" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 mr-3">
                        Cancel
                    </button>
                    <button type="submit" id="submitBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center">
                        <span id="btnText">Create repository</span>
                        <div id="btnLoader" class="loading-spinner ml-2 hidden"></div>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Tab switching
        const uploadTab = document.getElementById('uploadTab');
        const initializeTab = document.getElementById('initializeTab');
        const uploadTabContent = document.getElementById('uploadTabContent');
        const initializeTabContent = document.getElementById('initializeTabContent');
        
        uploadTab.addEventListener('click', function() {
            uploadTab.classList.add('tab-active');
            initializeTab.classList.remove('tab-active');
            uploadTabContent.classList.remove('hidden');
            initializeTabContent.classList.add('hidden');
        });
        
        initializeTab.addEventListener('click', function() {
            initializeTab.classList.add('tab-active');
            uploadTab.classList.remove('tab-active');
            initializeTabContent.classList.remove('hidden');
            uploadTabContent.classList.add('hidden');
        });
        
        // File upload handling
        const fileDropArea = document.getElementById('fileDropArea');
        const fileInput = document.getElementById('fileInput');
        const browseFilesBtn = document.getElementById('browseFilesBtn');
        const fileList = document.getElementById('fileList');
        const fileItems = document.getElementById('fileItems');
        const uploadedFiles = [];
        
        browseFilesBtn.addEventListener('click', function() {
            fileInput.click();
        });
        
        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
        });
        
        // Drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, function() {
                fileDropArea.classList.add('dragover');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, function() {
                fileDropArea.classList.remove('dragover');
            }, false);
        });
        
        fileDropArea.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        });
        
        function handleFiles(files) {
            ([...files]).forEach(uploadFile);
            fileList.classList.remove('hidden');
        }
        
        function uploadFile(file) {
            // Check if file already exists
            if (uploadedFiles.find(f => f.name === file.name)) {
                return;
            }
            
            uploadedFiles.push(file);
            
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item flex items-center justify-between p-2 bg-gray-50 rounded';
            
            const fileInfo = document.createElement('div');
            fileInfo.className = 'flex items-center flex-1';
            
            const fileIcon = document.createElement('i');
            fileIcon.className = getFileIcon(file.name);
            
            const fileName = document.createElement('span');
            fileName.className = 'ml-2 text-sm text-gray-700 truncate';
            fileName.textContent = file.name;
            fileName.title = file.name;
            
            const fileSize = document.createElement('span');
            fileSize.className = 'ml-2 text-xs text-gray-500';
            fileSize.textContent = formatFileSize(file.size);
            
            const filePreview = document.createElement('div');
            filePreview.className = 'hidden mt-2 file-preview';
            
            const previewBtn = document.createElement('button');
            previewBtn.className = 'text-xs text-blue-600 hover:text-blue-700';
            previewBtn.innerHTML = '<i class="fas fa-eye mr-1"></i>Preview';
            previewBtn.addEventListener('click', function() {
                showFilePreview(file, filePreview);
            });
            
            const removeBtn = document.createElement('button');
            removeBtn.className = 'text-red-500 hover:text-red-700';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.addEventListener('click', function() {
                fileItem.remove();
                const index = uploadedFiles.findIndex(f => f.name === file.name);
                if (index > -1) {
                    uploadedFiles.splice(index, 1);
                }
                if (uploadedFiles.length === 0) {
                    fileList.classList.add('hidden');
                }
            });
            
            fileInfo.appendChild(fileIcon);
            fileInfo.appendChild(fileName);
            fileInfo.appendChild(fileSize);
            fileInfo.appendChild(previewBtn);
            
            fileItem.appendChild(fileInfo);
            fileItem.appendChild(removeBtn);
            
            fileItems.appendChild(fileItem);
            
            // Create file preview
            createFilePreview(file, filePreview);
        }
        
        function createFilePreview(file, previewContainer) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const content = e.target.result;
                const fileType = file.type;
                
                if (fileType.startsWith('image/')) {
                    // Image preview
                    const img = document.createElement('img');
                    img.src = content;
                    img.className = 'image-preview';
                    previewContainer.appendChild(img);
                } else if (fileType === 'application/pdf') {
                    // PDF preview
                    const embed = document.createElement('embed');
                    embed.src = content;
                    embed.className = 'pdf-preview';
                    embed.type = 'application/pdf';
                    previewContainer.appendChild(embed);
                } else if (fileType.startsWith('text/') || fileType === 'application/json' || fileType === 'application/xml') {
                    // Text preview
                    const pre = document.createElement('pre');
                    pre.className = 'text-xs text-gray-600';
                    pre.textContent = content.substring(0, 500) + (content.length > 500 ? '...' : '');
                    previewContainer.appendChild(pre);
                } else {
                    // Binary file preview
                    const p = document.createElement('p');
                    p.className = 'text-xs text-gray-500';
                    p.textContent = 'Binary file - preview not available';
                    previewContainer.appendChild(p);
                }
            };
            
            reader.onerror = function() {
                const p = document.createElement('p');
                p.className = 'text-xs text-gray-500';
                p.textContent = 'Preview not available';
                previewContainer.appendChild(p);
            };
            
            reader.readAsDataURL(file);
        }
        
        function showFilePreview(file, previewContainer) {
            previewContainer.classList.toggle('hidden');
        }
        
        function getFileIcon(filename) {
            const extension = filename.split('.').pop().toLowerCase();
            const iconMap = {
                'js': 'fab fa-js-square text-yellow-500',
                'jsx': 'fab fa-react text-blue-400',
                'ts': 'fab fa-js-square text-blue-600',
                'tsx': 'fab fa-react text-blue-500',
                'html': 'fab fa-html5 text-orange-500',
                'htm': 'fab fa-html5 text-orange-500',
                'css': 'fab fa-css3-alt text-blue-500',
                'scss': 'fab fa-sass text-pink-500',
                'sass': 'fab fa-sass text-pink-500',
                'json': 'fas fa-code text-gray-500',
                'xml': 'fas fa-code text-orange-400',
                'md': 'fab fa-markdown text-blue-600',
                'markdown': 'fab fa-markdown text-blue-600',
                'txt': 'fas fa-file-alt text-gray-500',
                'pdf': 'fas fa-file-pdf text-red-500',
                'png': 'fas fa-file-image text-green-500',
                'jpg': 'fas fa-file-image text-green-500',
                'jpeg': 'fas fa-file-image text-green-500',
                'gif': 'fas fa-file-image text-green-500',
                'svg': 'fas fa-file-image text-green-500',
                'zip': 'fas fa-file-archive text-yellow-600',
                'rar': 'fas fa-file-archive text-yellow-600',
                'tar': 'fas fa-file-archive text-yellow-600',
                'gz': 'fas fa-file-archive text-yellow-600',
                'php': 'fab fa-php text-purple-500',
                'py': 'fab fa-python text-blue-400',
                'java': 'fab fa-java text-red-500',
                'c': 'fas fa-code text-blue-600',
                'cpp': 'fas fa-code text-blue-500',
                'cs': 'fas fa-code text-purple-600',
                'go': 'fas fa-code text-cyan-500',
                'rs': 'fas fa-code text-orange-600',
                'rb': 'fas fa-gem text-red-600',
                'swift': 'fab fa-swift text-orange-500',
                'kt': 'fas fa-code text-purple-700',
                'sql': 'fas fa-database text-blue-700',
                'sh': 'fas fa-terminal text-gray-600',
                'bash': 'fas fa-terminal text-gray-600',
                'zsh': 'fas fa-terminal text-gray-600',
                'fish': 'fas fa-terminal text-gray-600',
                'yml': 'fas fa-code text-purple-500',
                'yaml': 'fas fa-code text-purple-500',
                'toml': 'fas fa-code text-gray-500',
                'ini': 'fas fa-cog text-gray-500',
                'conf': 'fas fa-cog text-gray-500',
                'log': 'fas fa-file-alt text-gray-500',
                'lock': 'fas fa-lock text-red-500',
                'key': 'fas fa-key text-yellow-500',
                'pem': 'fas fa-key text-yellow-500',
                'crt': 'fas fa-certificate text-green-500',
                'dockerfile': 'fab fa-docker text-blue-500',
                'gitignore': 'fab fa-git-alt text-red-500',
                'gitattributes': 'fab fa-git-alt text-red-500',
                'editorconfig': 'fas fa-cog text-gray-500',
                'eslintrc': 'fas fa-code text-purple-500',
                'prettierrc': 'fas fa-code text-pink-500',
                'babelrc': 'fas fa-code text-yellow-500',
                'tsconfig': 'fas fa-code text-blue-600',
                'package': 'fas fa-box text-red-500',
                'composer': 'fas fa-box text-blue-500',
                'gemfile': 'fas fa-gem text-red-600',
                'pipfile': 'fas fa-box text-blue-400',
                'requirements': 'fas fa-box text-blue-400',
                'makefile': 'fas fa-cog text-gray-500',
                'cmake': 'fas fa-cog text-red-500',
                'gradle': 'fas fa-code text-green-600',
                'maven': 'fas fa-code text-red-600',
                'nuget': 'fas fa-box text-purple-500',
                'cargo': 'fas fa-cog text-orange-600',
                'npm': 'fab fa-npm text-red-500',
                'yarn': 'fab fa-yarn text-blue-500',
                'pnpm': 'fas fa-box text-orange-500',
                'license': 'fas fa-balance-scale text-gray-500',
                'licence': 'fas fa-balance-scale text-gray-500',
                'copying': 'fas fa-balance-scale text-gray-500',
                'authors': 'fas fa-users text-gray-500',
                'contributors': 'fas fa-users text-gray-500',
                'changelog': 'fas fa-history text-gray-500',
                'changes': 'fas fa-history text-gray-500',
                'history': 'fas fa-history text-gray-500',
                'news': 'fas fa-newspaper text-gray-500',
                'readme': 'fas fa-info-circle text-blue-500',
                'install': 'fas fa-download text-green-500',
                'setup': 'fas fa-cogs text-gray-500',
                'config': 'fas fa-cog text-gray-500',
                'configure': 'fas fa-cog text-gray-500',
                'build': 'fas fa-hammer text-gray-500',
                'make': 'fas fa-hammer text-gray-500',
                'test': 'fas fa-vial text-green-500',
                'tests': 'fas fa-vial text-green-500',
                'spec': 'fas fa-vial text-green-500',
                'specs': 'fas fa-vial text-green-500',
                'example': 'fas fa-file-code text-gray-500',
                'examples': 'fas fa-file-code text-gray-500',
                'sample': 'fas fa-file-code text-gray-500',
                'samples': 'fas fa-file-code text-gray-500',
                'demo': 'fas fa-play text-green-500',
                'demos': 'fas fa-play text-green-500',
                'doc': 'fas fa-file-alt text-blue-500',
                'docs': 'fas fa-file-alt text-blue-500',
                'documentation': 'fas fa-file-alt text-blue-500',
                'img': 'fas fa-image text-green-500',
                'imgs': 'fas fa-image text-green-500',
                'image': 'fas fa-image text-green-500',
                'images': 'fas fa-image text-green-500',
                'icon': 'fas fa-image text-green-500',
                'icons': 'fas fa-image text-green-500',
                'asset': 'fas fa-file text-gray-500',
                'assets': 'fas fa-file text-gray-500',
                'resource': 'fas fa-file text-gray-500',
                'resources': 'fas fa-file text-gray-500',
                'lib': 'fas fa-folder text-yellow-600',
                'libs': 'fas fa-folder text-yellow-600',
                'library': 'fas fa-folder text-yellow-600',
                'libraries': 'fas fa-folder text-yellow-600',
                'src': 'fas fa-folder text-blue-500',
                'source': 'fas fa-folder text-blue-500',
                'bin': 'fas fa-folder text-gray-600',
                'obj': 'fas fa-folder text-gray-600',
                'out': 'fas fa-folder text-gray-600',
                'build': 'fas fa-folder text-gray-600',
                'dist': 'fas fa-folder text-gray-600',
                'target': 'fas fa-folder text-gray-600',
                'release': 'fas fa-folder text-green-500',
                'releases': 'fas fa-folder text-green-500',
                'version': 'fas fa-tag text-blue-500',
                'versions': 'fas fa-tags text-blue-500',
                'backup': 'fas fa-archive text-yellow-600',
                'backups': 'fas fa-archive text-yellow-600',
                'tmp': 'fas fa-folder text-gray-500',
                'temp': 'fas fa-folder text-gray-500',
                'cache': 'fas fa-folder text-orange-500',
                'log': 'fas fa-folder text-red-500',
                'logs': 'fas fa-folder text-red-500'
            };
            
            return iconMap[extension] || 'fas fa-file text-gray-500';
        }
        
        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return number_format(bytes / 1073741824, 2) + ' GB';
            } else if (bytes >= 1048576) {
                return number_format(bytes / 1048576, 2) + ' MB';
            } else if (bytes >= 1024) {
                return number_format(bytes / 1024, 2) + ' KB';
            } else if (bytes > 1) {
                return bytes + ' bytes';
            } else if (bytes == 1) {
                return '1 byte';
            } else {
                return '0 bytes';
            }
        }
        
        // Form submission
        const createRepoForm = document.getElementById('createRepoForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnLoader = document.getElementById('btnLoader');
        const errorMessage = document.getElementById('errorMessage');
        const successMessage = document.getElementById('successMessage');
        const cancelBtn = document.getElementById('cancelBtn');
        
        createRepoForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Reset error states
            hideMessages();
            
            // Get form values
            const repoName = document.getElementById('repoName').value.trim();
            const description = document.getElementById('description').value.trim();
            const visibility = document.querySelector('input[name="visibility"]:checked').value;
            const addReadme = document.getElementById('addReadme').checked;
            const addGitignore = document.getElementById('addGitignore').checked;
            const license = document.getElementById('license').value;
            
            // Validate form
            let isValid = true;
            
            if (!repoName) {
                showError('Repository name is required');
                isValid = false;
            }
            
            if (!isValid) {
                return;
            }
            
            // Show loading state
            setLoading(true);
            
            // Create FormData for file upload
            const formData = new FormData();
            formData.append('repoName', repoName);
            formData.append('description', description);
            formData.append('visibility', visibility);
            formData.append('addReadme', addReadme);
            formData.append('addGitignore', addGitignore);
            formData.append('license', license);
            
            // Add files if any
            if (uploadedFiles.length > 0) {
                for (let i = 0; i < uploadedFiles.length; i++) {
                    formData.append('files[]', uploadedFiles[i]);
                }
            }
            
            // Send data to backend
            try {
                const response = await fetch('backend/create_repository.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    setLoading(false);
                    showSuccess('Repository created successfully! Redirecting...');
                    
                    // Redirect to repository page
                    setTimeout(() => {
                        window.location.href = 'repository.php?id=' + data.repoId;
                    }, 2000);
                } else {
                    setLoading(false);
                    showError(data.message || 'Failed to create repository');
                }
            } catch (error) {
                setLoading(false);
                showError('An error occurred. Please try again.');
                console.error('Create repository error:', error);
            }
        });
        
        cancelBtn.addEventListener('click', function() {
            window.location.href = 'dashboard.php';
        });
        
        // Helper functions
        function setLoading(isLoading) {
            if (isLoading) {
                submitBtn.disabled = true;
                btnText.textContent = 'Creating repository...';
                btnLoader.classList.remove('hidden');
            } else {
                submitBtn.disabled = false;
                btnText.textContent = 'Create repository';
                btnLoader.classList.add('hidden');
            }
        }
        
        function showError(message) {
            document.getElementById('errorText').textContent = message;
            errorMessage.classList.remove('hidden');
            successMessage.classList.add('hidden');
        }
        
        function showSuccess(message) {
            document.getElementById('successText').textContent = message;
            successMessage.classList.remove('hidden');
            errorMessage.classList.add('hidden');
        }
        
        function hideMessages() {
            errorMessage.classList.add('hidden');
            successMessage.classList.add('hidden');
        }
    </script>
</body>
</html>