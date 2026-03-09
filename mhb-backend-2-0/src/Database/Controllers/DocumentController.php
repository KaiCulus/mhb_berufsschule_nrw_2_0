<?php

namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Database\DB;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use PDO;

class DocumentController extends BaseController {
    
    // 1. Rollen-Mapping für die Scopes
    private const SCOPE_PERMISSIONS = [
        'admin_verwaltung' => 'MHB_BE_MSAL_ADMIN_VERWALTUNG',
        'verwaltung'    => 'MHB_BE_MSAL_TEACHER_ACCESS_GROUP'
    ];

    private $db;

    public function __construct() {
        $this->db = DB::getInstance()->getConnection();
    }

    /**
     * GET api/documents/{scope}
     * Holt alle aktiven Dokumente eines Scopes inklusive ihrer Aliase.
     */
    public function getByScope(string $scope) {
        AuthMiddleware::check();

        if (isset(self::SCOPE_PERMISSIONS[$scope])) {
            $this->requireGroup(self::SCOPE_PERMISSIONS[$scope]);
        }

        // Optimierte Abfrage: 
        // Wir holen die Aliase sortiert nach Beliebtheit (Anzahl Votes)
        $stmt = $this->db->prepare("
            SELECT 
                d.ms_id, 
                d.parent_id, 
                d.name_original, 
                d.share_url, 
                d.is_folder,
                (
                    SELECT GROUP_CONCAT(sub.alias_text ORDER BY sub.vote_count DESC SEPARATOR '||')
                    FROM (
                        SELECT a.alias_text, a.document_ms_id, COUNT(v.alias_id) as vote_count
                        FROM document_aliases a
                        LEFT JOIN alias_votes v ON a.id = v.alias_id
                        GROUP BY a.id
                    ) sub
                    WHERE sub.document_ms_id = d.ms_id
                    AND sub.alias_text > ''
                ) as alias_list
            FROM documents d
            WHERE d.sync_scope = :scope 
            AND d.deleted_at IS NULL 
            ORDER BY d.is_folder DESC, d.name_original ASC
        ");
        
        $stmt->execute(['scope' => $scope]);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($docs as &$doc) {
            // Explode mit Filter, um leere Einträge zu vermeiden
            $doc['aliases'] = !empty($doc['alias_list']) 
                ? explode('||', $doc['alias_list']) 
                : [];
            
            // Typen-Korrektur für das Frontend (Boolean statt 0/1)
            $doc['is_folder'] = (bool)$doc['is_folder'];
            
            unset($doc['alias_list']); 
        }

        $this->jsonResponse($docs);
    }
}