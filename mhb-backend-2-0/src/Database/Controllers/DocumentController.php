<?php

namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Database\DB;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use PDO;

class DocumentController extends BaseController {
    
    // 1. Rollen-Mapping für die Scopes
    private const SCOPE_PERMISSIONS = [
        'verwaltung' => 'MHB_BE_MSAL_ADMIN_VERWALTUNG',
        'studium'    => 'MHB_BE_MSAL_ADMIN_STUDIUM'
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
        // 2. Sicherheit: Authentifizierung prüfen
        AuthMiddleware::check();

        // 3. Berechtigungs-Check: Falls der Scope geschützt ist, Gruppe erzwingen
        if (isset(self::SCOPE_PERMISSIONS[$scope])) {
            $this->requireGroup(self::SCOPE_PERMISSIONS[$scope]);
        }

        // 4. Datenbankabfrage
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

        // 5. Nachbearbeitung (Transformation)
        foreach ($docs as &$doc) {
            $doc['aliases'] = !empty($doc['alias_list']) 
                ? explode('||', $doc['alias_list']) 
                : [];
            unset($doc['alias_list']); 
        }

        // 6. Einheitliche Antwort
        $this->jsonResponse($docs);
    }
}