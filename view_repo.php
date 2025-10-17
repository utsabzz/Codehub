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

// Check if current user is the repository owner
$is_owner = ($repo['owner_id'] == $user_id);

// Check if user has access to private repository (owner or shared user)
$has_access = false;
if ($repo['visibility'] === 'public') {
    $has_access = true;
} else {
    // Check if user is owner
    if ($is_owner) {
        $has_access = true;
    } else {
        // Check if repository is shared with this user
        $shared_sql = "SELECT id FROM repository_shares WHERE repository_id = ? AND user_id = ?";
        $shared_stmt = $conn->prepare($shared_sql);
        $shared_stmt->bind_param("ii", $repo_id, $user_id);
        $shared_stmt->execute();
        $shared_result = $shared_stmt->get_result();
        $has_access = $shared_result->num_rows > 0;
    }
}

// Check repository visibility and access rights
if (!$has_access) {
    // User doesn't have access - show access denied
    $private_repo = true;
} else {
    $private_repo = false;
    
    // Update view count (only if user is viewing and not the owner, or if owner views count too)
    if (!$is_owner) {
        $update_views_sql = "UPDATE repositories SET views = views + 1 WHERE id = ?";
        $update_views_stmt = $conn->prepare($update_views_sql);
        $update_views_stmt->bind_param("i", $repo_id);
        $update_views_stmt->execute();
        $repo['views']++; // Update local repo data
    }
    
    // Check if user has already starred this repository
    $star_sql = "SELECT id FROM user_stars WHERE user_id = ? AND repository_id = ?";
    $star_stmt = $conn->prepare($star_sql);
    $star_stmt->bind_param("ii", $user_id, $repo_id);
    $star_stmt->execute();
    $star_result = $star_stmt->get_result();
    $has_starred = $star_result->num_rows > 0;

    // Check if user has already forked this repository
    $fork_sql = "SELECT id FROM repositories WHERE owner_id = ? AND forked_from = ?";
    $fork_stmt = $conn->prepare($fork_sql);
    $fork_stmt->bind_param("ii", $user_id, $repo_id);
    $fork_stmt->execute();
    $fork_result = $fork_stmt->get_result();
    $has_forked = $fork_result->num_rows > 0;

    // Handle fork action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fork_repo'])) {
        if (!$is_owner && !$has_forked) {
            // Fork the repository
            forkRepository($repo_id, $user_id, $current_user['username'], $conn);
            $has_forked = true;
            
            // Refresh repository data to get updated fork count
            $refresh_sql = "SELECT forks FROM repositories WHERE id = ?";
            $refresh_stmt = $conn->prepare($refresh_sql);
            $refresh_stmt->bind_param("i", $repo_id);
            $refresh_stmt->execute();
            $refresh_result = $refresh_stmt->get_result();
            $refreshed_repo = $refresh_result->fetch_assoc();
            $repo['forks'] = $refreshed_repo['forks'];
        }
    }

    // Handle star/unstar action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['star_action'])) {
        if (!$is_owner) {
            if ($_POST['star_action'] === 'star' && !$has_starred) {
                // Star the repository
                $star_insert_sql = "INSERT INTO user_stars (user_id, repository_id) VALUES (?, ?)";
                $star_insert_stmt = $conn->prepare($star_insert_sql);
                $star_insert_stmt->bind_param("ii", $user_id, $repo_id);
                $star_insert_stmt->execute();
                
                // Update stars count
                $update_stars_sql = "UPDATE repositories SET stars = stars + 1 WHERE id = ?";
                $update_stars_stmt = $conn->prepare($update_stars_sql);
                $update_stars_stmt->bind_param("i", $repo_id);
                $update_stars_stmt->execute();
                
                $has_starred = true;
                $repo['stars']++;
            } elseif ($_POST['star_action'] === 'unstar' && $has_starred) {
                // Unstar the repository
                $star_delete_sql = "DELETE FROM user_stars WHERE user_id = ? AND repository_id = ?";
                $star_delete_stmt = $conn->prepare($star_delete_sql);
                $star_delete_stmt->bind_param("ii", $user_id, $repo_id);
                $star_delete_stmt->execute();
                
                // Update stars count
                $update_stars_sql = "UPDATE repositories SET stars = stars - 1 WHERE id = ?";
                $update_stars_stmt = $conn->prepare($update_stars_sql);
                $update_stars_stmt->bind_param("i", $repo_id);
                $update_stars_stmt->execute();
                
                $has_starred = false;
                $repo['stars']--;
            }
        }
    }

    // Handle add collaborator action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_collaborator'])) {
        if ($is_owner && $repo['visibility'] === 'private') {
            $collaborator_username = trim($_POST['collaborator_username']);
            
            // Find user by username
            $user_search_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
            $user_search_stmt = $conn->prepare($user_search_sql);
            $user_search_stmt->bind_param("si", $collaborator_username, $user_id);
            $user_search_stmt->execute();
            $user_search_result = $user_search_stmt->get_result();
            
            if ($user_search_result->num_rows > 0) {
                $collaborator = $user_search_result->fetch_assoc();
                $collaborator_id = $collaborator['id'];
                
                // Check if already shared
                $check_share_sql = "SELECT id FROM repository_shares WHERE repository_id = ? AND user_id = ?";
                $check_share_stmt = $conn->prepare($check_share_sql);
                $check_share_stmt->bind_param("ii", $repo_id, $collaborator_id);
                $check_share_stmt->execute();
                $check_share_result = $check_share_stmt->get_result();
                
                if ($check_share_result->num_rows === 0) {
                    // Add collaborator
                    $add_share_sql = "INSERT INTO repository_shares (repository_id, user_id, shared_by, created_at) VALUES (?, ?, ?, NOW())";
                    $add_share_stmt = $conn->prepare($add_share_sql);
                    $add_share_stmt->bind_param("iii", $repo_id, $collaborator_id, $user_id);
                    $add_share_stmt->execute();
                    $share_success = "Repository shared with $collaborator_username successfully!";
                } else {
                    $share_error = "Repository is already shared with $collaborator_username";
                }
            } else {
                $share_error = "User '$collaborator_username' not found";
            }
        }
    }

    // Handle remove collaborator action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_collaborator'])) {
        if ($is_owner) {
            $remove_user_id = intval($_POST['user_id']);
            $remove_sql = "DELETE FROM repository_shares WHERE repository_id = ? AND user_id = ?";
            $remove_stmt = $conn->prepare($remove_sql);
            $remove_stmt->bind_param("ii", $repo_id, $remove_user_id);
            $remove_stmt->execute();
        }
    }

    // Get collaborators for private repositories (only if owner)
    $collaborators = [];
    if ($is_owner && $repo['visibility'] === 'private') {
        $collab_sql = "SELECT u.id, u.username, u.profile_image, rs.created_at 
                      FROM repository_shares rs 
                      JOIN users u ON rs.user_id = u.id 
                      WHERE rs.repository_id = ? 
                      ORDER BY rs.created_at DESC";
        $collab_stmt = $conn->prepare($collab_sql);
        $collab_stmt->bind_param("i", $repo_id);
        $collab_stmt->execute();
        $collab_result = $collab_stmt->get_result();
        $collaborators = $collab_result->fetch_all(MYSQLI_ASSOC);
    }

    // Get repository data only if user has access
    $repo_structure = getRepoStructure($repo['path']);
    $readme = getReadmeContent($repo['path']);
    $repo_stats = getRepoStats($repo['path']);

    // Calculate language percentages
    $total_files = array_sum($repo_stats['languages']);
    $language_percentages = [];
    foreach ($repo_stats['languages'] as $language => $count) {
        $language_percentages[$language] = round(($count / $total_files) * 100, 1);
    }
}

// Fork repository function
function forkRepository($repo_id, $user_id, $username, $conn) {
    // Get original repository data
    $original_sql = "SELECT * FROM repositories WHERE id = ?";
    $original_stmt = $conn->prepare($original_sql);
    $original_stmt->bind_param("i", $repo_id);
    $original_stmt->execute();
    $original_result = $original_stmt->get_result();
    $original_repo = $original_result->fetch_assoc();
    
    // Create new repository name (add -fork if name exists)
    $new_repo_name = $original_repo['name'];
    $counter = 1;
    
    while (true) {
        $check_sql = "SELECT id FROM repositories WHERE owner_id = ? AND name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $user_id, $new_repo_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            break;
        }
        $new_repo_name = $original_repo['name'] . '-fork-' . $counter;
        $counter++;
    }
    
    // Create repository directory
    $base_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/projects';
    $user_dir = $base_path . '/' . $username;
    $repo_dir = $user_dir . '/' . $new_repo_name;
    
    // Create directories if they don't exist
    if (!is_dir($user_dir)) {
        mkdir($user_dir, 0755, true);
    }
    
    // Copy original repository files
    $original_repo_path = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $original_repo['path'];
    if (is_dir($original_repo_path)) {
        copyDirectory($original_repo_path, $repo_dir);
    }
    
    // Insert forked repository into database
    $fork_sql = "INSERT INTO repositories (name, path, description, owner_id, owner_username, visibility, default_branch, forked_from, stars, forks, views) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0)";
    $fork_stmt = $conn->prepare($fork_sql);
    $new_repo_path = 'projects/' . $username . '/' . $new_repo_name;
    $fork_stmt->bind_param("sssisssi", $new_repo_name, $new_repo_path, $original_repo['description'], $user_id, $username, $original_repo['visibility'], $original_repo['default_branch'], $repo_id);
    $fork_stmt->execute();
    
    // Update fork count of original repository
    $update_forks_sql = "UPDATE repositories SET forks = forks + 1 WHERE id = ?";
    $update_forks_stmt = $conn->prepare($update_forks_sql);
    $update_forks_stmt->bind_param("i", $repo_id);
    $update_forks_stmt->execute();
    
    return $conn->insert_id;
}

// Helper function to copy directory recursively
function copyDirectory($source, $destination) {
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            copy($item->getPathname(), $target);
        }
    }
}

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

// Improved markdown parser with proper HTML handling
function parseMarkdown($content) {
    // First, handle code blocks to preserve their content
    $content = preg_replace_callback('/```(\w+)?\n(.*?)```/s', function($matches) {
        $language = $matches[1] ?? '';
        $code = htmlspecialchars($matches[2]);
        return '<pre class="bg-gray-100 p-4 rounded-lg overflow-x-auto"><code class="language-' . $language . '">' . $code . '</code></pre>';
    }, $content);
    
    // Handle inline code
    $content = preg_replace_callback('/`([^`]+)`/', function($matches) {
        return '<code class="bg-gray-100 px-1 py-0.5 rounded text-sm">' . htmlspecialchars($matches[1]) . '</code>';
    }, $content);
    
    // Headers
    $content = preg_replace('/^# (.*$)/m', '<h1 class="text-2xl font-bold mt-6 mb-4 pb-2 border-b">$1</h1>', $content);
    $content = preg_replace('/^## (.*$)/m', '<h2 class="text-xl font-bold mt-5 mb-3 pb-2 border-b">$1</h2>', $content);
    $content = preg_replace('/^### (.*$)/m', '<h3 class="text-lg font-bold mt-4 mb-2">$1</h3>', $content);
    $content = preg_replace('/^#### (.*$)/m', '<h4 class="text-base font-bold mt-3 mb-2">$1</h4>', $content);
    $content = preg_replace('/^##### (.*$)/m', '<h5 class="text-sm font-bold mt-2 mb-1">$1</h5>', $content);
    $content = preg_replace('/^###### (.*$)/m', '<h6 class="text-xs font-bold mt-2 mb-1">$1</h6>', $content);
    
    // Bold and Italic
    $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
    $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
    $content = preg_replace('/__(.*?)__/', '<strong>$1</strong>', $content);
    $content = preg_replace('/_(.*?)_/', '<em>$1</em>', $content);
    
    // Links
    $content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" class="text-blue-600 hover:underline" target="_blank" rel="noopener">$1</a>', $content);
    
    // Images
    $content = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" class="max-w-full h-auto rounded-lg shadow-md my-4">', $content);
    
    // Blockquotes
    $content = preg_replace('/^> (.*$)/m', '<blockquote class="border-l-4 border-blue-500 pl-4 italic text-gray-700 bg-blue-50 py-2 my-4">$1</blockquote>', $content);
    
    // Horizontal Rule
    $content = preg_replace('/^\s*---\s*$/m', '<hr class="my-6 border-gray-300">', $content);
    $content = preg_replace('/^\s*\*\*\*\s*$/m', '<hr class="my-6 border-gray-300">', $content);
    $content = preg_replace('/^\s*___\s*$/m', '<hr class="my-6 border-gray-300">', $content);
    
    // Lists - unordered
    $content = preg_replace('/^\s*[-*+] (.*)$/m', '<li class="ml-4">$1</li>', $content);
    $content = preg_replace_callback('/(<li class="ml-4">.*?<\/li>)+/s', function($matches) {
        return '<ul class="list-disc list-inside space-y-1 my-4">' . $matches[0] . '</ul>';
    }, $content);
    
    // Lists - ordered
    $content = preg_replace('/^\s*\d+\. (.*)$/m', '<li class="ml-4">$1</li>', $content);
    $content = preg_replace_callback('/(<li class="ml-4">.*?<\/li>)+/s', function($matches) {
        return '<ol class="list-decimal list-inside space-y-1 my-4">' . $matches[0] . '</ol>';
    }, $content);
    
    // Line breaks - handle properly
    $lines = explode("\n", $content);
    $in_code_block = false;
    $in_list = false;
    $result = [];
    
    foreach ($lines as $line) {
        if (strpos($line, '<pre') !== false) {
            $in_code_block = true;
        }
        if (strpos($line, '</pre>') !== false) {
            $in_code_block = false;
        }
        
        if (strpos($line, '<ul') !== false || strpos($line, '<ol') !== false) {
            $in_list = true;
        }
        if (strpos($line, '</ul>') !== false || strpos($line, '</ol>') !== false) {
            $in_list = false;
        }
        
        // Don't add <br> in code blocks, lists, or for empty lines
        if (!$in_code_block && !$in_list && trim($line) !== '' && 
            !preg_match('/^<(h[1-6]|ul|ol|li|blockquote|hr|pre)/', $line) &&
            !preg_match('/^<\/(h[1-6]|ul|ol|li|blockquote|hr|pre)/', $line)) {
            $line .= '<br>';
        }
        
        $result[] = $line;
    }
    
    $content = implode("\n", $result);
    
    // Tables (basic support)
    $content = preg_replace_callback('/\|(.+)\|\n\|([-:| ]+)+\|\n((?:\|.*\|\n?)*)/', function($matches) {
        $headers = explode('|', trim($matches[1], '|'));
        $rows = explode("\n", trim($matches[3]));
        
        $table = '<table class="min-w-full border border-gray-300 my-4">';
        $table .= '<thead><tr>';
        foreach ($headers as $header) {
            $table .= '<th class="border border-gray-300 px-4 py-2 bg-gray-100 font-semibold">' . trim($header) . '</th>';
        }
        $table .= '</tr></thead><tbody>';
        
        foreach ($rows as $row) {
            if (trim($row) === '') continue;
            $cells = explode('|', trim($row, '|'));
            $table .= '<tr>';
            foreach ($cells as $cell) {
                $table .= '<td class="border border-gray-300 px-4 py-2">' . trim($cell) . '</td>';
            }
            $table .= '</tr>';
        }
        
        $table .= '</tbody></table>';
        return $table;
    }, $content);
    
    // Escape any remaining HTML tags that might be dangerous, but allow safe ones
    $safe_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'br', 'strong', 'em', 'code', 'pre', 'ul', 'ol', 'li', 'blockquote', 'hr', 'a', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td'];
    $safe_attributes = ['class', 'href', 'src', 'alt', 'target', 'rel'];
    
    // Use a proper HTML purifier approach - for simplicity, we'll use strip_tags with allowed tags
    $content = strip_tags($content, '<' . implode('><', $safe_tags) . '>');
    
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

        .markdown-body {
            line-height: 1.6;
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

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 0;
            border-radius: 12px;
            width: 400px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        .share-link {
            background-color: #f6f8fa;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            padding: 8px 12px;
            font-family: monospace;
            font-size: 14px;
            cursor: pointer;
        }
        
        .share-link:hover {
            background-color: #f3f4f6;
        }
        
        .collaborator-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e1e4e8;
        }
        
        .collaborator-item:last-child {
            border-bottom: none;
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
                        <a href="comit.php" class="text-gray-700 hover:text-gray-900 hover-underline">Comit History</a>
                        <a href="#" class="text-gray-700 hover:text-gray-900 hover-underline">Issues</a>
                        <a href="#" class="text-gray-700 hover:text-gray-900 hover-underline">Explore</a>
                    </nav>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <input type="text" placeholder="Search CodeHub"
                            class="w-64 px-3 py-1 bg-gray-100 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div class="dropdown relative">
                        <button class="text-gray-700 hover:text-gray-900">
                            <i class="fas fa-bell text-lg"></i>
                        </button>
                        <div
                            class="dropdown-content absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                            <div class="p-4">
                                <h3 class="font-semibold mb-2">Notifications</h3>
                                <p class="text-sm text-gray-600">You have no new notifications</p>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown relative">
                        <button class="flex items-center space-x-2">
                            <img src="<?php echo !empty($current_user['profile_image']) ? $current_user['profile_image'] : 'https://picsum.photos/seed/user' . $current_user['id'] . '/32/32.jpg'; ?>"
                                alt="User" class="w-8 h-8 rounded-full">
                            <i class="fas fa-chevron-down text-xs text-gray-600"></i>
                        </button>
                        <div
                            class="dropdown-content absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                            <div class="py-2">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Signed in as
                                    <?php echo $current_user['username']; ?>
                                </a>
                                <hr class="my-2">
                                <a href="profile.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your profile</a>
                                <a href="dashboard.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your
                                    repositories</a>
                                <a href="project.php?id=<?php echo $repo['id']; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your
                                    projects</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your
                                    stars</a>
                                <hr class="my-2">
                                <a href="project_settings.php?id=<?php echo $repo['id']; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                <a href="backend/logout.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <?php if ($private_repo): ?>
        <!-- Private Repository Access Denied -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center">
                <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-lock text-3xl text-gray-400"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-4">Private Repository</h1>
                <p class="text-gray-600 mb-8 max-w-md mx-auto">
                    This repository is private. You don't have permission to view its contents.
                </p>
                <div class="flex justify-center space-x-4">
                    <a href="dashboard.php" class="px-6 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600">
                        Back to Dashboard
                    </a>
                    <a href="explore.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        Explore Public Repositories
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Repository Header -->
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <a href="dashboard.php" class="text-gray-600 hover:text-gray-900 hover-underline">
                            <?php echo htmlspecialchars($repo['owner_username']); ?>
                        </a>
                        <span class="text-gray-400">/</span>
                        <h1 class="text-xl font-bold text-gray-900">
                            <?php echo htmlspecialchars($repo['name']); ?>
                        </h1>
                        <span class="px-2 py-1 text-xs font-semibold text-gray-600 bg-gray-100 rounded">
                            <?php echo ucfirst($repo['visibility']); ?>
                        </span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <!-- Share Button - Show for all repositories -->
                        <button onclick="showShareModal()" class="btn-secondary px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2 hover:bg-gray-200">
                            <i class="fas fa-share-alt"></i>
                            <span>Share</span>
                        </button>
                        
                        <button class="btn-secondary px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2">
                            <i class="fas fa-eye"></i>
                            <span>Watch</span>
                            <span class="text-gray-600"><?php echo $repo['views']; ?></span>
                        </button>
                        
                        <!-- Fork Button - Only show if user is not the owner -->
                        <?php if (!$is_owner): ?>
                            <?php if ($has_forked): ?>
                                <button class="btn-secondary px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2" disabled>
                                    <i class="fas fa-code-branch"></i>
                                    <span>Forked</span>
                                    <span class="text-gray-600"><?php echo $repo['forks']; ?></span>
                                </button>
                            <?php else: ?>
                                <button onclick="showForkModal()" class="btn-secondary px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2 hover:bg-gray-200">
                                    <i class="fas fa-code-branch"></i>
                                    <span>Fork</span>
                                    <span class="text-gray-600"><?php echo $repo['forks']; ?></span>
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Show fork count only for owner -->
                            <button class="btn-secondary px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2" disabled>
                                <i class="fas fa-code-branch"></i>
                                <span>Forks</span>
                                <span class="text-gray-600"><?php echo $repo['forks']; ?></span>
                            </button>
                        <?php endif; ?>
                        
                        <!-- Star Button - Only show if user is not the owner -->
                        <?php if (!$is_owner): ?>
                            <form method="POST" action="" class="m-0">
                                <?php if ($has_starred): ?>
                                    <input type="hidden" name="star_action" value="unstar">
                                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2 bg-yellow-500 hover:bg-yellow-600">
                                        <i class="fas fa-star"></i>
                                        <span>Unstar</span>
                                        <span class="text-yellow-100"><?php echo $repo['stars']; ?></span>
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="star_action" value="star">
                                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2">
                                        <i class="fas fa-star"></i>
                                        <span>Star</span>
                                        <span class="text-gray-200"><?php echo $repo['stars']; ?></span>
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <!-- Show star count only for owner -->
                            <button class="btn-primary px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-2" disabled>
                                <i class="fas fa-star"></i>
                                <span>Stars</span>
                                <span class="text-gray-200"><?php echo $repo['stars']; ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Share Modal -->
        <div id="shareModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="text-lg font-semibold text-gray-900">Share Repository</h3>
                </div>
                <div class="modal-body">
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Repository Link</label>
                        <div class="flex space-x-2">
                            <input type="text" id="shareLink" value="<?php echo "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>" readonly class="flex-1 share-link">
                            <button onclick="copyShareLink()" class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                Copy
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            <?php if ($repo['visibility'] === 'public'): ?>
                                Anyone with this link can view this repository.
                            <?php else: ?>
                                Only you and people you share with can view this repository.
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php if ($is_owner && $repo['visibility'] === 'private'): ?>
                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="font-semibold text-gray-900 mb-3">Share with specific people</h4>
                            
                            <?php if (isset($share_success)): ?>
                                <div class="mb-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                    <p class="text-green-700 text-sm"><?php echo $share_success; ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($share_error)): ?>
                                <div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                                    <p class="text-red-700 text-sm"><?php echo $share_error; ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" class="mb-4">
                                <div class="flex space-x-2">
                                    <input type="text" name="collaborator_username" placeholder="Enter username" required class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" placeholder="e.g., johndoe">
                                    <button type="submit" name="add_collaborator" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                        Add
                                    </button>
                                </div>
                            </form>
                            
                            <?php if (!empty($collaborators)): ?>
                                <div class="mt-4">
                                    <h5 class="font-medium text-gray-900 mb-2">People with access</h5>
                                    <div class="space-y-2 max-h-40 overflow-y-auto">
                                        <?php foreach ($collaborators as $collab): ?>
                                            <div class="collaborator-item">
                                                <div class="flex items-center space-x-3 flex-1">
                                                    <img src="<?php echo !empty($collab['profile_image']) ? $collab['profile_image'] : 'https://picsum.photos/seed/user' . $collab['id'] . '/32/32.jpg'; ?>" alt="<?php echo htmlspecialchars($collab['username']); ?>" class="w-6 h-6 rounded-full">
                                                    <span class="text-sm font-medium"><?php echo htmlspecialchars($collab['username']); ?></span>
                                                </div>
                                                <form method="POST" action="" class="m-0">
                                                    <input type="hidden" name="user_id" value="<?php echo $collab['id']; ?>">
                                                    <button type="submit" name="remove_collaborator" class="text-red-600 hover:text-red-800 text-sm">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideShareModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Fork Confirmation Modal -->
        <div id="forkModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="text-lg font-semibold text-gray-900">Fork repository</h3>
                </div>
                <div class="modal-body">
                    <p class="text-gray-600 mb-4">This will copy the entire repository <strong><?php echo htmlspecialchars($repo['name']); ?></strong> to your account.</p>
                    <p class="text-sm text-gray-500">You can make changes to your fork without affecting the original repository.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideForkModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                        Cancel
                    </button>
                    <form method="POST" action="" class="m-0">
                        <input type="hidden" name="fork_repo" value="1">
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                            Fork repository
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex space-x-8">
                    <a href="#" class="py-4 px-1 text-sm font-medium tab-active">Code</a>
                    <a href="#" class="py-4 px-1 text-sm font-medium text-gray-600 hover:text-gray-900">Issues</a>
                    <a href="comit.php" class="py-4 px-1 text-sm font-medium text-gray-600 hover:text-gray-900">Comit History</a>
                    <a href="#" class="py-4 px-1 text-sm font-medium text-gray-600 hover:text-gray-900">Actions</a>
                    <a  href="project.php?id=<?php echo $repo['id']; ?>" class="py-4 px-1 text-sm font-medium text-gray-600 hover:text-gray-900">Projects</a>
                    <a href="#" class="py-4 px-1 text-sm font-medium text-gray-600 hover:text-gray-900">Security</a>
                    <a href="#" class="py-4 px-1 text-sm font-medium text-gray-600 hover:text-gray-900">Insights</a>
                    <a href="project_settings.php?id=<?php echo $repo['id']; ?>" class="py-4 px-1 text-sm font-medium text-gray-600 hover:text-gray-900">Settings</a>
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
                                    <button
                                        class="flex items-center space-x-2 px-3 py-1 bg-gray-100 rounded-lg hover:bg-gray-200">
                                        <i class="fas fa-code-branch text-gray-600"></i>
                                        <span class="text-sm font-medium">
                                            <?php echo htmlspecialchars($repo['default_branch']); ?>
                                        </span>
                                        <i class="fas fa-chevron-down text-xs text-gray-600"></i>
                                    </button>
                                    <div
                                        class="branch-dropdown-content absolute left-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                        <div class="p-2">
                                            <input type="text" placeholder="Find or create a branch..."
                                                class="w-full px-3 py-2 text-sm border border-gray-200 rounded focus:outline-none focus:ring-2 focus:ring-orange-500">
                                            <div class="mt-2 py-2 border-t border-gray-200">
                                                <div class="px-3 py-2 hover:bg-gray-100 cursor-pointer">
                                                    <div class="flex items-center space-x-2">
                                                        <i class="fas fa-code-branch text-gray-600"></i>
                                                        <span class="text-sm font-medium">
                                                            <?php echo htmlspecialchars($repo['default_branch']); ?>
                                                        </span>
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
                                    <span>
                                        <?php echo htmlspecialchars($repo['owner_username']); ?>
                                    </span>
                                    <span>/</span>
                                    <span>
                                        <?php echo htmlspecialchars($repo['name']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button class="px-3 py-1 text-sm text-gray-600 hover:text-gray-900" id="copyLinkBtn">
                                    <i class="fas fa-link"></i>
                                </button>
                                <button class="px-3 py-1 text-sm text-gray-600 hover:text-gray-900" id="downloadBtn">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button class="btn-primary px-4 py-1 rounded text-sm font-medium"
                                    onclick="openEditor(<?php echo $repo_id; ?>)">
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
                                    <div class="file-row flex items-center space-x-3 px-3 py-2 rounded hover:bg-gray-50 cursor-pointer"
                                        onclick="openFileInEditor('<?php echo htmlspecialchars($item['name']); ?>', '<?php echo htmlspecialchars($item['path']); ?>', <?php echo $repo_id; ?>)">
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
                                        <span class="text-sm text-gray-800">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </span>
                                        <?php if (!$item['is_dir']): ?>
                                        <span class="text-xs text-gray-500 ml-auto">
                                            <?php echo localFormatFileSize($item['size']); ?>
                                        </span>
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
                            <h2 class="text-lg font-semibold">
                                <?php echo htmlspecialchars($readme['filename']); ?>
                            </h2>
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
                        <p class="text-sm text-gray-600 mb-3">
                            <?php echo !empty($repo['description']) ? htmlspecialchars($repo['description']) : 'No description provided'; ?>
                        </p>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-code-branch text-gray-400 w-4"></i>
                                <span class="text-gray-600">
                                    <?php echo htmlspecialchars($repo['default_branch']); ?>
                                </span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-clock text-gray-400 w-4"></i>
                                <span class="text-gray-600">Updated
                                    <?php echo localGetTimeAgo($repo['updated_at']); ?>
                                </span>
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
                                    <span class="language-color"
                                        style="background-color: <?php echo localGetLanguageColorCode($language); ?>"></span>
                                    <span class="text-sm">
                                        <?php echo $language; ?>
                                    </span>
                                </div>
                                <span class="text-sm text-gray-600">
                                    <?php echo $percentage; ?>%
                                </span>
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
                                <span class="font-medium">
                                    <?php echo $repo_stats['files']; ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Folders:</span>
                                <span class="font-medium">
                                    <?php echo $repo_stats['folders']; ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Size:</span>
                                <span class="font-medium">
                                    <?php echo localFormatFileSize($repo_stats['total_size']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Open file in editor
        function openFileInEditor(fileName, filePath, repoId) {
            // Check if it's a directory
            if (filePath.includes('/') || fileName.includes('.')) {
                // It's a file, open in editor
                window.location.href = 'editor.php?id=' + repoId + '&file=' + encodeURIComponent(filePath);
            } else {
                // It's a directory, we could implement folder navigation here
                alert('Folder navigation coming soon!');
            }
        }

        // Add interactive functionality
        document.addEventListener('DOMContentLoaded', function () {
            // Tab switching
            const tabs = document.querySelectorAll('a[href="#"]');
            tabs.forEach(tab => {
                tab.addEventListener('click', function (e) {
                    if (this.parentElement.classList.contains('flex') && this.parentElement.classList.contains('space-x-8')) {
                        e.preventDefault();
                        tabs.forEach(t => t.classList.remove('tab-active'));
                        this.classList.add('tab-active');
                    }
                });
            });

            // Copy link functionality
            const copyLinkBtn = document.getElementById('copyLinkBtn');
            if (copyLinkBtn) {
                copyLinkBtn.addEventListener('click', function () {
                    navigator.clipboard.writeText(window.location.href);
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check text-green-600"></i>';
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                    }, 2000);
                });
            }

            // Download button functionality
            const downloadBtn = document.getElementById('downloadBtn');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function () {
                    // Create a temporary link for download
                    const link = document.createElement('a');
                    link.href = 'download_repo.php?id=<?php echo $repo_id; ?>';
                    link.download = '<?php echo htmlspecialchars($repo['name']); ?>.zip';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            }

            // File click handlers
            const fileRows = document.querySelectorAll('.file-row');
            fileRows.forEach(row => {
                row.addEventListener('click', function () {
                    const fileName = this.querySelector('span').textContent;
                    // In a real implementation, this would navigate to the file view
                });
            });
        });

        // Open editor function
        function openEditor(repoId) {
            window.location.href = 'editor.php?id=' + repoId;
        }

        // Share modal functions
        function showShareModal() {
            document.getElementById('shareModal').style.display = 'block';
        }

        function hideShareModal() {
            document.getElementById('shareModal').style.display = 'none';
        }

        function copyShareLink() {
            const shareLink = document.getElementById('shareLink');
            shareLink.select();
            shareLink.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(shareLink.value);
            
            // Show copied feedback
            const copyButton = event.target;
            const originalText = copyButton.textContent;
            copyButton.textContent = 'Copied!';
            copyButton.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            copyButton.classList.add('bg-green-600', 'hover:bg-green-700');
            
            setTimeout(() => {
                copyButton.textContent = originalText;
                copyButton.classList.remove('bg-green-600', 'hover:bg-green-700');
                copyButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
            }, 2000);
        }

        // Fork modal functions
        function showForkModal() {
            document.getElementById('forkModal').style.display = 'block';
        }

        function hideForkModal() {
            document.getElementById('forkModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['shareModal', 'forkModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>