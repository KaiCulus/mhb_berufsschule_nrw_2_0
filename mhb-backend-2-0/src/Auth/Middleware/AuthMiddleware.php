<?php

namespace Kai\MhbBackend20\Auth\Middleware;

use Kai\MhbBackend20\Auth\Services\OAuthService;
use Kai\MhbBackend20\Database\DB;
use Kai\MhbBackend20\Common\Cipher;
use PDO;

/**
 * AuthMiddleware
 *
 * Zentraler Authentifizierungs- und Autorisierungs-Wächter.
 *
 * Authentifizierung (check):
 *   Prüft in zwei Stufen, ob ein Request berechtigt ist:
 *     1. Bestehende PHP-Session (Cookie-basiert, Standardfall im Browser)
 *     2. Bearer-Token im Authorization-Header (für programmatische API-Calls)
 *
 * Autorisierung (hasGroup / requireGroup):
 *   Prüft ausschließlich auf bereits befüllten Session-Daten.
 *   Löst keinen neuen Auth-Check aus, um Endlosschleifen zu vermeiden.
 */
class AuthMiddleware
{
    // =========================================================================
    // Authentifizierung
    // =========================================================================

    /**
     * Prüft ob der aktuelle Request authentifiziert ist.
     *
     * Gibt die User-Daten als Array zurück oder beendet den Request
     * mit einem 401-Fehler, falls keine gültige Sitzung gefunden wird.
     *
     * @return array User-Daten (id, name, email, groups, oid)
     */
    public static function check(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Weg 1: Bestehende Session — schnellster Pfad, kein DB-Hit nötig
        if (isset($_SESSION['user']) && !empty($_SESSION['user']['id'])) {
            return $_SESSION['user'];
        }

        // Weg 2: Bearer-Token im Authorization-Header
        // Wird verwendet wenn kein Session-Cookie vorhanden ist (z.B. API-Calls)
        $headers    = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            try {
                $service  = new OAuthService();
                $userData = $service->validateIdToken($matches[1]);

                // DB-ID laden und Session für Folge-Requests befüllen
                $enrichedUser = self::enrichUserData($userData);

                $_SESSION['user']        = $enrichedUser;
                $_SESSION['user_groups'] = $enrichedUser['groups'];

                return $enrichedUser;

            } catch (\Exception $e) {
                self::respondError(401, $e->getMessage());
            }
        }

        self::respondError(401, 'Keine gültige Sitzung oder Token gefunden.');
        return []; // Wird durch exit in respondError nie erreicht
    }

    // =========================================================================
    // Autorisierung
    // =========================================================================

    /**
     * Prüft ob der eingeloggte User Mitglied einer bestimmten Azure-Gruppe ist.
     *
     * Liest ausschließlich aus der bestehenden Session — löst keinen neuen
     * Auth-Check aus. Gibt false zurück wenn keine Session aktiv ist, anstatt
     * einen neuen Check zu triggern (verhindert Endlosschleifen).
     *
     * @param string $envKey .env-Schlüssel der Gruppen-ID, z.B. 'MHB_BE_MSAL_ADMIN_VERWALTUNG'
     * @return bool true wenn der User in der Gruppe ist
     */
    public static function hasGroup(string $envKey): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            error_log('AuthMiddleware::hasGroup() — Session nicht aktiv.');
            return false;
        }

        if (!isset($_SESSION['user'])) {
            error_log('AuthMiddleware::hasGroup() — Kein User in der Session.');
            return false;
        }

        $requiredGroupId = $_ENV[$envKey] ?? null;

        if (!$requiredGroupId) {
            error_log("AuthMiddleware::hasGroup() — Gruppe '{$envKey}' fehlt in der .env!");
            return false;
        }

        $userGroups = $_SESSION['user_groups'] ?? $_SESSION['user']['groups'] ?? [];

        return in_array($requiredGroupId, $userGroups, strict: true);
    }

    /**
     * Erzwingt Gruppen-Mitgliedschaft — bricht den Request mit 403 ab wenn nicht erfüllt.
     *
     * Verwendung in Controllern:
     *   $this->requireGroup('MHB_BE_MSAL_ADMIN_VERWALTUNG');
     *
     * @param string $envKey .env-Schlüssel der Gruppen-ID
     */
    public static function requireGroup(string $envKey): void
    {
        if (!self::hasGroup($envKey)) {
            self::respondError(403, "Zugriff verweigert: Berechtigung '{$envKey}' erforderlich.");
        }
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Reichert Microsoft-Token-Daten mit der internen Datenbank-ID an.
     *
     * Wird nur beim Bearer-Token-Pfad aufgerufen, wenn noch keine Session existiert.
     * Schlägt der DB-Lookup fehl (User noch nie via OAuth eingeloggt), wird eine
     * Exception geworfen — id=0 als stiller Fallback ist bewusst vermieden, da
     * dies zu Datenverfälschungen in anderen Controllern führen würde.
     *
     * @param array $msData Rohe User-Daten aus dem validierten JWT
     * @return array Angereicherte User-Daten inkl. DB-ID und entschlüsseltem Namen
     * @throws \RuntimeException Wenn der User nicht in der DB gefunden wird
     */
    private static function enrichUserData(array $msData): array
    {
        $email     = $msData['email'] ?? $msData['upn'];
        $emailHash = hash('sha256', strtolower(trim($email)));
        $encKey    = $_ENV['APP_ENCRYPTION_KEY'];

        $db   = DB::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT id, display_name_encrypted FROM users WHERE email_hash = ?');
        $stmt->execute([$emailHash]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        // Kein Datensatz = User hat sich noch nie via OAuth-Callback eingeloggt.
        // Wir erstellen keinen neuen User hier, da das ausschließlich Aufgabe
        // des OAuthControllers (syncUserWithDatabase) ist.
        if (!$record) {
            throw new \RuntimeException(
                'User nicht in der Datenbank gefunden. Bitte zuerst den OAuth-Login durchführen.'
            );
        }

        return [
            'id'     => (int) $record['id'],
            'name'   => Cipher::decrypt($record['display_name_encrypted'], $encKey),
            'email'  => $email,
            'groups' => $msData['groups'] ?? [],
            'oid'    => $msData['oid'] ?? null,
        ];
    }

    /**
     * Sendet eine JSON-Fehlerantwort und beendet den Request.
     *
     * @param int    $code    HTTP-Statuscode (401, 403, etc.)
     * @param string $message Fehlermeldung (wird an den Client gesendet)
     */
    private static function respondError(int $code, string $message): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json');
        }

        echo json_encode([
            'status'  => 'error',
            'code'    => $code,
            'message' => $message,
        ]);

        exit;
    }
}