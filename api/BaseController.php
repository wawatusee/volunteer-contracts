<?php
/**
 * BaseController
 *
 * Provides shared helpers for all API controllers:
 *  - JSON response emission
 *  - Request body parsing (JSON or form data)
 *  - Standard HTTP status helpers
 *
 * All controllers extend this class. To add authentication, rate-limiting,
 * or CORS in the future, do it here.
 *
 * @package VolunteerContracts\Controller
 */
abstract class BaseController
{
    // -------------------------------------------------------------------------
    // Response helpers
    // -------------------------------------------------------------------------

    /**
     * Emit a JSON success response and exit.
     *
     * @param mixed $data    The payload (array, scalar, or null)
     * @param int   $status  HTTP status code (default 200)
     */
    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Emit a JSON error response and exit.
     *
     * @param string|string[] $message  Error message or list of messages
     * @param int             $status   HTTP status code (default 400)
     */
    protected function error(string|array $message, int $status = 400): void
    {
        $this->json(['error' => $message], $status);
    }

    /**
     * Emit a 404 response.
     */
    protected function notFound(string $message = 'Not found'): void
    {
        $this->error($message, 404);
    }

    // -------------------------------------------------------------------------
    // Request helpers
    // -------------------------------------------------------------------------

    /**
     * Parse the request body as JSON, falling back to $_POST.
     * Throws if JSON is malformed.
     *
     * @return array  Associative array of input fields
     */
    protected function input(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw === '') {
                return [];
            }
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                return is_array($decoded) ? $decoded : [];
            } catch (\JsonException $e) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid JSON body: ' . $e->getMessage()]);
                exit;
            }
        }

        // Form-encoded or multipart
        return $_POST;
    }

    /**
     * Retrieve a single URL parameter from the route match map.
     *
     * @param array  $params  Route parameter map from Router
     * @param string $key
     * @return string         The value, or empty string
     */
    protected function param(array $params, string $key): string
    {
        return trim($params[$key] ?? '');
    }
}
