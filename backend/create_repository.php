<?php
// Set response headers
header('Content-Type: application/json');

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response = [
        'success' => false,
        'message' => 'User not logged in'
    ];
    echo json_encode($response);
    exit;
}

// Include database connection
require_once '../db_connection.php';

// Initialize response array
 $response = [
    'success' => false,
    'message' => '',
    'repoId' => null
];

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get user information
 $user_id = $_SESSION['user_id'];
 $sql = "SELECT username FROM users WHERE id = ?";
 $stmt = $conn->prepare($sql);
 $stmt->bind_param("i", $user_id);
 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows === 0) {
    $response['message'] = 'User not found';
    echo json_encode($response);
    exit;
}

 $user = $result->fetch_assoc();
 $username = $user['username'];

// Get form data
 $repoName = trim($_POST['repoName']);
 $description = trim($_POST['description']);
 $visibility = $_POST['visibility'];
 $addReadme = isset($_POST['addReadme']) ? true : false;
 $addGitignore = isset($_POST['addGitignore']) ? true : false;
 $license = $_POST['license'];

// Validate repository name
if (empty($repoName)) {
    $response['message'] = 'Repository name is required';
    echo json_encode($response);
    exit;
}

// Check if repository name is valid (basic validation)
if (!preg_match('/^[a-zA-Z0-9._-]+$/', $repoName)) {
    $response['message'] = 'Repository name can only contain alphanumeric characters, hyphens, underscores, and periods';
    echo json_encode($response);
    exit;
}

// Check if repository already exists for this user
 $sql = "SELECT id FROM repositories WHERE name = ? AND owner_username = ?";
 $stmt = $conn->prepare($sql);
 $stmt->bind_param("ss", $repoName, $username);
 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows > 0) {
    $response['message'] = 'A repository with this name already exists';
    echo json_encode($response);
    exit;
}

// Create repository path
 $repoPath = 'codehub/projects/' . $username . '/' . $repoPath;
 $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $repoPath;

// Create directory if it doesn't exist
if (!is_dir($fullPath)) {
    if (!mkdir($fullPath, 0755, true)) {
        $response['message'] = 'Failed to create repository directory';
        echo json_encode($response);
        exit;
    }
}

// Insert repository into database
 $sql = "INSERT INTO repositories (name, description, owner_id, owner_username, path, visibility, default_branch, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'main', 'active', NOW())";
 $stmt = $conn->prepare($sql);
 $stmt->bind_param("ssissss", 
    $repoName, 
    $description, 
    $user_id, 
    $username, 
    $repoPath, 
    $visibility
);

if (!$stmt->execute()) {
    $response['message'] = 'Failed to create repository in database';
    echo json_encode($response);
    exit;
}

 $repoId = $conn->insert_id;

// Create README.md if requested
if ($addReadme) {
    $readmeContent = "# " . $repoName . "\n\n";
    if (!empty($description)) {
        $readmeContent .= $description . "\n\n";
    }
    $readmeContent .= "## Installation\n\n```bash\n# Add installation instructions here\n```\n\n## Usage\n\n```bash\n# Add usage instructions here\n```\n\n## Contributing\n\nPull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.\n\nPlease make sure to update tests as appropriate.\n\n## License\n\n";
    
    if (!empty($license)) {
        $licenseText = getLicenseText($license);
        $readmeContent .= "This project is licensed under the " . $licenseText . ".\n";
    } else {
        $readmeContent .= "[Click here](https://choosealicense.com) to choose a license.\n";
    }
    
    file_put_contents($fullPath . '/README.md', $readmeContent);
}

// Create .gitignore if requested
if ($addGitignore) {
    $gitignoreContent = "# Dependencies\n/node_modules\n/.pnp\n.pnp.js\n\n# Testing\n/coverage\n\n# Production\n/build\n/dist\n\n# Misc\n.DS_Store\n.env.local\n.env.development.local\n.env.test.local\n.env.production.local\n\nnpm-debug.log*\nyarn-debug.log*\nyarn-error.log*\n";
    
    file_put_contents($fullPath . '/.gitignore', $gitignoreContent);
}

// Create LICENSE file if a license was selected
if (!empty($license)) {
    $licenseContent = getLicenseContent($license);
    if ($licenseContent) {
        file_put_contents($fullPath . '/LICENSE', $licenseContent);
    }
}

// Handle file uploads
if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
    $files = $_FILES['files'];
    $fileCount = count($files['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $tmpName = $files['tmp_name'][$i];
            $fileName = $files['name'][$i];
            
            // Create directory structure if needed
            $targetPath = $fullPath . '/' . $fileName;
            $targetDir = dirname($targetPath);
            
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            // Move file to repository directory
            if (!move_uploaded_file($tmpName, $targetPath)) {
                // Log error but continue with other files
                error_log("Failed to upload file: " . $fileName);
            }
        }
    }
}

// Close connection
 $conn->close();

// Return success response
 $response['success'] = true;
 $response['message'] = 'Repository created successfully';
 $response['repoId'] = $repoId;

echo json_encode($response);

// Helper functions
function getLicenseText($license) {
    $licenses = [
        'mit' => 'MIT License',
        'apache-2.0' => 'Apache License 2.0',
        'gpl-3.0' => 'GNU General Public License v3.0',
        'bsd-3-clause' => 'BSD 3-Clause License',
        'bsd-2-clause' => 'BSD 2-Clause License',
        'isc' => 'ISC License',
        'lgpl-3.0' => 'GNU Lesser General Public License v3.0',
        'mpl-2.0' => 'Mozilla Public License 2.0',
        'unlicense' => 'The Unlicense'
    ];
    
    return $licenses[$license] ?? 'Unknown License';
}

function getLicenseContent($license) {
    $licenseContents = [
        'mit' => "MIT License\n\nCopyright (c) " . date('Y') . "\n\nPermission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the \"Software\"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:\n\na. You must give any other recipients of the Work or Derivative Works a copy of this License; and\nb. You must cause any modified files to carry prominent notices stating that You changed the files; and\nc. You must retain, in the Source form of any Derivative Works that You distribute, all copyright, trademark, patent, attribution, and other notices from the Source form of the Work, excluding those notices that do not pertain to any part of the Derivative Works themselves, in at least one of the following places: within a NOTICE text file distributed as part of the Derivative Works; within the Source form or documentation, if provided along with the Derivative Works; or, within a display generated by the Derivative Works, if and wherever such third-party notices normally appear. The contents of the NOTICE file are for informational purposes only and do not modify the License. You may add Your own attribution notices within Derivative Works that You distribute, alongside or as an addendum to the NOTICE text from the Work, provided that such additional attribution notices cannot be construed as modifying the License. You may add Your own copyright notice to Your modifications and may provide additional or different license terms and conditions for use, reproduction, or distribution of Your modifications, or for any such Derivative Works as a whole, provided Your use, reproduction, and distribution of the Work otherwise complies with the conditions stated in this License.\n\ne. The Work may include a \"NOTICE\" text file that contains attribution notices provided by Licensor. You may not remove any of these notices from the Work.\n\ne. The Work may include a \"NOTICE\" text file that contains attribution notices provided by Licensor. You may not remove any of these notices from the Work.\n\ne. The Work may include a \"NOTICE\" text file that contains attribution notices provided by Licensor. You may add Your own attribution notices within Derivative Works that You distribute, alongside or as an addendum to the NOTICE text from the Work, provided that such additional attribution notices cannot be construed as modifying the License. You may add Your own copyright notice to Your modifications and may provide additional or different license terms and conditions for use, reproduction, or distribution of Your modifications, or for any such Derivative Works as a whole, provided Your use, reproduction, and distribution of the Work otherwise complies with the conditions stated in this License.\n\ne. The Work may include a \"NOTICE\" text file that contains attribution notices provided by Licensor. You may not remove any of these notices from the Work.\n\nEND OF TERMS AND CONDITIONS\n\nCopyright " . date('Y') . " " . $username . "\n\nLicensed under the Apache License, Version 2.0 (the \"License\"); you may not use this file except in compliance with the License. You may obtain a copy of the License at\n\nhttp://www.apache.org/licenses/LICENSE-2.0\n\nUnless required by applicable law or agreed to in writing, software distributed under the License is distributed on an \"AS IS\" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied, See the License for the specific language governing permissions and limitations under the License.\n\nEND OF TERMS AND CONDITIONS\n\nCopyright " . date('Y') . " " . $username . "\n\nLicensed under the Apache License, Version 2.0 (the \"License\"); you may not use this file except in compliance with the License. You may obtain a copy of the License at\n\nhttp://www.apache.org/licenses/LICENSE-2.0\n\nUnless required by applicable law or agreed to in writing, Licensor provides the Work (and each Contributor provides its Contributions) ON AN \"AS IS\" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied, including, without limitation, any warranties or conditions of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, unless required by applicable law (such as deliberate and grossly negligent acts) or agreed to in writing, shall any Contributor be liable to You for damages, including any direct, indirect, special, incidental, or consequential damages of any character arising as a result of this License or out of the use or inability to use the Work (including but not limited to damages for loss of goodwill, work stoppage, computer failure or malfunction, or any and all other commercial damages or losses), even if such Contributor has been advised of the possibility of such damages."
    ];
    
    return $licenseContents[$license] ?? '';
}
?>