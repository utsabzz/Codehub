<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub File Explorer</title>
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

        .file-message {
            font-size: 12px;
            color: var(--github-text-secondary);
            margin-right: 16px;
        }

        .file-time {
            font-size: 12px;
            color: var(--github-text-secondary;
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
                    <span class="repo-name" id="repo-name">file-explorer</span>
                    <span class="repo-visibility" id="repo-visibility">Public</span>
                </div>
                <div class="repo-actions">
                    <button class="btn" id="branch-btn">
                        <i class="fas fa-code-branch"></i> <span id="branch-name">main</span>
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
                    <span class="breadcrumb-item" data-path="">file-explorer</span>
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

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <i class="fas fa-check-circle" id="toast-icon"></i>
        <span id="toast-message">Operation completed successfully</span>
    </div>

    <script>
        // Database structure integration
        const projectData = {
            id: 1,
            name: "file-explorer",
            path: "projects/abhirup/PrivateRepo",
            description: "A GitHub-style file explorer implementation",
            owner_id: 1,
            owner_username: "abhirup",
            visibility: "public",
            language: "JavaScript",
            stars: 15,
            forks: 3,
            views: 127,
            default_branch: "main",
            forked_from: null,
            created_at: "2023-06-15 10:30:00",
            updated_at: "2023-10-20 14:45:00"
        };

        // File system structure
        let fileSystem = {
            "README.md": { type: "file", content: "# File Explorer\n\nA GitHub-style file explorer implementation.", lastModified: "2023-10-20 14:45:00" },
            "src": {
                type: "folder",
                children: {
                    "index.js": { type: "file", content: "console.log('Hello World');", lastModified: "2023-10-19 11:20:00" },
                    "styles.css": { type: "file", content: "body { margin: 0; }", lastModified: "2023-10-18 09:15:00" },
                    "components": {
                        type: "folder",
                        children: {
                            "FileItem.js": { type: "file", content: "// File item component", lastModified: "2023-10-17 16:30:00" },
                            "Modal.js": { type: "file", content: "// Modal component", lastModified: "2023-10-16 13:45:00" }
                        },
                        lastModified: "2023-10-17 16:30:00"
                    }
                },
                lastModified: "2023-10-19 11:20:00"
            },
            "package.json": { type: "file", content: '{\n  "name": "file-explorer",\n  "version": "1.0.0"\n}', lastModified: "2023-10-15 12:10:00" },
            ".gitignore": { type: "file", content: "node_modules/\n.env", lastModified: "2023-10-14 08:05:00" },
            "docs": {
                type: "folder",
                children: {
                    "README.md": { type: "file", content: "# Documentation", lastModified: "2023-10-13 15:25:00" },
                    "api.md": { type: "file", content: "# API Reference", lastModified: "2023-10-12 10:40:00" }
                },
                lastModified: "2023-10-13 15:25:00"
            },
            "LICENSE": { type: "file", content: "MIT License", lastModified: "2023-10-10 14:20:00" }
        };

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
        const addFolderModal = document.getElementById('add-folder-modal');
        const uploadModal = document.getElementById('upload-modal');
        const closeAddFileModal = document.getElementById('close-add-file-modal');
        const closeAddFolderModal = document.getElementById('close-add-folder-modal');
        const closeUploadModal = document.getElementById('close-upload-modal');
        const cancelAddFile = document.getElementById('cancel-add-file');
        const cancelAddFolder = document.getElementById('cancel-add-folder');
        const cancelUpload = document.getElementById('cancel-upload');
        const createNewFile = document.getElementById('create-new-file');
        const createNewFolder = document.getElementById('create-new-folder');
        const uploadFiles = document.getElementById('upload-files');
        const fileDropArea = document.getElementById('file-drop-area');
        const fileInput = document.getElementById('file-input');
        const browseFiles = document.getElementById('browse-files');
        const fileListPreview = document.getElementById('file-list-preview');
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-message');
        const toastIcon = document.getElementById('toast-icon');
        const uploadText = document.getElementById('upload-text');
        const uploadSpinner = document.getElementById('upload-spinner');

        // Current path and state
        let currentPath = [];
        let selectedFiles = [];

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            initializeProject();
            renderFiles(getCurrentDirectory());
            setupEventListeners();
        });

        // Initialize project data
        function initializeProject() {
            repoName.textContent = projectData.name;
            repoVisibility.textContent = projectData.visibility.charAt(0).toUpperCase() + projectData.visibility.slice(1);
            branchName.textContent = projectData.default_branch;
            
            // Update breadcrumb
            updateBreadcrumb();
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

            closeAddFolderModal.addEventListener('click', () => {
                addFolderModal.classList.remove('active');
            });

            closeUploadModal.addEventListener('click', () => {
                uploadModal.classList.remove('active');
                resetFileUpload();
            });

            cancelAddFile.addEventListener('click', () => {
                addFileModal.classList.remove('active');
            });

            cancelAddFolder.addEventListener('click', () => {
                addFolderModal.classList.remove('active');
            });

            cancelUpload.addEventListener('click', () => {
                uploadModal.classList.remove('active');
                resetFileUpload();
            });

            // Create new file
            createNewFile.addEventListener('click', () => {
                const fileName = document.getElementById('new-file-name').value;
                const fileContent = document.getElementById('new-file-content').value;
                
                if (!fileName) {
                    showToast('Please enter a file name', 'error');
                    return;
                }
                
                // Add file to current directory
                addFileToCurrentDirectory(fileName, fileContent);
                
                // Reset form and close modal
                document.getElementById('new-file-name').value = '';
                document.getElementById('new-file-content').value = '';
                addFileModal.classList.remove('active');
                
                showToast(`File "${fileName}" created successfully`, 'success');
            });

            // Create new folder
            createNewFolder.addEventListener('click', () => {
                const folderName = document.getElementById('new-folder-name').value;
                
                if (!folderName) {
                    showToast('Please enter a folder name', 'error');
                    return;
                }
                
                // Add folder to current directory
                addFolderToCurrentDirectory(folderName);
                
                // Reset form and close modal
                document.getElementById('new-folder-name').value = '';
                addFolderModal.classList.remove('active');
                
                showToast(`Folder "${folderName}" created successfully`, 'success');
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
            uploadFiles.addEventListener('click', () => {
                if (selectedFiles.length === 0) {
                    showToast('Please select files to upload', 'error');
                    return;
                }
                
                // Show loading state
                uploadText.textContent = 'Uploading...';
                uploadSpinner.style.display = 'inline-block';
                
                // Simulate upload
                setTimeout(() => {
                    // Add files to current directory
                    selectedFiles.forEach(file => {
                        addFileToCurrentDirectory(file.name, 'Uploaded file content');
                    });
                    
                    // Reset and close modal
                    resetFileUpload();
                    uploadModal.classList.remove('active');
                    
                    showToast(`${selectedFiles.length} file(s) uploaded successfully`, 'success');
                }, 1500);
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

        // Get current directory based on path
        function getCurrentDirectory() {
            let currentDir = fileSystem;
            
            for (const segment of currentPath) {
                if (currentDir[segment] && currentDir[segment].type === 'folder') {
                    currentDir = currentDir[segment].children;
                } else {
                    // Path doesn't exist, reset to root
                    currentPath = [];
                    return fileSystem;
                }
            }
            
            return currentDir;
        }

        // Render files in current directory
        function renderFiles(directory) {
            const files = Object.keys(directory)
                .map(name => {
                    const item = directory[name];
                    return {
                        name,
                        type: item.type,
                        icon: item.type === 'folder' ? 'folder' : 
                              name.endsWith('.md') ? 'markdown' : 'file',
                        lastModified: item.lastModified || 'Unknown'
                    };
                })
                .sort((a, b) => {
                    // Folders first, then files
                    if (a.type === 'folder' && b.type !== 'folder') return -1;
                    if (a.type !== 'folder' && b.type === 'folder') return 1;
                    // Alphabetical order
                    return a.name.localeCompare(b.name);
                });
            
            if (files.length === 0) {
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
                <div class="file-item" data-name="${file.name}" data-type="${file.type}">
                    <i class="fas ${file.type === 'folder' ? 'fa-folder' : file.icon === 'markdown' ? 'fa-file-alt' : 'fa-file'} file-icon ${file.icon}"></i>
                    <span class="file-name">${file.name}</span>
                    <span class="file-message">Latest commit</span>
                    <span class="file-time">${formatTimeAgo(file.lastModified)}</span>
                </div>
            `).join('');
            
            // Add click handlers to file items
            document.querySelectorAll('.file-item').forEach(item => {
                item.addEventListener('click', () => {
                    const name = item.dataset.name;
                    const type = item.dataset.type;
                    
                    if (type === 'folder') {
                        // Navigate to folder
                        navigateToFolder(name);
                    } else {
                        // View file
                        viewFile(name);
                    }
                });
            });
        }

        // Navigate to a folder
        function navigateToFolder(folderName) {
            currentPath.push(folderName);
            updateBreadcrumb();
            renderFiles(getCurrentDirectory());
        }

        // View a file
        function viewFile(fileName) {
            const currentDir = getCurrentDirectory();
            const file = currentDir[fileName];
            
            if (file && file.type === 'file') {
                showToast(`Viewing ${fileName}: ${file.content.substring(0, 50)}${file.content.length > 50 ? '...' : ''}`, 'info');
            } else {
                showToast(`Cannot view ${fileName}`, 'error');
            }
        }

        // Update breadcrumb based on current path
        function updateBreadcrumb() {
            breadcrumb.innerHTML = '';
            
            // Add root
            const rootItem = document.createElement('span');
            rootItem.className = 'breadcrumb-item';
            rootItem.textContent = projectData.name;
            rootItem.dataset.path = '';
            rootItem.addEventListener('click', () => {
                currentPath = [];
                updateBreadcrumb();
                renderFiles(getCurrentDirectory());
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
                        renderFiles(getCurrentDirectory());
                    };
                })(i));
                
                breadcrumb.appendChild(pathItem);
            }
        }

        // Add file to current directory
        function addFileToCurrentDirectory(fileName, content) {
            const currentDir = getCurrentDirectory();
            
            // Check if file already exists
            if (currentDir[fileName]) {
                showToast(`File "${fileName}" already exists`, 'error');
                return;
            }
            
            currentDir[fileName] = {
                type: 'file',
                content: content,
                lastModified: new Date().toISOString().replace('T', ' ').substring(0, 19)
            };
            
            renderFiles(getCurrentDirectory());
        }

        // Add folder to current directory
        function addFolderToCurrentDirectory(folderName) {
            const currentDir = getCurrentDirectory();
            
            // Check if folder already exists
            if (currentDir[folderName]) {
                showToast(`Folder "${folderName}" already exists`, 'error');
                return;
            }
            
            currentDir[folderName] = {
                type: 'folder',
                children: {},
                lastModified: new Date().toISOString().replace('T', ' ').substring(0, 19)
            };
            
            renderFiles(getCurrentDirectory());
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
                    <i class="fas ${file.name.endsWith('.md') ? 'fa-file-alt' : 'fa-file'} file-preview-icon"></i>
                    <span class="file-preview-name">${file.name}</span>
                    <span class="file-preview-size">${formatFileSize(file.size)}</span>
                </div>
            `).join('');
        }

        // Reset file upload
        function resetFileUpload() {
            selectedFiles = [];
            fileInput.value = '';
            fileListPreview.innerHTML = '';
            uploadText.textContent = 'Upload files';
            uploadSpinner.style.display = 'none';
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