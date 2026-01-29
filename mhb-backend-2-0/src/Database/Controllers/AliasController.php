<?php
namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Database\DB;
use PDO;

class AliasController {
    /**
     * Holt alle Aliase für ein Dokument inklusive Voting-Statistiken.
     */
    public function getAliases($docId, $userId) {
        // Wir holen die PDO-Verbindung über getConnection()
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
            'userId' => $userId
        ]);
        
        return $stmt->fetchAll();
    }

    /**
     * Fügt einen neuen Namensvorschlag (Alias) hinzu.
     */
    public function addAlias($docId, $aliasText, $userId) {
        $db = DB::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO document_aliases (document_ms_id, alias_text, created_by) 
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([$docId, $aliasText, $userId]);
        
        return $db->lastInsertId();
    }

    /**
     * Schaltet den Vote eines Nutzers für einen Alias an oder aus (Toggle).
     */
    public function toggleVote($aliasId, $userId) {
        $db = DB::getInstance()->getConnection();
        
        // Check ob Vote bereits in der Tabelle alias_votes existiert
        $stmt = $db->prepare("SELECT 1 FROM alias_votes WHERE alias_id = ? AND user_id = ?");
        $stmt->execute([$aliasId, $userId]);
        
        if ($stmt->fetch()) {
            // Wenn vorhanden: Entfernen
            $stmt = $db->prepare("DELETE FROM alias_votes WHERE alias_id = ? AND user_id = ?");
            $stmt->execute([$aliasId, $userId]);
            return ['action' => 'removed'];
        } else {
            // Wenn nicht vorhanden: Hinzufügen
            $stmt = $db->prepare("INSERT INTO alias_votes (alias_id, user_id) VALUES (?, ?)");
            $stmt->execute([$aliasId, $userId]);
            return ['action' => 'added'];
        }
    }
}