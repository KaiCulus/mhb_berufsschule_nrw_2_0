<?php

namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Database\DB;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use PDO;

class FavoriteController extends BaseController {
    
    private $db;

    public function __construct() {
        $this->db = DB::getInstance()->getConnection();
    }

    /**
     * GET api/favorites
     * Holt alle Favoriten-IDs für den aktuell eingeloggten User
     */
    public function getFavorites() {
        // Authentifizierung prüfen und User-Daten holen
        $user = AuthMiddleware::check();
        
        $stmt = $this->db->prepare("SELECT document_ms_id FROM user_favorites WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        
        // FETCH_COLUMN gibt uns direkt das flache Array ["ID1", "ID2"]
        $favorites = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $this->jsonResponse($favorites);
    }

    /**
     * POST api/favorites
     * Fügt einen Favoriten für den aktuellen User hinzu
     */
    public function addFavorite() {
        $user = AuthMiddleware::check();
        
        // Validierung via BaseController (erwartet docId im Body)
        $data = $this->validateRequest([
            'docId' => 'string'
        ]);

        // INSERT IGNORE verhindert Dubletten ohne Fehlermeldung
        $stmt = $this->db->prepare("INSERT IGNORE INTO user_favorites (user_id, document_ms_id) VALUES (?, ?)");
        $stmt->execute([$user['id'], $data['docId']]);

        $this->jsonResponse(['success' => true, 'added' => $data['docId']]);
    }

    /**
     * DELETE api/favorites
     * Entfernt einen Favoriten für den aktuellen User
     */
    public function removeFavorite() {
        $user = AuthMiddleware::check();
        
        // Da DELETE oft keine Bodys hat, prüfen wir hier beides: 
        // Entweder via validateRequest (Body) oder getQueryParam (URL)
        $docId = $this->validateRequest(['docId' => 'string'])['docId'];

        $stmt = $this->db->prepare("DELETE FROM user_favorites WHERE user_id = ? AND document_ms_id = ?");
        $stmt->execute([$user['id'], $docId]);

        $this->jsonResponse(['success' => true, 'removed' => $docId]);
    }
}