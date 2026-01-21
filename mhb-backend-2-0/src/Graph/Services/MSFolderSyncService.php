<?php

namespace Kai\MhbBackend20\Graph\Services;

use Kai\MhbBackend20\Graph\GraphClient;
use Kai\MhbBackend20\Database\DB;
use PDO;

class MSFolderSyncService {
    private $db;
    private $graphClient;
    private array $profiles;

    public function __construct(GraphClient $graphClient) {
        // Nutzt dein Singleton für die Datenbankverbindung
        $this->db = DB::getInstance()->getConnection();
        $this->graphClient = $graphClient;
        
        // Pfad zur Config: Von src/Graph/Services/ drei Ebenen hoch zu config/
        $config = require __DIR__ . '/../../../config/graph.php';
        $this->profiles = $config['sync_profiles'] ?? [];
    }

    /**
     * Startet den Sync für ein bestimmtes Profil aus config/graph.php
     */
    public function syncByProfile(string $profileKey): void {
        if (!isset($this->profiles[$profileKey])) {
            throw new \Exception("Profil '$profileKey' nicht in config/graph.php gefunden.");
        }

        $profile = $this->profiles[$profileKey];
        
        if (empty($profile['drive_id']) || empty($profile['folder_id'])) {
            throw new \Exception("Drive ID oder Folder ID für Profil '$profileKey' fehlt in der Konfiguration.");
        }

        // Start der Rekursion
        $this->fetchAndSaveRecursive(
            $profile['drive_id'], 
            $profile['folder_id'], 
            $profile['folder_id'] // Der Startordner ist sein eigener Parent im Root-Kontext
        );
    }

    /**
     * Kern-Logik: Holt Daten von Microsoft und spiegelt sie in die MariaDB
     */
    private function fetchAndSaveRecursive(string $driveId, string $folderId, string $parentId): void {
        // Holt die Items über den GraphClient
        $items = $this->graphClient->getDriveChildren($driveId, $folderId);

        foreach ($items as $item) {
            $isFolder = isset($item['folder']) ? 1 : 0;
            
            // Vorbereitung des SQL-Statements (Upsert)
            $stmt = $this->db->prepare("
                INSERT INTO documents (ms_id, parent_id, name_original, share_url, is_folder)
                VALUES (:ms_id, :parent_id, :name, :url, :is_folder)
                ON DUPLICATE KEY UPDATE 
                    parent_id = VALUES(parent_id),
                    name_original = VALUES(name_original),
                    share_url = VALUES(share_url),
                    is_folder = VALUES(is_folder),
                    last_synced = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                'ms_id'     => $item['id'],
                'parent_id' => $parentId,
                'name'      => $item['name'],
                'url'       => $item['webUrl'] ?? $item['web_url'] ?? null,
                'is_folder' => $isFolder
            ]);

            // Wenn das Item ein Ordner ist, gehen wir rekursiv eine Ebene tiefer
            if ($isFolder) {
                $this->fetchAndSaveRecursive($driveId, $item['id'], $item['id']);
            }
        }
    }
}