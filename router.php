<?php
// Router script for PHP built-in server
// This handles static files and passes dynamic requests to CodeIgniter

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    // Get file extension
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    
    // Set appropriate content type
    $contentTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
    ];
    
    if (isset($contentTypes[$ext])) {
        header('Content-Type: ' . $contentTypes[$ext]);
    }
    
    return false; // Serve the file
}

// Pass everything else to CodeIgniter
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/public/index.php';
