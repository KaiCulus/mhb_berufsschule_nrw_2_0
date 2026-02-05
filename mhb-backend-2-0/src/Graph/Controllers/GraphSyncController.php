<?php

namespace Kai\MhbBackend20\Graph\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Graph\Services\MSFolderSyncService;
use Kai\MhbBackend20\Graph\GraphClient;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use Kai\MhbBackend20\Database\DB;
use PDO;

class GraphSyncController extends BaseController {
    
    private const ROLE_MAPPING = [
        'verwaltung' => 'MHB_BE_MSAL_ADMIN_VERWALTUNG',
        'paedagogik' => 'MHB_BE_MSAL_ADMIN_PAEDAGOGIK',
        'it_admin'   => 'MHB_BE_MSAL_ADMIN_IT',
    ];

    private $db;

    public function __construct() {
        $this->db = DB::getInstance()->getConnection();
    }

    /**
     * POST api/sync/execute/{type}
     */
    public function executeSync(string $type) {
        if (!isset(self::ROLE_MAPPING[$type])) {
            $this->errorResponse("Ungültiger Synchronisations-Typ: $type", 400);
        }

        $user = AuthMiddleware::check();
        $this->requireGroup(self::ROLE_MAPPING[$type]);

        // 1. Log-Eintrag erstellen (Status: running)
        $logId = $this->startSyncLog($type, $user['id']);

        try {
            // Zeitlimit erhöhen, da Syncs lange dauern können
            set_time_limit(300); 
            $client = new GraphClient();
            $service = new MSFolderSyncService($client);
            $service->syncByProfile($type);

            // 2. Log-Eintrag aktualisieren (Status: success)
            $this->endSyncLog($logId, 'success');

            $this->jsonResponse([
                'success' => true, 
                'message' => "Synchronisation für '$type' erfolgreich abgeschlossen."
            ]);
        } catch (\Exception $e) {
            // 3. Log-Eintrag im Fehlerfall (Status: error)
            $this->endSyncLog($logId, 'error', $e->getMessage());
            $this->errorResponse('Sync fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Erstellt einen initialen Log-Eintrag
     */
    private function startSyncLog(string $type, int $userId): int {
        $stmt = $this->db->prepare("
            INSERT INTO sync_logs (sync_type, triggered_by, status, started_at) 
            VALUES (?, ?, 'running', NOW())
        ");
        $stmt->execute([$type, $userId]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Schließt den Log-Eintrag ab
     */
    private function endSyncLog(int $logId, string $status, ?string $message = null): void {
        $stmt = $this->db->prepare("
            UPDATE sync_logs 
            SET status = ?, ended_at = NOW(), error_message = ? 
            WHERE id = ?
        ");
        $stmt->execute([$status, $message, $logId]);
    }
}