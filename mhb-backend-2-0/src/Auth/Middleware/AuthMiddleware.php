<?php
namespace Kai\MhbBackend20\Auth\Middleware;

use Kai\MhbBackend20\Auth\Services\OAuthService;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;

class AuthMiddleware {
    public static function check(): array {
        $headers = getallheaders();
        // Case-insensitivity Fix für verschiedene Server-Umgebungen
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            header('Content-Type: application/json', true, 401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        try {
            $service = new OAuthService();
            return $service->validateIdToken($matches[1]);
        } catch (OAuthException $e) {
            // Nutze den Status-Code der Exception (401 oder 403)
            http_response_code($e->getCode());
            header('Content-Type: application/json');
            echo json_encode([
                'error' => ($e->getCode() === 403 ? 'Forbidden' : 'Unauthorized'),
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }
}