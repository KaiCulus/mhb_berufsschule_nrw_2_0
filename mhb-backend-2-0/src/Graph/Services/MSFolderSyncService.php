<?php

namespace Kai\MhbBackend20\Graph\Services;

use Kai\MhbBackend20\Graph\GraphClient;
use Kai\MhbBackend20\Database\DB;
use PDO;

/**
 * MSFolderSyncService
 *
 * Synchronisiert SharePoint-Ordnerstrukturen rekursiv mit der lokalen Datenbank.
 *
 * Ablauf pro Sync-Lauf:
 *   1. Alle Items des konfigurierten Ordners via Graph API abrufen (rekursiv)
 *   2. Jeden Item per UPSERT in die 'documents' Tabelle schreiben
 *   3. Items die im aktuellen Lauf nicht mehr gesehen wurden → Soft-Delete
 *   4. Verknüpfte Daten (Aliase, Favoriten) gelöschter Dokumente bereinigen
 *
 * Performance-Optimierung:
 *   Das PDO-Statement für den UPSERT wird einmal vorbereitet und für alle
 *   Items (auch über Rekursionsebenen) wiederverwendet — kein erneutes Prepare
 *   bei jedem Item.
 *
 * Soft-Delete Strategie:
 *   Dokumente werden nicht physisch gelöscht sondern mit deleted_at markiert.
 *   Das Zeitstempel-Vergleich (last_synced < syncTimestamp) identifiziert
 *   Items die im aktuellen Lauf nicht mehr in SharePoint vorhanden waren.
 */
class MSFolderSyncService
{
    private \PDO $db;
    private GraphClient $graphClient;
    private array $profiles;

    public function __construct(GraphClient $graphClient)
    {
        $this->db          = DB::getInstance()->getConnection();
        $this->graphClient = $graphClient;

        // require statt require_once — require_once würde beim zweiten Aufruf
        // das gecachte Ergebnis des ersten Aufrufs zurückgeben statt ein neues Array
        $config          = require ROOT_PATH . '/config/graph.php';
        $this->profiles  = $config['sync_profiles'] ?? [];
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Führt einen vollständigen Sync für das angegebene Profil durch.
     *
     * @param string $profileKey Profil-Schlüssel aus graph.php (z.B. 'verwaltung')
     * @throws \InvalidArgumentException Wenn das Profil nicht konfiguriert ist
     * @throws \RuntimeException         Bei Graph API oder Datenbankfehlern
     */
    public function syncByProfile(string $profileKey): void
    {
        if (!isset($this->profiles[$profileKey])) {
            throw new \InvalidArgumentException("Sync-Profil '{$profileKey}' nicht in graph.php konfiguriert.");
        }

        $profile       = $this->profiles[$profileKey];
        $syncTimestamp = date('Y-m-d H:i:s');

        // Statement einmal vorbereiten — wird für alle Items in der Rekursion wiederverwendet
        $upsertStmt = $this->db->prepare("
            INSERT INTO documents
                (ms_id, parent_id, name_original, share_url, is_folder, sync_scope, last_synced, deleted_at)
            VALUES
                (:ms_id, :parent_id, :name, :url, :is_folder, :scope, :last_synced, NULL)
            ON DUPLICATE KEY UPDATE
                parent_id     = VALUES(parent_id),
                name_original = VALUES(name_original),
                share_url     = VALUES(share_url),
                is_folder     = VALUES(is_folder),
                sync_scope    = VALUES(sync_scope),
                last_synced   = VALUES(last_synced),
                deleted_at    = NULL
        ");

        // Rekursiv alle Ordner und Dateien abrufen und speichern
        $this->fetchAndSaveRecursive(
            $upsertStmt,
            $profile['drive_id'],
            $profile['folder_id'],
            'root',        // Markierung für die oberste Ebene
            $profileKey,
            $syncTimestamp
        );

        // Items die im Sync nicht gesehen wurden als gelöscht markieren
        $this->softDeleteMissingItems($profileKey, $syncTimestamp);

        error_log("MSFolderSyncService: Sync '{$profileKey}' abgeschlossen.");
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Ruft alle Kind-Elemente eines Ordners ab und speichert sie per UPSERT.
     * Ruft sich selbst rekursiv für Unterordner auf.
     *
     * @param \PDOStatement $stmt          Vorbereitetes UPSERT-Statement
     * @param string        $driveId       Drive-ID aus dem Sync-Profil
     * @param string        $folderId      Aktueller Ordner (Graph Item-ID)
     * @param string        $parentId      Parent-ID für die DB ('root' auf oberster Ebene)
     * @param string        $profileKey    Sync-Profil-Schlüssel (wird als sync_scope gespeichert)
     * @param string        $syncTimestamp Zeitstempel des aktuellen Sync-Laufs
     */
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
                'ms_id'       => $item['id'],
                'parent_id'   => $parentId,
                'name'        => $item['name'],
                'url'         => $item['webUrl'] ?? null,
                'is_folder'   => $isFolder,
                'scope'       => $profileKey,
                'last_synced' => $syncTimestamp,
            ]);

            // Für Ordner: Rekursiv die Kinder laden
            if ($isFolder) {
                $this->fetchAndSaveRecursive(
                    $stmt,
                    $driveId,
                    $item['id'],  // Kind-Ordner als nächster Eltern-Ordner
                    $item['id'],  // Item selbst wird parent_id der Kinder
                    $profileKey,
                    $syncTimestamp
                );
            }
        }
    }

    /**
     * Markiert Dokumente als gelöscht die im aktuellen Sync nicht mehr gesehen wurden.
     *
     * Identifikation: last_synced < syncTimestamp (wurden im aktuellen Lauf nicht aktualisiert)
     *
     * Hinweis zum Timing:
     *   Zwischen fetchAndSaveRecursive() und softDeleteMissingItems() könnten Requests
     *   noch die alten Dokumente sehen. Bei Sync-Dauern von typisch <30s ist das
     *   akzeptabel — für Echtzeit-Konsistenz wäre eine Transaktion nötig.
     *
     * Fehlerverhalten:
     *   PDO-Fehler werden geloggt und weitergeworfen — ein Cleanup-Fehler soll
     *   den Sync-Log auf 'error' setzen statt still ignoriert zu werden.
     *
     * @param string $profileKey    Sync-Profil-Schlüssel
     * @param string $syncTimestamp Zeitstempel des aktuellen Sync-Laufs
     * @throws \RuntimeException Bei Datenbankfehlern während des Cleanups
     */
    private function softDeleteMissingItems(string $profileKey, string $syncTimestamp): void
    {
        // 1. IDs der zu löschenden Dokumente ermitteln
        //    (für das manuelle Cascade auf Aliase und Favoriten)
        $stmtFind = $this->db->prepare("
            SELECT ms_id FROM documents
            WHERE sync_scope  = :scope
              AND last_synced < :startTime
              AND deleted_at IS NULL
        ");
        $stmtFind->execute(['scope' => $profileKey, 'startTime' => $syncTimestamp]);
        $idsToCleanup = $stmtFind->fetchAll(PDO::FETCH_COLUMN);

        if (empty($idsToCleanup)) {
            error_log("MSFolderSyncService: Cleanup '{$profileKey}' — keine veralteten Einträge.");
            return;
        }

        // 2. Soft-Delete: deleted_at setzen statt physisch löschen
        //    Erhält die Datensätze für Audit-Zwecke und verhindert Datenverlust
        $this->db->prepare("
            UPDATE documents
            SET deleted_at = :now
            WHERE sync_scope  = :scope
              AND last_synced < :startTime
              AND deleted_at IS NULL
        ")->execute([
            'now'       => date('Y-m-d H:i:s'),
            'scope'     => $profileKey,
            'startTime' => $syncTimestamp,
        ]);

        // 3. Manuelles Cascade für verknüpfte Tabellen
        //    Da Dokumente nur soft-gelöscht werden, greift kein FK-Cascade —
        //    Aliase und Favoriten werden hier manuell bereinigt.
        $placeholders = implode(',', array_fill(0, count($idsToCleanup), '?'));

        $this->db->prepare("DELETE FROM document_aliases WHERE document_ms_id IN ({$placeholders})")
                 ->execute($idsToCleanup);

        $this->db->prepare("DELETE FROM user_favorites WHERE document_ms_id IN ({$placeholders})")
                 ->execute($idsToCleanup);

        error_log(sprintf(
            "MSFolderSyncService: Cleanup '%s' — %d Dokumente archiviert, Aliase & Favoriten entfernt.",
            $profileKey,
            count($idsToCleanup)
        ));
    }
}