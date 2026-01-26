<?php
namespace Kai\MhbBackend20\Auth\Middleware;

use Kai\MhbBackend20\Auth\Services\OAuthService;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;

class AuthMiddleware {
    /**
     * Prüft die Authentifizierung via Session ODER ID-Token.
     * @return array Die User-Daten
     */
    public static function check(): array {
        // 1. Weg: Bestehende Session (Schnellster Weg für Browser-Requests)
        if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
            return $_SESSION['user'];
        }

        // 2. Weg: Authorization Header (Falls keine Session da ist, z.B. API-Clients)
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            try {
                $service = new OAuthService();
                $userData = $service->validateIdToken($matches[1]);
                
                // Optional: Session für diesen User "hydrieren", falls noch nicht geschehen
                $_SESSION['user'] = $userData;
                return $userData;
            } catch (OAuthException $e) {
                self::respondError($e->getCode(), $e->getMessage());
            }
        }

        // Wenn weder Session noch gültiges Token gefunden wurde
        self::respondError(401, 'Keine gültige Sitzung oder Token gefunden.');
        return [];
    }

    /**
     * Prüft explizit, ob der User eine bestimmte Berechtigung hat.
     */
    public static function checkPermission(string $type): void {
        self::check(); // Erstmal grundsätzlich einloggen

        $userGroups = $_SESSION['user_groups'] ?? [];
        // Wir nutzen die gleiche Logik wie im Mapping des Controllers
        $requiredGroupId = $_ENV['MHB_BE_MSAL_ADMIN_' . strtoupper($type)] ?? null;

        if (!$requiredGroupId || !in_array($requiredGroupId, $userGroups)) {
            self::respondError(403, "Zugriff verweigert: Fehlende Berechtigung für $type.");
        }
    }

    private static function respondError(int $code, string $message): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => ($code === 403 ? 'Forbidden' : 'Unauthorized'),
            'message' => $message
        ]);
        exit;
    }
}