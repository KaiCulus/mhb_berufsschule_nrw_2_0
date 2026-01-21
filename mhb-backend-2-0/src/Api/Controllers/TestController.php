<?php
namespace Kai\MhbBackend20\Api\Controllers;

use Kai\MhbBackend20\Common\Cipher;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;

class TestController {
    
    public function getThirdLetter(): void 
    {
        // 1. Sicherheit: Nur eingeloggte User (Säule 1)
        AuthMiddleware::check(); 

        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['error' => 'Keine ID angegeben']);
            return;
        }

        $db = \Kai\MhbBackend20\Database\DB::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT email_encrypted FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if ($user) {
            // 2. Entschlüsseln (Säule 3)
            $email = Cipher::decrypt($user['email_encrypted'], $_ENV['APP_ENCRYPTION_KEY']);
            
            // 3. Logik: 3. Buchstabe
            $letter = mb_substr($email, 2, 1);
            
            header('Content-Type: application/json');
            echo json_encode(['letter' => $letter]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User nicht gefunden']);
        }
    }
}