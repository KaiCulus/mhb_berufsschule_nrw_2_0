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
        $this->db = DB::getInstance()->getConnection();
        $this->graphClient = $graphClient;
        
        // Hier require statt require_once wegen rekursion.
        $config = require ROOT_PATH . '/config/graph.php'; 
        $this->profiles = $config['sync_profiles'] ?? [];
    }

    public function syncByProfile(string $profileKey): void {
        if (!isset($this->profiles[$profileKey])) {
            throw new \Exception("Profil '$profileKey' nicht konfiguriert.");
        }

        $profile = $this->profiles[$profileKey];
        $syncTimestamp = date('Y-m-d H:i:s');

        // Prepare Statement EINMAL außerhalb der Rekursion für maximale Speed
        $upsertStmt = $this->db->prepare("
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

        $this->fetchAndSaveRecursive(
            $upsertStmt,
            $profile['drive_id'], 
            $profile['folder_id'], 
            'root', // Markierung für oberste Ebene
            $profileKey,
            $syncTimestamp 
        );

        $this->softDeleteMissingItems($profileKey, $syncTimestamp);
    }

    private function fetchAndSaveRecursive(
        \PDOStatement $stmt,
        string $driveId, 
        string $folderId, 
        string $parentId, 
        string $profileKey,
        string $syncTimestamp
    ): void {
        $items = $this->graphClient->getDriveChildren($driveId, $folderId);

        foreach ($items as $item) {
            $isFolder = isset($item['folder']) ? 1 : 0;
            
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
                // Rekursion nutzt das gleiche Statement weiter
                $this->fetchAndSaveRecursive($stmt, $driveId, $item['id'], $item['id'], $profileKey, $syncTimestamp);
            }
        }
    }

    private function softDeleteMissingItems(string $profileKey, string $syncTimestamp): void {
        try {
            // 1. Finde alle IDs, die wir gleich als gelöscht markieren werden
            // Wir brauchen diese IDs, um in den verknüpften Tabellen aufzuräumen
            $stmtFind = $this->db->prepare("
                SELECT ms_id FROM documents 
                WHERE sync_scope = :scope 
                AND last_synced < :startTime
                AND deleted_at IS NULL
            ");
            $stmtFind->execute(['scope' => $profileKey, 'startTime' => $syncTimestamp]);
            $idsToCleanup = $stmtFind->fetchAll(PDO::FETCH_COLUMN);

            if (empty($idsToCleanup)) return;

            // 2. Dokumente in 'documents' als gelöscht markieren (Soft-Delete)
            $stmtUpdate = $this->db->prepare("
                UPDATE documents 
                SET deleted_at = :now
                WHERE sync_scope = :scope 
                AND last_synced < :startTime
                AND deleted_at IS NULL
            ");
            $stmtUpdate->execute([
                'now'       => date('Y-m-d H:i:s'),
                'scope'     => $profileKey,
                'startTime' => $syncTimestamp
            ]);

            // 3. Manuelles "Cascade" für Soft-Delete durchführen
            // Da wir physisch nichts aus 'documents' löschen, müssen wir hier manuell ran:
            
            $placeholders = implode(',', array_fill(0, count($idsToCleanup), '?'));

            // Aliase löschen
            $stmtAlias = $this->db->prepare("DELETE FROM document_aliases WHERE document_ms_id IN ($placeholders)");
            $stmtAlias->execute($idsToCleanup);

            // Favoriten löschen (Heißt bei dir 'user_favorites' laut SQL)
            $stmtFav = $this->db->prepare("DELETE FROM user_favorites WHERE document_ms_id IN ($placeholders)");
            $stmtFav->execute($idsToCleanup);

            error_log("Sync Cleanup [$profileKey]: " . count($idsToCleanup) . " Dokumente archiviert. Aliase & Favoriten entfernt.");

        } catch (\PDOException $e) {
            error_log("Fehler beim Cleanup des Sync-Profils $profileKey: " . $e->getMessage());
        }
    }
}