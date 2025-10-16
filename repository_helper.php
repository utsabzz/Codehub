<?php
// repository_helper.php

function findReadmeFile($repoPath) {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repoPath;
    $readmeFiles = ['README.md', 'readme.md', 'README.txt', 'readme.txt'];
    
    foreach ($readmeFiles as $file) {
        if (file_exists($fullPath . '/' . $file)) {
            return $file;
        }
    }
    return null;
}

function getFileContent($repoPath, $filename) {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/CodeHub/' . $repoPath . '/' . $filename;
    if (file_exists($fullPath)) {
        return file_get_contents($fullPath);
    }
    return null;
}

function getLanguageColor($language) {
    $colors = [
        'PHP' => 'purple',
        'JavaScript' => 'yellow',
        'Python' => 'blue',
        'Java' => 'red',
        'HTML' => 'orange',
        'CSS' => 'pink',
        'TypeScript' => 'blue',
        'C++' => 'pink',
        'C#' => 'green',
        'Ruby' => 'red'
    ];
    return $colors[$language] ?? 'gray';
}

function getTimeAgo($date) {
    $timestamp = strtotime($date);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
    return floor($diff / 31536000) . ' years ago';
}

function getStatusColor($status) {
    $colors = [
        'open' => 'green',
        'closed' => 'red',
        'merged' => 'purple',
        'draft' => 'gray'
    ];
    return $colors[$status] ?? 'gray';
}

function getStatusText($status) {
    $texts = [
        'open' => 'Open',
        'closed' => 'Closed',
        'merged' => 'Merged',
        'draft' => 'Draft'
    ];
    return $texts[$status] ?? 'Unknown';
}

function getTypeColor($type) {
    $colors = [
        'bug' => 'red',
        'feature' => 'green',
        'enhancement' => 'blue',
        'question' => 'gray'
    ];
    return $colors[$type] ?? 'gray';
}
?>