<?php
/**
 * Vercel Serverless Front Controller for GPAMS PHP Application
 * Routes all PHP page and API requests through a single serverless function
 * while preserving relative paths, working directories, and $_SERVER variables.
 */

$projectRoot = realpath(__DIR__ . '/..');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$relPath = ltrim($uri, '/');

if ($relPath === '' || $relPath === '/') {
    $relPath = 'index.php';
} elseif (is_dir($projectRoot . '/' . rtrim($relPath, '/'))) {
    $relPath = rtrim($relPath, '/') . '/index.php';
} elseif (!str_ends_with($relPath, '.php') && is_file($projectRoot . '/' . $relPath . '.php')) {
    $relPath .= '.php';
}

$targetFile = realpath($projectRoot . '/' . $relPath);

// Security check: ensure target file is inside projectRoot and is a .php file
if (
    !$targetFile ||
    !str_starts_with($targetFile, $projectRoot) ||
    !is_file($targetFile) ||
    !str_ends_with(strtolower($targetFile), '.php')
) {
    http_response_code(404);
    echo "404 Not Found";
    exit;
}

// Prevent infinite recursion if /api/index.php is requested directly
if ($targetFile === __FILE__) {
    $targetFile = $projectRoot . '/index.php';
    $relPath = 'index.php';
}

// Emulate standard Apache/PHP environment variables for sidebar & auth helpers
$_SERVER['SCRIPT_NAME'] = '/' . str_replace('\\', '/', $relPath);
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['SCRIPT_FILENAME'] = $targetFile;

chdir(dirname($targetFile));
require $targetFile;
