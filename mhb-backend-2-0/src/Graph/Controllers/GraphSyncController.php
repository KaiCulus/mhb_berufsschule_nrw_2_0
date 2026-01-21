<?php

namespace Kai\MhbBackend20\Graph\Controllers;

use Kai\MhbBackend20\Graph\Services\MSFolderSyncService;
use Kai\MhbBackend20\Graph\GraphClient;

class GraphSyncController {
    
    private array $roleMapping;

    public function __construct() {
        // Dieses Mapping verbindet eine "Aktion" mit einer Gruppen-ID aus der .env
        // Später kannst du hier einfach weitere Zeilen hinzufügen.
        $this->roleMapping = [
            'verwaltung' => $_ENV['MHB_BE_MSAL_ADMIN_VERWALTUNG'],
            'paedagogik' => $_ENV['MHB_BE_MSAL_ADMIN_PAEDAGOGIK'] ?? null,
            'it_admin'   => $_ENV['MHB_BE_MSAL_ADMIN_IT'] ?? null,
        ];
    }

    /**
     * Gibt eine Liste aller Berechtigungen des aktuellen Users zurück.
     * Das Frontend kann dies nutzen, um Buttons ein/auszublenden.
     */
    public function getPermissions() {
        $userGroups = $_SESSION['user_groups'] ?? [];
        $permissions = [];

        foreach ($this->roleMapping as $roleKey => $groupId) {
            $permissions[$roleKey] = !empty($groupId) && in_array($groupId, $userGroups);
        }

        header('Content-Type: application/json');
        echo json_encode(['permissions' => $permissions]);
    }

    /**
     * Generischer Sync-Endpoint
     * URL-Beispiel: /api/sync/execute/verwaltung
     */
    public function executeSync(string $type) {
        header('Content-Type: application/json');
        $userGroups = $_SESSION['user_groups'] ?? [];
        $requiredGroupId = $this->roleMapping[$type] ?? null;

        // 1. Validierung: Existiert der Typ und hat der User die Gruppe?
        if (!$requiredGroupId || !in_array($requiredGroupId, $userGroups)) {
            http_response_code(403);
            echo json_encode(['error' => 'Nicht autorisiert für Sync-Typ: ' . $type]);
            return;
        }

        try {
            $client = new GraphClient();
            $service = new MSFolderSyncService($client);
            
            // Nutzt den $type direkt als Profil-Key für den Service
            $service->syncByProfile($type);

            echo json_encode([
                'success' => true, 
                'message' => "Synchronisation für '$type' erfolgreich abgeschlossen."
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}