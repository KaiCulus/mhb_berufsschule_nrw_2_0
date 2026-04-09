<?php

namespace Kai\MhbBackend20\Graph\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Graph\Services\MSFolderSyncService;
use Kai\MhbBackend20\Graph\GraphClient;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use Kai\MhbBackend20\Database\DB;
use PDO;

/**
 * GraphSyncController
 *
 * Steuert die manuelle Synchronisation von SharePoint-Ordnern mit der Datenbank.
 *
 * Jeder Sync-Typ ist einer Berechtigungsgruppe zugeordnet — nur Mitglieder der
 * jeweiligen Gruppe dürfen den entsprechenden Sync auslösen.
 *
 * Jeder Sync-Lauf wird in der sync_logs Tabelle protokolliert (Start, Ende, Status, Fehler).
 */
class GraphSyncController extends BaseController
{
    /**
     * Zuordnung von Sync-Typ zu .env-Schlüssel der Berechtigungsgruppe.
     * Gleichzeitig Whitelist der erlaubten Sync-Typen.
     */
    private const ROLE_MAPPING = [
        'verwaltung' => 'MHB_BE_MSAL_ADMIN_VERWALTUNG',
        'common' => 'MHB_BE_MSAL_ADMIN_COMMON',
    ];

    private \PDO $db;

    public function __construct()
    {
        $this->db = DB::getInstance()->getConnection();
    }

    // =========================================================================
    // Public Endpoints
    // =========================================================================

    /**
     * POST api/sync/execute/{type}
     *
     * Löst eine Synchronisation für den angegebenen Typ aus.
     * Nur Mitglieder der zugehörigen Gruppe dürfen den Sync triggern.
     *
     * Ablauf:
     *   1. Authentifizierung und Autorisierung prüfen
     *   2. Typ gegen Whitelist validieren
     *   3. Sync-Log anlegen (Status: running)
     *   4. Sync durchführen
     *   5. Log abschließen (Status: success oder error)
     *
     * @param string $type Sync-Typ (muss in ROLE_MAPPING stehen)
     */
    public function executeSync(string $type): void
    {
        // 1. Authentifizierung zuerst — dann Typ-Validierung
        $user = AuthMiddleware::check();

        // 2. Typ gegen Whitelist prüfen
        if (!isset(self::ROLE_MAPPING[$type])) {
            $this->errorResponse("Ungültiger Synchronisations-Typ.", 400);
        }

        // 3. Gruppen-Autorisierung für diesen Sync-Typ
        $this->requireGroup(self::ROLE_MAPPING[$type]);

        // 4. Sync-Log anlegen
        $logId = $this->startSyncLog($type, $user['id']);

        try {
            // Zeitlimit erhöhen — Syncs können bei vielen Dateien länger dauern
            set_time_limit(300);

            $service = new MSFolderSyncService(new GraphClient());
            $service->syncByProfile($type);

            // 5a. Erfolg protokollieren
            $this->endSyncLog($logId, 'success');

            $this->jsonResponse([
                'success' => true,
                'message' => "Synchronisation für '{$type}' erfolgreich abgeschlossen.",
            ]);

        } catch (\Throwable $e) {
            // 5b. Fehler protokollieren — internes Detail nur im Log, nicht im Response
            $errorId = uniqid('sync_err_');
            error_log("[{$errorId}] Sync '{$type}' fehlgeschlagen: " . $e->getMessage());
            $this->endSyncLog($logId, 'error', $e->getMessage()); // Volltext ins Log

            $this->errorResponse("Synchronisation fehlgeschlagen. Referenz: {$errorId}", 500);
        }
    }

    /**
     * GET api/sync/get-permissions
     *
     * Gibt zurück welche Sync-Typen der aktuelle User ausführen darf.
     * Wird vom Frontend verwendet um Sync-Buttons zu zeigen oder zu verstecken.
     *
     * Antwort-Beispiel:
     *   { "permissions": { "verwaltung": true, "paedagogik": false, "it_admin": false } }
     */
    public function getPermissions(): void
    {
        AuthMiddleware::check();

        $permissions = [];
        foreach (self::ROLE_MAPPING as $type => $envKey) {
            $permissions[$type] = AuthMiddleware::hasGroup($envKey);
        }

        $this->jsonResponse(['permissions' => $permissions]);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Legt einen initialen Sync-Log-Eintrag mit Status 'running' an.
     *
     * @param string $type   Sync-Typ
     * @param int    $userId User der den Sync ausgelöst hat
     * @return int           ID des Log-Eintrags (wird für endSyncLog benötigt)
     */
    private function startSyncLog(string $type, int $userId): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO sync_logs (sync_type, triggered_by, status, started_at)
            VALUES (?, ?, 'running', NOW())
        ");
        $stmt->execute([$type, $userId]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Schließt einen Sync-Log-Eintrag ab.
     *
     * @param int         $logId   ID des Log-Eintrags
     * @param string      $status  'success' oder 'error'
     * @param string|null $message Fehlermeldung bei Status 'error'
     */
    private function endSyncLog(int $logId, string $status, ?string $message = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE sync_logs
            SET status = ?, ended_at = NOW(), error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $message, $logId]);
    }
}