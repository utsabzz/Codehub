<?php
// Repository helper functions

/**
 * Get repository content from filesystem
 * @param string $repoPath Path to the repository
 * @param string $currentPath Current path within the repository (default: root)
 * @return array Array of files and directories
 */
function getRepositoryContent($repoPath, $currentPath = '') {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $repoPath;
    if (!empty($currentPath)) {
        $fullPath .= '/' . $currentPath;
    }
    
    if (!is_dir($fullPath)) {
        return [];
    }
    
    $content = [];
    $items = scandir($fullPath);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $itemPath = $fullPath . '/' . $item;
        $relativePath = empty($currentPath) ? $item : $currentPath . '/' . $item;
        
        if (is_dir($itemPath)) {
            $content[] = [
                'name' => $item,
                'path' => $relativePath,
                'type' => 'directory',
                'size' => 0,
                'modified' => filemtime($itemPath)
            ];
        } else {
            $content[] = [
                'name' => $item,
                'path' => $relativePath,
                'type' => 'file',
                'size' => filesize($itemPath),
                'modified' => filemtime($itemPath),
                'extension' => pathinfo($item, PATHINFO_EXTENSION)
            ];
        }
    }
    
    // Sort: directories first, then files, both alphabetically
    usort($content, function($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'directory' ? -1 : 1;
        }
        return strcmp($a['name'], $b['name']);
    });
    
    return $content;
}

/**
 * Find README file in a directory
 * @param string $repoPath Path to the repository
 * @param string $currentPath Current path within the repository
 * @return string|false Path to README file or false if not found
 */
function findReadmeFile($repoPath, $currentPath = '') {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $repoPath;
    if (!empty($currentPath)) {
        $fullPath .= '/' . $currentPath;
    }
    
    if (!is_dir($fullPath)) {
        return false;
    }
    
    $readmeNames = ['README.md', 'README.markdown', 'README.txt', 'README', 'readme.md', 'readme.markdown', 'readme.txt', 'readme'];
    
    foreach ($readmeNames as $readme) {
        if (file_exists($fullPath . '/' . $readme)) {
            return empty($currentPath) ? $readme : $currentPath . '/' . $readme;
        }
    }
    
    return false;
}

/**
 * Get file content
 * @param string $repoPath Path to the repository
 * @param string $filePath Path to the file within the repository
 * @return string|false File content or false if not found
 */
function getFileContent($repoPath, $filePath) {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $repoPath . '/' . $filePath;
    
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        return false;
    }
    
    return file_get_contents($fullPath);
}

/**
 * Parse markdown to HTML (basic implementation)
 * @param string $markdown Markdown content
 * @return string HTML content
 */
function parseMarkdown($markdown) {
    // Basic markdown parsing
    $html = $markdown;
    
    // Headers
    $html = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $html);
    
    // Bold
    $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
    
    // Italic
    $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);
    
    // Code blocks
    $html = preg_replace('/```(.*?)```/s', '<pre><code>$1</code></pre>', $html);
    
    // Inline code
    $html = preg_replace('/`(.*?)`/', '<code>$1</code>', $html);
    
    // Links
    $html = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $html);
    
    // Line breaks
    $html = preg_replace('/\n\n/', '</p><p>', $html);
    $html = preg_replace('/\n/', '<br>', $html);
    
    // Wrap in paragraphs
    $html = '<p>' . $html . '</p>';
    
    // Clean up
    $html = str_replace('<p></p>', '', $html);
    $html = str_replace('<p><pre>', '<pre>', $html);
    $html = str_replace('</pre></p>', '</pre>', $html);
    $html = str_replace('<p><h1>', '<h1>', $html);
    $html = str_replace('</h1></p>', '</h1>', $html);
    $html = str_replace('<p><h2>', '<h2>', $html);
    $html = str_replace('</h2></p>', '</h2>', $html);
    $html = str_replace('<p><h3>', '<h3>', $html);
    $html = str_replace('</h3></p>', '</h3>', $html);
    
    return $html;
}

/**
 * Get file icon based on extension
 * @param string $extension File extension
 * @return string Font Awesome icon class
 */
function getFileIcon($extension) {
    $icons = [
        'js' => 'fab fa-js-square text-yellow-500',
        'jsx' => 'fab fa-react text-blue-400',
        'ts' => 'fab fa-js-square text-blue-600',
        'tsx' => 'fab fa-react text-blue-500',
        'html' => 'fab fa-html5 text-orange-500',
        'htm' => 'fab fa-html5 text-orange-500',
        'css' => 'fab fa-css3-alt text-blue-500',
        'scss' => 'fab fa-sass text-pink-500',
        'sass' => 'fab fa-sass text-pink-500',
        'json' => 'fas fa-code text-gray-500',
        'xml' => 'fas fa-code text-orange-400',
        'md' => 'fab fa-markdown text-blue-600',
        'markdown' => 'fab fa-markdown text-blue-600',
        'txt' => 'fas fa-file-alt text-gray-500',
        'pdf' => 'fas fa-file-pdf text-red-500',
        'png' => 'fas fa-file-image text-green-500',
        'jpg' => 'fas fa-file-image text-green-500',
        'jpeg' => 'fas fa-file-image text-green-500',
        'gif' => 'fas fa-file-image text-green-500',
        'svg' => 'fas fa-file-image text-green-500',
        'zip' => 'fas fa-file-archive text-yellow-600',
        'rar' => 'fas fa-file-archive text-yellow-600',
        'tar' => 'fas fa-file-archive text-yellow-600',
        'gz' => 'fas fa-file-archive text-yellow-600',
        'php' => 'fab fa-php text-purple-500',
        'py' => 'fab fa-python text-blue-400',
        'java' => 'fab fa-java text-red-500',
        'c' => 'fas fa-code text-blue-600',
        'cpp' => 'fas fa-code text-blue-500',
        'cs' => 'fas fa-code text-purple-600',
        'go' => 'fas fa-code text-cyan-500',
        'rs' => 'fas fa-code text-orange-600',
        'rb' => 'fas fa-gem text-red-600',
        'swift' => 'fab fa-swift text-orange-500',
        'kt' => 'fas fa-code text-purple-700',
        'sql' => 'fas fa-database text-blue-700',
        'sh' => 'fas fa-terminal text-gray-600',
        'bash' => 'fas fa-terminal text-gray-600',
        'zsh' => 'fas fa-terminal text-gray-600',
        'fish' => 'fas fa-terminal text-gray-600',
        'yml' => 'fas fa-code text-purple-500',
        'yaml' => 'fas fa-code text-purple-500',
        'toml' => 'fas fa-code text-gray-500',
        'ini' => 'fas fa-cog text-gray-500',
        'conf' => 'fas fa-cog text-gray-500',
        'log' => 'fas fa-file-alt text-gray-500',
        'lock' => 'fas fa-lock text-red-500',
        'key' => 'fas fa-key text-yellow-500',
        'pem' => 'fas fa-key text-yellow-500',
        'crt' => 'fas fa-certificate text-green-500',
        'dockerfile' => 'fab fa-docker text-blue-500',
        'gitignore' => 'fab fa-git-alt text-red-500',
        'gitattributes' => 'fab fa-git-alt text-red-500',
        'editorconfig' => 'fas fa-cog text-gray-500',
        'eslintrc' => 'fas fa-code text-purple-500',
        'prettierrc' => 'fas fa-code text-pink-500',
        'babelrc' => 'fas fa-code text-yellow-500',
        'tsconfig' => 'fas fa-code text-blue-600',
        'package' => 'fas fa-box text-red-500',
        'composer' => 'fas fa-box text-blue-500',
        'gemfile' => 'fas fa-gem text-red-600',
        'pipfile' => 'fas fa-box text-blue-400',
        'requirements' => 'fas fa-box text-blue-400',
        'makefile' => 'fas fa-cog text-gray-500',
        'cmake' => 'fas fa-cog text-red-500',
        'gradle' => 'fas fa-code text-green-600',
        'maven' => 'fas fa-code text-red-600',
        'nuget' => 'fas fa-box text-purple-500',
        'cargo' => 'fas fa-cog text-orange-600',
        'npm' => 'fab fa-npm text-red-500',
        'yarn' => 'fab fa-yarn text-blue-500',
        'pnpm' => 'fas fa-box text-orange-500',
        'license' => 'fas fa-balance-scale text-gray-500',
        'licence' => 'fas fa-balance-scale text-gray-500',
        'copying' => 'fas fa-balance-scale text-gray-500',
        'authors' => 'fas fa-users text-gray-500',
        'contributors' => 'fas fa-users text-gray-500',
        'changelog' => 'fas fa-history text-gray-500',
        'changes' => 'fas fa-history text-gray-500',
        'history' => 'fas fa-history text-gray-500',
        'news' => 'fas fa-newspaper text-gray-500',
        'readme' => 'fas fa-info-circle text-blue-500',
        'install' => 'fas fa-download text-green-500',
        'setup' => 'fas fa-cogs text-gray-500',
        'config' => 'fas fa-cog text-gray-500',
        'configure' => 'fas fa-cog text-gray-500',
        'build' => 'fas fa-hammer text-gray-500',
        'make' => 'fas fa-hammer text-gray-500',
        'test' => 'fas fa-vial text-green-500',
        'tests' => 'fas fa-vial text-green-500',
        'spec' => 'fas fa-vial text-green-500',
        'specs' => 'fas fa-vial text-green-500',
        'example' => 'fas fa-file-code text-gray-500',
        'examples' => 'fas fa-file-code text-gray-500',
        'sample' => 'fas fa-file-code text-gray-500',
        'samples' => 'fas fa-file-code text-gray-500',
        'demo' => 'fas fa-play text-green-500',
        'demos' => 'fas fa-play text-green-500',
        'doc' => 'fas fa-file-alt text-blue-500',
        'docs' => 'fas fa-file-alt text-blue-500',
        'documentation' => 'fas fa-file-alt text-blue-500',
        'img' => 'fas fa-image text-green-500',
        'imgs' => 'fas fa-image text-green-500',
        'image' => 'fas fa-image text-green-500',
        'images' => 'fas fa-image text-green-500',
        'icon' => 'fas fa-image text-green-500',
        'icons' => 'fas fa-image text-green-500',
        'asset' => 'fas fa-file text-gray-500',
        'assets' => 'fas fa-file text-gray-500',
        'resource' => 'fas fa-file text-gray-500',
        'resources' => 'fas fa-file text-gray-500',
        'lib' => 'fas fa-folder text-yellow-600',
        'libs' => 'fas fa-folder text-yellow-600',
        'library' => 'fas fa-folder text-yellow-600',
        'libraries' => 'fas fa-folder text-yellow-600',
        'src' => 'fas fa-folder text-blue-500',
        'source' => 'fas fa-folder text-blue-500',
        'bin' => 'fas fa-folder text-gray-600',
        'obj' => 'fas fa-folder text-gray-600',
        'out' => 'fas fa-folder text-gray-600',
        'build' => 'fas fa-folder text-gray-600',
        'dist' => 'fas fa-folder text-gray-600',
        'target' => 'fas fa-folder text-gray-600',
        'release' => 'fas fa-folder text-green-500',
        'releases' => 'fas fa-folder text-green-500',
        'version' => 'fas fa-tag text-blue-500',
        'versions' => 'fas fa-tags text-blue-500',
        'backup' => 'fas fa-archive text-yellow-600',
        'backups' => 'fas fa-archive text-yellow-600',
        'tmp' => 'fas fa-folder text-gray-500',
        'temp' => 'fas fa-folder text-gray-500',
        'cache' => 'fas fa-folder text-orange-500',
        'log' => 'fas fa-folder text-red-500',
        'logs' => 'fas fa-folder text-red-500'
    ];
    
    return $icons[strtolower($extension)] ?? 'fas fa-file text-gray-500';
}

/**
 * Format file size
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}

/**
 * Get language color
 * @param string $language Programming language
 * @return string Color name
 */
function getLanguageColor($language) {
    $colors = [
        'JavaScript' => 'yellow',
        'TypeScript' => 'blue',
        'Python' => 'red',
        'React Native' => 'cyan',
        'D3.js' => 'orange',
        'Node.js' => 'green',
        'Vue.js' => 'green',
        'PHP' => 'purple',
        'Java' => 'red',
        'C' => 'blue',
        'C++' => 'blue',
        'C#' => 'purple',
        'Go' => 'cyan',
        'Rust' => 'orange',
        'Ruby' => 'red',
        'Swift' => 'orange',
        'Kotlin' => 'purple',
        'SQL' => 'blue'
    ];
    return $colors[$language] ?? 'gray';
}

/**
 * Get status color
 * @param string $status Status
 * @return string Color name
 */
function getStatusColor($status) {
    $colors = [
        'open' => 'orange',
        'closed' => 'green',
        'merged' => 'purple'
    ];
    return $colors[$status] ?? 'gray';
}

/**
 * Get status text
 * @param string $status Status
 * @return string Status text
 */
function getStatusText($status) {
    $texts = [
        'open' => 'OPEN',
        'closed' => 'CLOSED',
        'merged' => 'MERGED'
    ];
    return $texts[$status] ?? 'UNKNOWN';
}

/**
 * Get type color
 * @param string $type Issue type
 * @return string Color name
 */
function getTypeColor($type) {
    $colors = [
        'bug' => 'red',
        'enhancement' => 'blue',
        'question' => 'yellow',
        'documentation' => 'green'
    ];
    return $colors[$type] ?? 'gray';
}

/**
 * Format time ago
 * @param string $datetime DateTime string
 * @return string Formatted time ago
 */
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M j, Y', $time);
    }
}
?>