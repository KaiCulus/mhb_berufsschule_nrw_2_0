<?php
namespace Kai\MhbBackend20\Auth\Middleware;

use Kai\MhbBackend20\Auth\Services\OAuthService;

class AuthMiddleware {
    public static function check(): array {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            header('Content-Type: application/json', true, 401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $service = new OAuthService();
        return $service->validateIdToken($matches[1]);
    }
}
