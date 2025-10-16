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
$sql = "SELECT id, username, email FROM users WHERE id = ?";
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

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $repo_name = trim($_POST['repository_name']);
    $description = trim($_POST['description']);
    $visibility = $_POST['visibility'];
    $add_readme = isset($_POST['add_readme']);
    $add_gitignore = isset($_POST['add_gitignore']);
    $choose_license = $_POST['license'];
    
    // Validate repository name
    if (empty($repo_name)) {
        $error = "Repository name is required.";
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $repo_name)) {
        $error = "Repository name can only contain letters, numbers, hyphens, underscores, and periods.";
    } else {
        // Check if repository already exists for this user
        $check_sql = "SELECT id FROM repositories WHERE owner_username = ? AND name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $username, $repo_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "You already have a repository with that name.";
        } else {
            // Create repository directory
            $base_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/projects';
            $user_dir = $base_path . '/' . $username;
            $repo_dir = $user_dir . '/' . $repo_name;
            
            // Create directories if they don't exist
            if (!is_dir($user_dir)) {
                mkdir($user_dir, 0755, true);
            }
            
            if (!is_dir($repo_dir)) {
                if (mkdir($repo_dir, 0755, true)) {
                    // Insert repository into database
                    $insert_sql = "INSERT INTO repositories (name, path, description, owner_id, owner_username, visibility, default_branch) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $repo_path = 'projects/' . $username . '/' . $repo_name;
                    $default_branch = 'main';
                    
                    $insert_stmt->bind_param("sssisss", $repo_name, $repo_path, $description, $user_id, $username, $visibility, $default_branch);
                    
                    if ($insert_stmt->execute()) {
                        $repo_id = $conn->insert_id;
                        
                        // Create README.md if selected
                        if ($add_readme) {
                            $readme_content = "# " . $repo_name . "\n\n" . $description;
                            file_put_contents($repo_dir . '/README.md', $readme_content);
                        }
                        
                        // Create .gitignore if selected
                        if ($add_gitignore) {
                            $gitignore_content = "# Logs\nlogs\n*.log\nnpm-debug.log*\nyarn-debug.log*\nyarn-error.log*\n\n# Dependencies\nnode_modules/\n\n# Production builds\nbuild/\ndist/\n\n# Environment variables\n.env\n.env.local\n.env.development.local\n.env.test.local\n.env.production.local";
                            file_put_contents($repo_dir . '/.gitignore', $gitignore_content);
                        }
                        
                        // Create LICENSE file if selected
                        if ($choose_license !== 'none') {
                            $license_content = getLicenseContent($choose_license, $username);
                            file_put_contents($repo_dir . '/LICENSE', $license_content);
                        }
                        
                        // Initialize git repository
                        chdir($repo_dir);
                        exec('git init', $output, $return_var);
                        exec('git add .', $output, $return_var);
                        exec('git config user.email "' . $user['email'] . '"', $output, $return_var);
                        exec('git config user.name "' . $username . '"', $output, $return_var);
                        exec('git commit -m "Initial commit"', $output, $return_var);
                        
                        $success = "Repository created successfully!";
                        
                        // Redirect to repository page after 2 seconds
                        header("refresh:2;url=repository.php?id=" . $repo_id);
                    } else {
                        $error = "Failed to create repository in database.";
                        // Clean up directory if database insert failed
                        rmdir($repo_dir);
                    }
                } else {
                    $error = "Failed to create repository directory.";
                }
            } else {
                $error = "Repository directory already exists.";
            }
        }
    }
}

// Function to get license content
function getLicenseContent($license, $username) {
    $year = date('Y');
    
    switch ($license) {
        case 'mit':
            return "MIT License

Copyright (c) $year $username

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the \"Software\"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED \"AS IS\", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.";
        
        case 'apache':
            return "Apache License
Version 2.0, January 2004
http://www.apache.org/licenses/

TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION

1. Definitions.

[Full Apache 2.0 license text would go here - shortened for example]
Copyright $year $username";
        
        case 'gpl':
            return "GNU GENERAL PUBLIC LICENSE
Version 3, 29 June 2007

Copyright (C) 2007 Free Software Foundation, Inc. <https://fsf.org/>
Everyone is permitted to copy and distribute verbatim copies
of this license document, but changing it is not allowed.

[Full GPL v3 license text would go here - shortened for example]
Copyright (C) $year $username";
        
        default:
            return "";
    }
}
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
        
        .form-section {
            transition: all 0.3s ease;
        }
        
        .form-section:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .visibility-option {
            transition: all 0.2s ease;
        }
        
        .visibility-option:hover {
            transform: translateY(-1px);
        }
        
        .license-option {
            transition: all 0.2s ease;
        }
        
        .license-option:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
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
                        <span class="ml-2 text-xl font-semibold">CodeHub APS</span>
                    </div>
                </div>
                
                <!-- Right Section -->
                <div class="flex items-center space-x-4">
                    <!-- Profile -->
                    <div class="relative group">
                        <button class="flex items-center space-x-2">
                            <img src="https://picsum.photos/seed/user<?php echo $user['id']; ?>/32/32.jpg" alt="Profile" class="h-8 w-8 rounded-full">
                            <i class="fas fa-chevron-down text-xs text-gray-600"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Create a new repository</h1>
            <p class="text-gray-600 mt-2">A repository contains all project files, including the revision history.</p>
        </div>

        <!-- Error/Success Messages -->
        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700"><?php echo $error; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700"><?php echo $success; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <!-- Repository Template Section -->
            <div class="bg-white rounded-lg border border-gray-200 p-6 mb-6 form-section">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Repository template</h2>
                <p class="text-gray-600 mb-4">Start your repository with a template to skip the initial setup.</p>
                <button type="button" class="w-full text-left p-4 border border-gray-200 rounded-lg hover:border-blue-500 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-900">No template</p>
                            <p class="text-sm text-gray-600">Start with an empty repository</p>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </div>
                </button>
            </div>

            <!-- Repository Details Section -->
            <div class="bg-white rounded-lg border border-gray-200 p-6 mb-6 form-section">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Repository details</h2>
                
                <!-- Owner and Repository Name -->
                <div class="mb-4">
                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden bg-gray-50">
                        <div class="px-3 py-2 bg-gray-100 border-r border-gray-300">
                            <span class="text-sm font-medium text-gray-700"><?php echo $username; ?></span>
                            <span class="text-gray-500">/</span>
                        </div>
                        <input 
                            type="text" 
                            name="repository_name" 
                            placeholder="Repository name" 
                            class="flex-1 px-3 py-2 border-0 focus:ring-0 focus:outline-none bg-white"
                            value="<?php echo isset($_POST['repository_name']) ? htmlspecialchars($_POST['repository_name']) : ''; ?>"
                            required
                        >
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Great repository names are short and memorable.</p>
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <textarea 
                        name="description" 
                        placeholder="Description (optional)" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        rows="3"
                    ><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>

                <!-- Visibility Options -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <label class="visibility-option cursor-pointer">
                        <input type="radio" name="visibility" value="public" class="sr-only" checked>
                        <div class="p-4 border-2 border-gray-200 rounded-lg hover:border-blue-500">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-book mr-2 text-gray-600"></i>
                                <span class="font-medium text-gray-900">Public</span>
                            </div>
                            <p class="text-sm text-gray-600">Anyone on the internet can see this repository.</p>
                        </div>
                    </label>
                    
                    <label class="visibility-option cursor-pointer">
                        <input type="radio" name="visibility" value="private" class="sr-only">
                        <div class="p-4 border-2 border-gray-200 rounded-lg hover:border-blue-500">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-lock mr-2 text-gray-600"></i>
                                <span class="font-medium text-gray-900">Private</span>
                            </div>
                            <p class="text-sm text-gray-600">You choose who can see and commit to this repository.</p>
                        </div>
                    </label>
                </div>

                <!-- Initialize Repository Section -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Initialize this repository with:</h3>
                    
                    <div class="space-y-3">
                        <label class="flex items-center">
                            <input type="checkbox" name="add_readme" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-gray-700">Add a README file</span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" name="add_gitignore" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-gray-700">Add .gitignore</span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" id="choose_license_toggle" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-gray-700">Choose a license</span>
                        </label>
                        
                        <!-- License Options (Hidden by default) -->
                        <div id="license_options" class="ml-6 mt-2 hidden">
                            <select name="license" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="none">None</option>
                                <option value="mit">MIT License</option>
                                <option value="apache">Apache License 2.0</option>
                                <option value="gpl">GNU General Public License v3.0</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">A license tells others what they can and can't do with your code.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Repository Button -->
            <div class="flex justify-end">
                <button 
                    type="submit" 
                    class="px-6 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors"
                >
                    Create repository
                </button>
            </div>
        </form>
    </div>

    <script>
        // Toggle license options
        const licenseToggle = document.getElementById('choose_license_toggle');
        const licenseOptions = document.getElementById('license_options');
        
        licenseToggle.addEventListener('change', function() {
            if (this.checked) {
                licenseOptions.classList.remove('hidden');
            } else {
                licenseOptions.classList.add('hidden');
            }
        });

        // Add active state to visibility options
        const visibilityOptions = document.querySelectorAll('.visibility-option');
        visibilityOptions.forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            const container = option.querySelector('div');
            
            radio.addEventListener('change', function() {
                // Remove active state from all options
                visibilityOptions.forEach(opt => {
                    opt.querySelector('div').classList.remove('border-blue-500', 'bg-blue-50');
                });
                
                // Add active state to selected option
                if (this.checked) {
                    container.classList.add('border-blue-500', 'bg-blue-50');
                }
            });
            
            // Set initial state
            if (radio.checked) {
                container.classList.add('border-blue-500', 'bg-blue-50');
            }
        });
    </script>
</body>
</html>