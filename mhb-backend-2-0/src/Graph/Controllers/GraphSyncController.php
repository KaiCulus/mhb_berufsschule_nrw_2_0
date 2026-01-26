<?php

namespace Kai\MhbBackend20\Graph\Controllers;

use Kai\MhbBackend20\Graph\Services\MSFolderSyncService;
use Kai\MhbBackend20\Graph\GraphClient;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware; // Import hinzugefügt

class GraphSyncController {
    
    private array $roleMapping;

    public function __construct() {
        $this->roleMapping = [
            'verwaltung' => $_ENV['MHB_BE_MSAL_ADMIN_VERWALTUNG'] ?? null,
            'paedagogik' => $_ENV['MHB_BE_MSAL_ADMIN_PAEDAGOGIK'] ?? null,
            'it_admin'   => $_ENV['MHB_BE_MSAL_ADMIN_IT'] ?? null,
        ];
    }

    public function getPermissions() {
        // Sicherheit: Nur wer eingeloggt ist, darf Permissions sehen
        AuthMiddleware::check(); 

        $userGroups = $_SESSION['user_groups'] ?? [];
        $permissions = [];

        foreach ($this->roleMapping as $roleKey => $groupId) {
            $permissions[$roleKey] = !empty($groupId) && in_array($groupId, $userGroups);
        }

        header('Content-Type: application/json');
        echo json_encode(['permissions' => $permissions]);
    }

    public function executeSync(string $type) {
        // Nutzt jetzt die Middleware für konsistente Prüfung
        AuthMiddleware::checkPermission($type);

        // Ab hier wissen wir: User ist eingeloggt UND in der richtigen Gruppe
        header('Content-Type: application/json');

        try {
            $client = new GraphClient();
            $service = new MSFolderSyncService($client);
            
            $service->syncByProfile($type);

            echo json_encode([
                'success' => true, 
                'message' => "Synchronisation für '$type' erfolgreich abgeschlossen."
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Sync fehlgeschlagen', 'details' => $e->getMessage()]);
        }
    }
}