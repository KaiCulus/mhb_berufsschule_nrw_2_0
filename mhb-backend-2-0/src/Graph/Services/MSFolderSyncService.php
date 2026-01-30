<?php

namespace Kai\MhbBackend20\Graph\Services;

use Kai\MhbBackend20\Graph\GraphClient;
use Kai\MhbBackend20\Database\DB;

class MSFolderSyncService {
    private $db;
    private $graphClient;
    private array $profiles;

    public function __construct(GraphClient $graphClient) {
        $this->db = DB::getInstance()->getConnection();
        $this->graphClient = $graphClient;
        $config = require_once __DIR__ . '/../../../config/graph.php';
        $this->profiles = $config['sync_profiles'] ?? [];
    }

    public function syncByProfile(string $profileKey): void {
        if (!isset($this->profiles[$profileKey])) {
            throw new \Exception("Profil '$profileKey' nicht konfiguriert.");
        }

        $profile = $this->profiles[$profileKey];
        
        // 1. Startzeitpunkt fixieren (Single Source of Truth)
        $syncTimestamp = date('Y-m-d H:i:s');

        // 2. Rekursiv synchronisieren
        // Wir geben $syncTimestamp mit, damit alle Items exakt diese Zeit bekommen
        $this->fetchAndSaveRecursive(
            $profile['drive_id'], 
            $profile['folder_id'], 
            $profile['folder_id'],
            $profileKey,
            $syncTimestamp 
        );

        // 3. Aufräumen
        $this->softDeleteMissingItems($profileKey, $syncTimestamp);
    }

    private function fetchAndSaveRecursive(
        string $driveId, 
        string $folderId, 
        string $parentId, 
        string $profileKey,
        string $syncTimestamp
    ): void {
        $items = $this->graphClient->getDriveChildren($driveId, $folderId);

        foreach ($items as $item) {
            $isFolder = isset($item['folder']) ? 1 : 0;
            
            // KORREKTUR: Wir nutzen im UPDATE-Teil jetzt VALUES(...) 
            // statt die Parameter (:parent_id etc.) erneut zu binden.
            // Das verhindert den "Invalid parameter number" Fehler.
            $stmt = $this->db->prepare("
                INSERT INTO documents (ms_id, parent_id, name_original, share_url, is_folder, sync_scope, last_synced, deleted_at)
                VALUES (:ms_id, :parent_id, :name, :url, :is_folder, :scope, :last_synced, NULL)
                ON DUPLICATE KEY UPDATE 
                    parent_id = VALUES(parent_id),
                    name_original = VALUES(name_original),
                    share_url = VALUES(share_url),
                    is_folder = VALUES(is_folder),
                    sync_scope = VALUES(sync_scope),
                    last_synced = VALUES(last_synced), 
                    deleted_at = NULL
            ");

            $stmt->execute([
                'ms_id'     => $item['id'],
                'parent_id' => $parentId,
                'name'      => $item['name'],
                'url'       => $item['webUrl'] ?? null,
                'is_folder' => $isFolder,
                'scope'     => $profileKey,
                'last_synced' => $syncTimestamp
            ]);

            if ($isFolder) {
                $this->fetchAndSaveRecursive($driveId, $item['id'], $item['id'], $profileKey, $syncTimestamp);
            }
        }
    }

    private function softDeleteMissingItems(string $profileKey, string $syncTimestamp): void {
        // Logik: Alles was NICHT exakt den aktuellen Zeitstempel hat (oder neuer ist), ist alt.
        // Da wir beim Update oben exakt $syncTimestamp setzen, sind alle aktiven Dateien = $syncTimestamp.
        // Alles was < $syncTimestamp ist, wurde in diesem Lauf nicht angefasst.
        
        $stmt = $this->db->prepare("
            UPDATE documents 
            SET deleted_at = :now
            WHERE sync_scope = :scope 
            AND last_synced < :startTime
            AND deleted_at IS NULL
        ");
        
        $stmt->execute([
            'now'       => date('Y-m-d H:i:s'), // Löschzeitpunkt ist "jetzt"
            'scope'     => $profileKey,
            'startTime' => $syncTimestamp       // Schwelle ist der Start des Laufs
        ]);
        
        //TODO: Methode entfernen wenn nichts mehr geloggt werden muss, da unnötig:
        $deletedCount = $stmt->rowCount();
        if ($deletedCount > 0) {
            // Optional: Loggen
            // error_log("Cleanup '$profileKey': $deletedCount Dokumente entfernt.");
        }
    }
}