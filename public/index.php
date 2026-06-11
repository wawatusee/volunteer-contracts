<?php
/**
 * public/index.php
 *
 * Single entry point for both the REST API (/api/*) and the SPA shell.
 *
 * URL routing logic:
 *  - /api/*  → bootstrap.php (JSON API)
 *  - /*      → serve the SPA shell (index.html)
 *
 * Apache / nginx rewrite rules (see .htaccess) direct all requests here.
 * For `php -S localhost:8080 public/index.php` this file is the router script.
 */

declare(strict_types=1);

$requestUri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

// ---------------------------------------------------------
// API requests → bootstrap the PHP REST layer
// ---------------------------------------------------------
if (str_starts_with($requestUri, '/api/')) {
    require __DIR__ . '/../bootstrap.php';
    exit;
}

// ---------------------------------------------------------
// Static asset pass-through when using php -S
// ---------------------------------------------------------
$publicDir = __DIR__;
$filePath  = $publicDir . $requestUri;

if ($requestUri !== '/' && is_file($filePath)) {
    return false; // let the built-in server serve the file
}

// ---------------------------------------------------------
// Everything else → SPA shell
// ---------------------------------------------------------
require __DIR__ . '/shell.php';
