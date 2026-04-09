<?php

namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use Kai\MhbBackend20\Database\DB;
use PDO;

/**
 * DocumentController
 *
 * Liefert Dokumente aus der Datenbank, gefiltert nach Sync-Scope.
 *
 * Scopes repräsentieren Dokumentengruppen mit unterschiedlichen Zugriffsrechten:
 *   - 'admin_verwaltung' → nur Verwaltungs-Admins
 *   - 'verwaltung'       → alle Lehrer (Teacher Access Group)
 *   - alle anderen       → alle authentifizierten User
 *
 * Jedes Dokument wird mit seinen Aliasen angereichert, sortiert nach Beliebtheit.
 */
class DocumentController extends BaseController
{
    /**
     * Zuordnung von Scope-Namen zu .env-Schlüsseln der Berechtigungsgruppen.
     * Scopes die hier nicht aufgeführt sind, sind für alle eingeloggten User zugänglich.
     */
    private const SCOPE_PERMISSIONS = [
        'admin_verwaltung' => 'MHB_BE_MSAL_ADMIN_VERWALTUNG',
        'admin_common'     => 'MHB_BE_MSAL_ADMIN_COMMON',
        'verwaltung'       => 'MHB_BE_MSAL_TEACHER_ACCESS_GROUP',
    ];

    private \PDO $db;

    public function __construct()
    {
        $this->db = DB::getInstance()->getConnection();
    }

    /**
     * GET api/documents/{scope}
     *
     * Gibt alle aktiven (nicht gelöschten) Dokumente eines Scopes zurück,
     * inklusive ihrer Aliase sortiert nach Vote-Anzahl.
     *
     * Zugriffskontrolle:
     *   1. Authentifizierung: User muss eingeloggt sein (immer)
     *   2. Autorisierung: Für privilegierte Scopes wird Gruppen-Mitgliedschaft geprüft
     *
     * @param string $scope Dokumentengruppe (z.B. 'verwaltung', 'admin_verwaltung')
     */
    public function getByScope(string $scope): void
    {
        // 1. Authentifizierung — gilt für alle Scopes
        AuthMiddleware::check();

        // 2. Gruppen-Autorisierung — nur für privilegierte Scopes
        if (isset(self::SCOPE_PERMISSIONS[$scope])) {
            $this->requireGroup(self::SCOPE_PERMISSIONS[$scope]);
        }

        // Alle aktiven Dokumente des Scopes mit Aliasen laden.
        // Aliase werden als '||'-getrennter String aggregiert und im PHP-Code
        // explodiert — effizienter als ein separater Query pro Dokument (N+1).
        // Sortierung: Ordner zuerst, dann alphabetisch nach Originalname.
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
                        SELECT
                            a.alias_text,
                            a.document_ms_id,
                            COUNT(v.alias_id) AS vote_count
                        FROM document_aliases a
                        LEFT JOIN alias_votes v ON a.id = v.alias_id
                        GROUP BY a.id
                    ) sub
                    WHERE sub.document_ms_id = d.ms_id
                      AND sub.alias_text > ''
                ) AS alias_list
            FROM documents d
            WHERE d.sync_scope = :scope
              AND d.deleted_at IS NULL
            ORDER BY d.is_folder DESC, d.name_original ASC
        ");

        $stmt->execute(['scope' => $scope]);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Nachbearbeitung: alias_list String → Array, is_folder 0/1 → bool
        foreach ($docs as &$doc) {
            $doc['aliases']  = !empty($doc['alias_list'])
                ? explode('||', $doc['alias_list'])
                : [];
            $doc['is_folder'] = (bool) $doc['is_folder'];
            unset($doc['alias_list']); // Internes Aggregationsfeld nicht ans Frontend schicken
        }
        unset($doc); // Referenz aus foreach aufräumen

        $this->jsonResponse($docs);
    }
}