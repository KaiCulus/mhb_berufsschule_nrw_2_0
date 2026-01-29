<?php

namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Database\DB;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use PDO;

class DocumentController {
    private $db;

    public function __construct() {
        $this->db = DB::getInstance()->getConnection();
    }

    /**
     * Holt alle aktiven Dokumente eines Scopes inklusive ihrer Aliase.
     */
    public function getByScope(string $scope) {
        // Sicherheit: Nur authentifizierte User dürfen Daten sehen
        AuthMiddleware::check();

        // Wir nutzen GROUP_CONCAT, um alle Aliase für die Suche in einem Feld zu sammeln
        // Die Aliase werden nur einbezogen, wenn sie mindestens 1 Vote haben
        $stmt = $this->db->prepare("
            SELECT 
                d.ms_id, 
                d.parent_id, 
                d.name_original, 
                d.share_url, 
                d.is_folder,
                (
                    SELECT GROUP_CONCAT(DISTINCT a.alias_text SEPARATOR '||')
                    FROM document_aliases a
                    INNER JOIN alias_votes v ON a.id = v.alias_id
                    WHERE a.document_ms_id = d.ms_id
                    AND a.alias_text IS NOT NULL 
                    AND a.alias_text != ''
                ) as alias_list
            FROM documents d
            WHERE d.sync_scope = :scope 
            AND d.deleted_at IS NULL 
            ORDER BY d.is_folder DESC, d.name_original ASC
        ");
        
        $stmt->execute(['scope' => $scope]);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Nachbearbeitung: Alias-String in ein Array umwandeln für das Frontend
        foreach ($docs as &$doc) {
            if (!empty($doc['alias_list'])) {
                $doc['aliases'] = explode('||', $doc['alias_list']);
            } else {
                $doc['aliases'] = [];
            }
            unset($doc['alias_list']); // Temporäres Hilfsfeld entfernen
        }

        header('Content-Type: application/json');
        echo json_encode($docs);
    }
}