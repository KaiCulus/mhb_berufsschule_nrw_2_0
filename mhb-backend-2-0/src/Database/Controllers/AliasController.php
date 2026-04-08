<?php

namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use Kai\MhbBackend20\Database\DB;
use PDO;

/**
 * AliasController
 *
 * Verwaltet Dokument-Aliase und deren Bewertungen (Votes).
 *
 * Aliase erlauben es Nutzern, eigene Bezeichnungen für Dokumente zu vergeben
 * und die Vorschläge anderer Nutzer per Vote zu priorisieren.
 *
 * Sicherheitshinweis:
 *   Die User-ID wird in allen Methoden ausschließlich aus der Session gelesen —
 *   nie aus dem Request-Body oder URL-Parametern. Das verhindert, dass ein
 *   eingeloggter User Aktionen im Namen anderer User ausführen kann.
 */
class AliasController extends BaseController
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = DB::getInstance()->getConnection();
    }

    /**
     * GET api/aliases/{docId}/{userId}
     *
     * Gibt alle Aliase eines Dokuments zurück, sortiert nach Beliebtheit.
     * Markiert zusätzlich welche Aliase der aktuelle User bereits gevoted hat.
     *
     * @param string $docId    Microsoft-Dokument-ID aus der URL
     * @param string $userId   URL-Parameter — wird ignoriert, Session-ID wird verwendet
     */
    public function getAliases(string $scope, string $docId): void
    {
        // User-ID aus der Session — URL-Parameter $userId wird bewusst ignoriert
        $user = AuthMiddleware::check();

        $stmt = $this->db->prepare("
            SELECT
                a.*,
                COUNT(v.alias_id)                                           AS vote_count,
                MAX(CASE WHEN v.user_id = :userId THEN 1 ELSE 0 END)       AS user_voted
            FROM document_aliases a
            LEFT JOIN alias_votes v ON v.alias_id = a.id
            WHERE a.document_ms_id = :docId
            GROUP BY a.id
            ORDER BY vote_count DESC
        ");

        $stmt->execute([
            'docId'  => $docId,
            'userId' => $user['id'],
        ]);

        $this->jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * POST api/aliases
     *
     * Legt einen neuen Alias für ein Dokument an.
     * Der Alias-Text wird vor dem Speichern gegen XSS bereinigt.
     *
     * Erwarteter Request-Body:
     *   { "docId": "...", "aliasText": "..." }
     */
    public function addAlias(): void
    {
        // User-ID aus der Session — nicht aus dem Body akzeptieren
        $user = AuthMiddleware::check();

        $data = $this->validateRequest([
            'docId'     => 'string',
            'aliasText' => 'string',
        ]);

        // XSS-Schutz: HTML-Sonderzeichen im Alias-Text escapen
        $cleanAlias = $this->sanitize($data['aliasText']);

        $stmt = $this->db->prepare("
            INSERT INTO document_aliases (document_ms_id, alias_text, created_by)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $data['docId'],
            $cleanAlias,
            $user['id'],
        ]);

        $this->jsonResponse(['status' => 'success', 'id' => $this->db->lastInsertId()], 201);
    }

    /**
     * POST api/aliases/vote
     *
     * Schaltet den Vote des aktuellen Users für einen Alias um (Toggle).
     * Existiert ein Vote → wird gelöscht. Existiert keiner → wird angelegt.
     *
     * Erwarteter Request-Body:
     *   { "aliasId": 42 }
     */
    public function toggleVote(): void
    {
        // User-ID aus der Session — nicht aus dem Body akzeptieren
        $user = AuthMiddleware::check();

        $data    = $this->validateRequest(['aliasId' => 'int']);
        $aliasId = (int) $data['aliasId'];
        $userId  = $user['id'];

        // Prüfen ob Vote bereits existiert
        $stmt = $this->db->prepare("SELECT 1 FROM alias_votes WHERE alias_id = ? AND user_id = ?");
        $stmt->execute([$aliasId, $userId]);

        if ($stmt->fetch()) {
            // Vote entfernen
            $this->db->prepare("DELETE FROM alias_votes WHERE alias_id = ? AND user_id = ?")
                     ->execute([$aliasId, $userId]);

            $this->jsonResponse(['action' => 'removed']);
        } else {
            // Vote hinzufügen
            $this->db->prepare("INSERT INTO alias_votes (alias_id, user_id) VALUES (?, ?)")
                     ->execute([$aliasId, $userId]);

            $this->jsonResponse(['action' => 'added'], 201);
        }
    }
}