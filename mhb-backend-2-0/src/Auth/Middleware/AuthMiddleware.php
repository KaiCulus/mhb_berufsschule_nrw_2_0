<?php
namespace Kai\MhbBackend20\Auth\Middleware;

use Kai\MhbBackend20\Auth\Services\OAuthService;
use Kai\MhbBackend20\Database\DB;
use Kai\MhbBackend20\Common\Cipher;
use PDO;

class AuthMiddleware {
    
    /**
     * Prüft die Authentifizierung via Session ODER ID-Token.
     */
    public static function check(): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Weg: Bestehende Session
        if (isset($_SESSION['user']) && !empty($_SESSION['user']['id'])) {
            return $_SESSION['user'];
        }

        // 2. Weg: Authorization Header (für API-Calls ohne Cookies)
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            try {
                $service = new OAuthService();
                // Validiert JWT und prüft initiale Lehrer-Gruppen-Mitgliedschaft
                $userData = $service->validateIdToken($matches[1]);
                
                // Reichert Daten mit DB-ID an und speichert alles in der Session
                $enrichedUser = self::enrichUserData($userData);
                
                $_SESSION['user'] = $enrichedUser;
                $_SESSION['user_groups'] = $enrichedUser['groups']; 
                
                return $enrichedUser;
            } catch (\Exception $e) {
                self::respondError(401, $e->getMessage());
            }
        }

        self::respondError(401, 'Keine gültige Sitzung oder Token gefunden.');
        return []; // Wird durch exit in respondError nie erreicht
    }

    /**
     * Holt die DB-ID und entschlüsselt den Namen
     */
    private static function enrichUserData(array $msData): array {
        $db = DB::getInstance()->getConnection();
        $email = $msData['email'] ?? $msData['upn'];
        $emailHash = hash('sha256', strtolower(trim($email)));
        $encKey = $_ENV['APP_ENCRYPTION_KEY'];

        $stmt = $db->prepare("SELECT id, display_name_encrypted FROM users WHERE email_hash = ?");
        $stmt->execute([$emailHash]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        // HIER IST DER FIX: 
        // Statt respondError(403) bei fehlendem Record, geben wir id => 0 zurück.
        return [
            'id' => $record ? (int)$record['id'] : 0, 
            'name' => $record ? Cipher::decrypt($record['display_name_encrypted'], $encKey) : ($msData['name'] ?? 'Neuer User'),
            'email' => $email,
            'groups' => $msData['groups'] ?? [],
            'oid' => $msData['oid'] ?? null
        ];
    }

    /**
     * Kern-Logik: Gibt true zurück, wenn der User in der Gruppe ist.
     */
    public static function hasGroup(string $envKey): bool {
        // Wir rufen check() nicht direkt auf, um Endlosschleifen zu vermeiden, 
        // prüfen aber, ob die Session valide ist.
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        if (!isset($_SESSION['user'])) {
            $user = self::check(); // Triggert Auth-Check falls noch nicht geschehen
        } else {
            $user = $_SESSION['user'];
        }

        $userGroups = $_SESSION['user_groups'] ?? $user['groups'] ?? [];
        $requiredGroupId = $_ENV[$envKey] ?? null;

        if (!$requiredGroupId) {
            error_log("Warnung: Berechtigungsgruppe $envKey fehlt in der .env!");
            return false;
        }

        return in_array($requiredGroupId, $userGroups);
    }

    /**
     * Wächter-Logik für Controller
     */
    public static function requireGroup(string $envKey): void {
        if (!self::hasGroup($envKey)) {
            self::respondError(403, "Zugriff verweigert: Sie benötigen die Berechtigung für $envKey.");
        }
    }

    private static function respondError(int $code, string $message): void {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'status' => 'error',
            'code' => $code,
            'message' => $message
        ]);
        exit;
    }
}