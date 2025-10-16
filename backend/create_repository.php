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
 $repoName = $conn->real_escape_string(trim($_POST['repoName']));
 $description = $conn->real_escape_string(trim($_POST['description']));
 $visibility = $conn->real_escape_string($_POST['visibility']);
 $addReadme = isset($_POST['addReadme']) ? $_POST['addReadme'] : false;
 $addGitignore = isset($_POST['addGitignore']) ? $_POST['addGitignore'] : false;
 $license = $conn->real_escape_string($_POST['license']);

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
 $repoPath = 'codehub/projects/' . $username . '/' . $repoName;
 $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $repoPath;

// Create directory if it doesn't exist
if (!is_dir($fullPath)) {
    if (!mkdir($fullPath, 0755, true)) {
        $response['message'] = 'Failed to create repository directory';
        echo json_encode($response);
        exit;
    }
}

// Initialize Git repository (optional)
 $gitInitialized = false;
if (is_dir($fullPath . '/.git')) {
    $gitInitialized = true;
} else {
    // Try to initialize Git repository
    $gitCommand = 'cd ' . escapeshellarg($fullPath) . ' && git init';
    $gitInitialized = shell_exec($gitCommand) !== null;
}

// Insert repository into database
 $sql = "INSERT INTO repositories (name, description, owner_id, owner_username, path, visibility, default_branch, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'main', 'active', NOW())";
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
        'mit' => "MIT License\n\nCopyright (c) " . date('Y') . "\n\nPermission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the \"Software\"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:\n\nThe above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.\n\nTHE SOFTWARE IS PROVIDED \"AS IS\", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.",
        
        'apache-2.0' => "Apache License\nVersion 2.0, January 2004\nhttp://www.apache.org/licenses/\n\nTERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION\n\n1. Definitions.\n\n\"License\" shall mean the terms and conditions for use, reproduction, and distribution as defined by Sections 1 through 9 of this document.\n\n\"Licensor\" shall mean the copyright owner or entity authorized by the copyright owner that is granting the License.\n\n\"Legal Entity\" shall mean the union of the acting entity and all other entities that control, are controlled by, or are under common control with that entity. For the purposes of this definition, \"control\" means (i) the power, direct or indirect, to cause the direction or management of such entity, whether by contract or otherwise, or (ii) ownership of fifty percent (50%) or more of the outstanding shares, or (iii) beneficial ownership of such entity.\n\n\"You\" (or \"Your\") shall mean an individual or Legal Entity exercising permissions granted by this License.\n\n\"Source\" form shall mean the preferred form for making modifications, including but not limited to software source code, documentation source, and configuration files.\n\n\"Object\" form shall mean any form resulting from mechanical transformation or translation of a Source form, including but not limited to compiled object code, generated documentation, and conversions to other media types.\n\n\"Work\" shall mean the work of authorship, whether in Source or Object form, made available under the License, as indicated by a copyright notice that is included in or attached to the work (which shall not include communications that are conspicuously marked or otherwise designated in writing by the copyright owner as \"Not a Work\").\n\n\"Derivative Works\" shall mean any work, whether in Source or Object form, that is based upon (or derived from) the Work and for which the editorial revisions, annotations, elaborations, or other modifications represent, as a whole, an original work of authorship. For the purposes of this License, Derivative Works shall not include works that remain separable from, or merely link (or bind by name) to the interfaces of, the Work and derivative works thereof.\n\n\"Contribution\" shall mean any work of authorship, including the original version of the Work and any modifications or additions to that Work or Derivative Works thereof, that is intentionally submitted to Licensor for inclusion in the Work by the copyright owner or by an individual or Legal Entity authorized to submit on behalf of the copyright owner. For the purposes of this definition, \"submitted\" means any form of electronic, verbal, or written communication sent to the Licensor or its representatives, including but not limited to communication on electronic mailing lists, source code control systems, and issue tracking systems that are managed by, or on behalf of, the Licensor for the purpose of discussing and improving the Work, but excluding communication that is conspicuously marked or otherwise designated in writing by the copyright owner as \"Not a Contribution.\"\n\n\"Contributor\" shall mean Licensor and any individual or Legal Entity on behalf of whom a Contribution has been received by Licensor and subsequently incorporated within the Work.\n\n2. Grant of Copyright License. Subject to the terms and conditions of this License, each Contributor hereby grants to You a perpetual, worldwide, non-exclusive, no-charge, royalty-free, irrevocable copyright license to use, reproduce, modify, merge, publish, distribute, sublicense, and/or sell copies of the Work, and to permit persons to whom the Work is furnished to do so, subject to the following conditions:\n\na. You must give any other recipients of the Work or Derivative Works a copy of this License; and\nb. You must cause any modified files to carry prominent notices stating that You changed the files; and\nc. You must retain, in the Source form of any Derivative Works that You distribute, all copyright, trademark, patent, attribution, and other notices from the Source form of the Work, excluding those notices that do not pertain to any part of the Derivative Works; and\nd. If the Work includes a \"NOTICE\" text file as part of its distribution, then any Derivative Works that You distribute must include a readable copy of the attribution notices contained within such NOTICE file, excluding those notices that do not pertain to any part of the Derivative Works themselves, in at least one of the following places: within a NOTICE text file distributed as part of the Derivative Works; within the Source form or documentation, if provided along with the Derivative Works; or, within a display generated by the Derivative Works, if and wherever such third-party notices normally appear. The contents of the NOTICE file are for informational purposes only and do not modify the License. You may add Your own attribution notices within Derivative Works that You distribute, alongside or as an addendum to the NOTICE text from the Work, provided that such additional attribution notices cannot be construed as modifying the License. You may add Your own copyright notice to Your modifications and may provide additional or different license terms and conditions for use, reproduction, or distribution of Your modifications, or for any such Derivative Works as a whole, provided Your use, reproduction, and distribution of the Work otherwise complies with the conditions stated in this License.\n\ne. The Work may include a NOTICE text file that contains attribution notices provided by Licensor. You may not remove any of these notices from the Work.\n\n3. Grant of Patent License. Subject to the terms and conditions of this License, each Contributor hereby grants to You a perpetual, worldwide, non-exclusive, no-charge, royalty-free, irrevocable (except as stated in this section) patent license to make, have made, use, offer to sell, sell, import, and otherwise transfer the Work, where such license applies only to those patent claims licensable by such Contributor that are necessarily infringed by their Contribution(s) alone or by combination of their Contribution(s) with the Work to which such Contribution(s) was submitted. If You institute patent litigation against any entity (including a cross-claim or counterclaim in a lawsuit) alleging that the Work or a Contribution incorporated within the Work constitutes direct or contributory patent infringement, then any patent licenses granted to You under this License for that Work shall terminate as of the date such litigation is filed.\n\n4. Redistribution. You may reproduce and distribute copies of the Work or Derivative Works thereof in any medium, with or without modifications, and in Source or Object form, provided that You meet the following conditions:\n\na. You must give any other recipients of the Work or Derivative Works a copy of this License; and\nb. You must cause any modified files to carry prominent notices stating that You changed the files; and\nc. You must retain, in the Source form of any Derivative Works that You distribute, all copyright, trademark, patent, attribution, and other notices from the Source form of the Work, excluding those notices that do not pertain to any part of the Derivative Works themselves; and\nd. If the Work includes a \"NOTICE\" text file as part of its distribution, then any Derivative Works that You distribute must include a readable copy of the attribution notices contained within such NOTICE file, excluding those notices that do not pertain to any part of the Derivative Works themselves, in at least one of the following places: within a NOTICE text file distributed as part of the Derivative Works; within the Source form or documentation, if provided along with the Derivative Works; or, within a display generated by the Derivative Works, if and wherever such third-party notices normally appear. The contents of the NOTICE file are for informational purposes only and do not modify the License. You may add Your own attribution notices within Derivative Works that You distribute, alongside or as an addendum to the NOTICE text from the Work, provided that such additional attribution notices cannot be construed as modifying the License. You may add Your own copyright notice to Your modifications and may provide additional or different license terms and conditions for use, reproduction, or distribution of Your modifications, or for any such Derivative Works as a whole, provided Your use, reproduction, and distribution of the Work otherwise complies with the conditions stated in this License.\n\ne. The Work may include a NOTICE text file that contains attribution notices provided by Licensor. You may not remove any of these notices from the Work.\n\n5. Submission of Contributions. Unless You explicitly state otherwise, any Contribution intentionally submitted for inclusion in the Work by You to the Licensor shall be under the terms and conditions of this License, without any additional terms or conditions. Notwithstanding the above, nothing herein shall supersede or modify the terms of any separate license agreement you may have executed with Licensor regarding such Contributions.\n\n6. Trademarks. This License does not grant any rights to use the trademarks, service marks, or product names of the Licensor, except as required for reasonable and customary use in describing the origin of the Work and reproducing the content of the NOTICE file.\n\n7. Disclaimer of Warranty. Unless required by applicable law or agreed to in writing, Licensor provides the Work (and each Contributor provides its Contributions) ON AN \"AS IS\" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied, including, without limitation, any warranties or conditions of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A PARTICULAR PURPOSE. You are solely responsible for determining the appropriateness of using or redistributing the Work and assume any risks associated with Your exercise of permissions under this License.\n\n8. Limitation of Liability. In no event and under no legal theory, whether in tort (including negligence), contract, or otherwise, unless required by applicable law (such as deliberate and grossly negligent acts) or agreed to in writing, shall any Contributor be liable to You for damages, including any direct, indirect, special, incidental, or consequential damages of any character arising as a result of this License or out of the use or inability to use the Work (including but not limited to damages for loss of goodwill, work stoppage, computer failure or malfunction, or any and all other commercial damages or losses), even if such Contributor has been advised of the possibility of such damages.\n\n9. Accepting Warranty or Support. You may choose to offer, and to charge a fee for, warranty, support, indemnity or other liability obligations and/or rights consistent with this License. However, in accepting such obligations, You may act only on Your own behalf and on Your sole responsibility, not on behalf of any other Contributor, and only if You agree to indemnify, defend, and hold each Contributor harmless for any liability incurred by, or claims asserted against, such Contributor by reason of your accepting any such warranty or support.\n\nEND OF TERMS AND CONDITIONS\n\nCopyright " . date('Y') . " " . $username . "\n\nLicensed under the Apache License, Version 2.0 (the \"License\"); you may not use this file except in compliance with the License. You may obtain a copy of the License at\n\nhttp://www.apache.org/licenses/LICENSE-2.0\n\nUnless required by applicable law or agreed to in writing, software distributed under the License is distributed on an \"AS IS\" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License."
    ];
    
    return $licenseContents[$license] ?? '';
}
?>