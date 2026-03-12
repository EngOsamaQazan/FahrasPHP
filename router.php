<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
if ($uri === '') $uri = '/';

if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false;
}

if ($uri !== '/' && !pathinfo($uri, PATHINFO_EXTENSION)) {
    if (file_exists(__DIR__ . $uri . '.php')) {
        require __DIR__ . $uri . '.php';
        return true;
    }
    if (is_dir(__DIR__ . $uri) && file_exists(__DIR__ . $uri . '/index.php')) {
        require __DIR__ . $uri . '/index.php';
        return true;
    }
}

if ($uri === '/') {
    require __DIR__ . '/index.php';
    return true;
}

return false;
