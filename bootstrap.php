<?php
/**
 * bootstrap.php
 *
 * Application entry point (not the public one — that's public/index.php).
 *
 * Responsibilities:
 *  1. Autoload all classes (simple require-based loader — no Composer needed).
 *  2. Set up the global $storage instance (swap JsonFileStorage → PdoStorage here).
 *  3. Register all routes.
 *  4. Dispatch the request.
 *
 * Everything is explicit and traceable: no magic, no annotations.
 *
 * @package VolunteerContracts
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Error reporting (disable in production)
// ---------------------------------------------------------------------------
ini_set('display_errors', '1');
error_reporting(E_ALL);

// ---------------------------------------------------------------------------
// Paths
// ---------------------------------------------------------------------------
define('BASE_DIR',  __DIR__);
define('DATA_DIR',  BASE_DIR . '/data');
define('API_DIR',   BASE_DIR . '/api');
define('STOR_DIR',  BASE_DIR . '/storage');
define('TMPL_DIR',  BASE_DIR . '/templates');

// ---------------------------------------------------------------------------
// Simple class autoloader
// (Replace with Composer autoload if you add a composer.json later)
// ---------------------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    $dirs = [API_DIR, STOR_DIR, TMPL_DIR];
    foreach ($dirs as $dir) {
        $file = $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

// ---------------------------------------------------------------------------
// CORS headers (allow the frontend served from the same origin or localhost)
// ---------------------------------------------------------------------------
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: {$origin}");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------------------------------------------------------------------------
// Dependency injection
//
// $storage is a global so controllers can pick it up.
// For a proper DI container, replace this block with Pimple / PHP-DI / your own.
// ---------------------------------------------------------------------------
$storage = new JsonFileStorage(DATA_DIR);

// ---------------------------------------------------------------------------
// Route registration
// ---------------------------------------------------------------------------
$router = new Router();

// Workers
$router->get('/api/workers',              [WorkerController::class,   'index']);
$router->post('/api/workers',             [WorkerController::class,   'store']);
$router->get('/api/workers/{id}',         [WorkerController::class,   'show']);
$router->put('/api/workers/{id}',         [WorkerController::class,   'update']);
$router->delete('/api/workers/{id}',      [WorkerController::class,   'destroy']);

// Contracts
$router->get('/api/contracts',            [ContractController::class, 'index']);
$router->post('/api/contracts',           [ContractController::class, 'store']);
$router->post('/api/contracts/preview',   [ContractController::class, 'preview']);
$router->get('/api/contracts/{id}',       [ContractController::class, 'show']);
$router->get('/api/contracts/{id}/preview', [ContractController::class, 'showPreview']);
$router->put('/api/contracts/{id}',       [ContractController::class, 'update']);
$router->delete('/api/contracts/{id}',    [ContractController::class, 'destroy']);

// ---------------------------------------------------------------------------
// Dispatch
// ---------------------------------------------------------------------------
$router->dispatch();
