<?php

namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Database\DB;
use PDO;

class FavoriteController {
    /**
     * Holt alle Favoriten-IDs für einen bestimmten User
     */
    public function getFavorites($userId) {
        $db = DB::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT document_ms_id FROM user_favorites WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Wir geben nur ein flaches Array der IDs zurück: ["ID1", "ID2"]
        $favorites = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        header('Content-Type: application/json');
        echo json_encode($favorites);
    }

    /**
     * Fügt einen Favoriten hinzu (POST)
     */
    public function addFavorite() {
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['userId'] ?? null;
        $docId = $data['docId'] ?? null;

        if (!$userId || !$docId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing data']);
            return;
        }

        $db = DB::getInstance()->getConnection();
        // IGNORE verhindert Fehler, falls der Favorit bereits existiert
        $stmt = $db->prepare("INSERT IGNORE INTO user_favorites (user_id, document_ms_id) VALUES (?, ?)");
        $stmt->execute([$userId, $docId]);

        echo json_encode(['success' => true]);
    }

    /**
     * Entfernt einen Favoriten (DELETE)
     */
    public function removeFavorite() {
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['userId'] ?? null;
        $docId = $data['docId'] ?? null;

        $db = DB::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM user_favorites WHERE user_id = ? AND document_ms_id = ?");
        $stmt->execute([$userId, $docId]);

        echo json_encode(['success' => true]);
    }
}