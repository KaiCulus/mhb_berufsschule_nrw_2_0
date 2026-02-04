<?php

namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Database\DB;
use PDO;

class AliasController extends BaseController {
    
    /**
     * GET api/aliases/{docId}/{userId}
     * Parameter kommen via Router (Regex-Matches)
     */
    public function getAliases($docId, $userId) {
        $db = DB::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT a.*, 
                   (SELECT COUNT(*) FROM alias_votes v WHERE v.alias_id = a.id) as vote_count,
                   (SELECT COUNT(*) FROM alias_votes v WHERE v.alias_id = a.id AND v.user_id = :userId) as user_voted
            FROM document_aliases a 
            WHERE a.document_ms_id = :docId
            ORDER BY vote_count DESC
        ");
        
        $stmt->execute([
            'docId' => $docId, 
            'userId' => (int)$userId
        ]);
        
        $this->jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * POST api/aliases
     */
    public function addAlias() {
        // Nutzen der neuen Validierung aus dem BaseController
        $data = $this->validateRequest([
            'docId'     => 'string',
            'aliasText' => 'string',
            'userId'    => 'int'
        ]);

        $db = DB::getInstance()->getConnection();
        
        // Input bereinigen (XSS Schutz für den Alias-Text)
        $cleanAlias = $this->sanitize($data['aliasText']);

        $stmt = $db->prepare("
            INSERT INTO document_aliases (document_ms_id, alias_text, created_by) 
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([
            $data['docId'], 
            $cleanAlias, 
            (int)$data['userId']
        ]);
        
        $this->jsonResponse([
            'status' => 'success', 
            'id' => $db->lastInsertId()
        ], 201);
    }

    /**
     * POST api/aliases/vote
     */
    public function toggleVote() {
        // Validierung der IDs
        $data = $this->validateRequest([
            'aliasId' => 'int',
            'userId'  => 'int'
        ]);

        $db = DB::getInstance()->getConnection();
        $aliasId = (int)$data['aliasId'];
        $userId  = (int)$data['userId'];
        
        // Check ob Vote bereits existiert
        $stmt = $db->prepare("SELECT 1 FROM alias_votes WHERE alias_id = ? AND user_id = ?");
        $stmt->execute([$aliasId, $userId]);
        
        if ($stmt->fetch()) {
            // Toggle Off
            $stmt = $db->prepare("DELETE FROM alias_votes WHERE alias_id = ? AND user_id = ?");
            $stmt->execute([$aliasId, $userId]);
            $this->jsonResponse(['action' => 'removed']);
        } else {
            // Toggle On
            $stmt = $db->prepare("INSERT INTO alias_votes (alias_id, user_id) VALUES (?, ?)");
            $stmt->execute([$aliasId, $userId]);
            $this->jsonResponse(['action' => 'added'], 201);
        }
    }
}