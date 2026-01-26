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
     * Holt alle aktiven Dokumente eines Scopes.
     */
    public function getByScope(string $scope) {
        // Sicherheit: Nur authentifizierte User dürfen Daten sehen
        AuthMiddleware::check();

        $stmt = $this->db->prepare("
            SELECT ms_id, parent_id, name_original, share_url, is_folder 
            FROM documents 
            WHERE sync_scope = :scope 
            AND deleted_at IS NULL 
            ORDER BY is_folder DESC, name_original ASC
        ");
        
        $stmt->execute(['scope' => $scope]);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($docs);
    }
}