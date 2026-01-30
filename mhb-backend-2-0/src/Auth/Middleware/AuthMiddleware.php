<?php
namespace Kai\MhbBackend20\Auth\Middleware;

use Kai\MhbBackend20\Auth\Services\OAuthService;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;
use Kai\MhbBackend20\Database\DB;
use Kai\MhbBackend20\Common\Cipher;
use PDO;

class AuthMiddleware {
    /**
     * Prüft die Authentifizierung via Session ODER ID-Token.
     * @return array Die User-Daten inkl. db_id, name und email
     */
    public static function check(): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Weg: Bestehende Session
        if (isset($_SESSION['user']) && !empty($_SESSION['user']['id'])) {
            // Falls user da ist, aber groups fehlen (z.B. nach Session-Wiederherstellung), 
            // stellen wir sicher, dass sie verfügbar sind.
            if (!isset($_SESSION['user_groups'])) {
                $_SESSION['user_groups'] = $_SESSION['user']['groups'] ?? [];
            }
            return $_SESSION['user'];
        }

        // 2. Weg: Authorization Header
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            try {
                $service = new \Kai\MhbBackend20\Auth\Services\OAuthService();
                $userData = $service->validateIdToken($matches[1]);
                
                // WICHTIG: Hier werden Gruppen und db_id gesetzt
                $enrichedUser = self::enrichUserData($userData);
                
                $_SESSION['user'] = $enrichedUser;
                $_SESSION['user_groups'] = $enrichedUser['groups']; // Explizit für checkPermission
                
                return $enrichedUser;
            } catch (\Exception $e) {
                self::respondError(401, $e->getMessage());
            }
        }

        self::respondError(401, 'Keine gültige Sitzung oder Token gefunden.');
        return [];
    }

    /**
     * Reichert das Microsoft-Token-Array mit der DB-ID und entschlüsselten Namen an
     */
   private static function enrichUserData(array $msData): array {
        $db = DB::getInstance()->getConnection();
        $email = $msData['email'] ?? $msData['upn'];
        $emailHash = hash('sha256', $email);
        $encKey = $_ENV['APP_ENCRYPTION_KEY'];

        $stmt = $db->prepare("SELECT id, display_name_encrypted FROM users WHERE email_hash = ?");
        $stmt->execute([$emailHash]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            self::respondError(403, "Benutzerprofil in der Datenbank nicht gefunden.");
        }

        // WICHTIG: Die Gruppen müssen für checkPermission() verfügbar sein
        $groups = $msData['groups'] ?? [];
        $_SESSION['user_groups'] = $groups; // Direkt in die Session für checkPermission

        return [
            'id' => $record['id'],
            'name' => Cipher::decrypt($record['display_name_encrypted'], $encKey),
            'email' => $email,
            'groups' => $groups, // Auch im User-Array für das Frontend
            'oid' => $msData['oid'] ?? null
        ];
    }

    public static function checkPermission(string $type): void {
        $user = self::check(); // Stellt sicher, dass $_SESSION['user_groups'] befüllt ist

        $userGroups = $_SESSION['user_groups'] ?? $user['groups'] ?? [];
        
        // ENV Key muss genau matchen, z.B. MHB_BE_MSAL_ADMIN_VERWALTUNG
        $envKey = 'MHB_BE_MSAL_ADMIN_' . strtoupper($type);
        $requiredGroupId = $_ENV[$envKey] ?? null;

        if (!$requiredGroupId) {
            error_log("Warnung: $envKey ist nicht in der .env definiert!");
            self::respondError(500, "Server-Konfigurationsfehler.");
        }

        if (!in_array($requiredGroupId, $userGroups)) {
            self::respondError(403, "Zugriff verweigert: Fehlende Berechtigung für $type.");
        }
    }

    public static function isTicketProcessor(): bool {
        $user = self::check();
        $requiredGroupId = $_ENV['MHB_BE_MSAL_TICKETPROCESSORS'] ?? null;
        return in_array($requiredGroupId, $_SESSION['user_groups'] ?? []);
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