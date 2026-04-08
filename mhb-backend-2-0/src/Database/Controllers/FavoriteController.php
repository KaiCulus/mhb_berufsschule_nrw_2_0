<?php

namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use Kai\MhbBackend20\Database\DB;
use PDO;

/**
 * FavoriteController
 *
 * Verwaltet die Favoriten-Dokumente des eingeloggten Users.
 *
 * Favoriten werden ausschließlich anhand der Session-User-ID gespeichert —
 * kein User kann Favoriten eines anderen Users lesen oder verändern.
 *
 * Alle Endpunkte erfordern eine aktive Authentifizierung.
 */
class FavoriteController extends BaseController
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = DB::getInstance()->getConnection();
    }

    /**
     * GET api/favorites
     *
     * Gibt alle Favoriten-Dokument-IDs des aktuellen Users zurück.
     *
     * Antwort: ["ms-id-1", "ms-id-2", ...]
     */
    public function getFavorites(): void
    {
        $user = AuthMiddleware::check();

        $stmt = $this->db->prepare("SELECT document_ms_id FROM user_favorites WHERE user_id = ?");
        $stmt->execute([$user['id']]);

        // FETCH_COLUMN gibt direkt ein flaches Array zurück — kein array_map nötig
        $this->jsonResponse($stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * POST api/favorites
     *
     * Fügt ein Dokument zu den Favoriten des aktuellen Users hinzu.
     * Bereits vorhandene Favoriten werden stillschweigend ignoriert (INSERT IGNORE).
     *
     * Erwarteter Request-Body:
     *   { "docId": "..." }
     */
    public function addFavorite(): void
    {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest(['docId' => 'string']);

        // INSERT IGNORE: verhindert Duplikate ohne Fehler zu werfen
        $stmt = $this->db->prepare("INSERT IGNORE INTO user_favorites (user_id, document_ms_id) VALUES (?, ?)");
        $stmt->execute([$user['id'], $data['docId']]);

        $this->jsonResponse(['success' => true, 'added' => $data['docId']]);
    }

    /**
     * DELETE api/favorites
     *
     * Entfernt ein Dokument aus den Favoriten des aktuellen Users.
     * Die WHERE-Bedingung auf user_id stellt sicher, dass kein User
     * Favoriten anderer User löschen kann.
     *
     * Erwarteter Request-Body:
     *   { "docId": "..." }
     */
    public function removeFavorite(): void
    {
        $user  = AuthMiddleware::check();
        $data  = $this->validateRequest(['docId' => 'string']);
        $docId = $data['docId'];

        $stmt = $this->db->prepare("DELETE FROM user_favorites WHERE user_id = ? AND document_ms_id = ?");
        $stmt->execute([$user['id'], $docId]);

        $this->jsonResponse(['success' => true, 'removed' => $docId]);
    }
}