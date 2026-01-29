<?php

namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Database\DB;
use Kai\MhbBackend20\Graph\Services\TicketMailService;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use Kai\MhbBackend20\Common\Cipher;
use PDO;

class TicketController {
    private $db;
    private TicketMailService $mailService;
    private string $encKey;

    public function __construct() {
        $this->db = DB::getInstance()->getConnection();
        $this->mailService = new TicketMailService();
        $this->encKey = $_ENV['APP_ENCRYPTION_KEY'];
    }

    /**
     * Erstellt ein neues Ticket und versendet Benachrichtigungen
     */
public function createTicket() {
        $user = AuthMiddleware::check(); // AuthMiddleware liefert hoffentlich bereits entschlüsselte Daten
        $data = json_decode(file_get_contents('php://input'), true);

        $targetMail = $this->mapCategoryToMail($data['category']);

        $stmt = $this->db->prepare("
            INSERT INTO tickets (title, description, category, sub_category, priority, location_type, building, room, created_by, assigned_group_mail)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['title'], $data['description'], $data['category'], $data['sub_category'] ?? null,
            $data['priority'], $data['location_type'], $data['building'] ?? null, $data['room'] ?? null,
            $user['id'], $targetMail
        ]);

        $ticketId = $this->db->lastInsertId();

        // E-Mail an Ersteller (Klarsicht-Daten aus der Session/Middleware nutzen)
        $this->mailService->sendNotification(
            $user['email'], 
            "Ticket eingegangen: #$ticketId - " . $data['title'],
            "Hallo {$user['name']}, dein Ticket wurde mit der ID #$ticketId empfangen."
        );

        // Info an Bearbeiter-Gruppe
        $this->mailService->sendNotification(
            $targetMail,
            "NEUES TICKET: #$ticketId - " . $data['title'],
            "Ein neues Ticket in der Kategorie <b>{$data['category']}</b> wurde von {$user['name']} erstellt."
        );

        echo json_encode(['status' => 'success', 'ticket_id' => $ticketId]);
    }

    public function getAll() {
        AuthMiddleware::check();
        $stmt = $this->db->prepare("
            SELECT t.*, u.display_name_encrypted as creator_name_enc 
            FROM tickets t
            JOIN users u ON t.created_by = u.id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute();
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($this->decryptResults($tickets, 'creator_name_enc', 'creator_name'));
    }

    public function getByUser(int $userId) {
        AuthMiddleware::check();
        $stmt = $this->db->prepare("
            SELECT DISTINCT t.*, u.display_name_encrypted as creator_name_enc
            FROM tickets t
            JOIN users u ON t.created_by = u.id
            LEFT JOIN ticket_subscriptions s ON t.id = s.ticket_id
            WHERE t.created_by = ? OR s.user_id = ?
            ORDER BY t.updated_at DESC
        ");
        $stmt->execute([$userId, $userId]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($this->decryptResults($tickets, 'creator_name_enc', 'creator_name'));
    }

    public function getDetail(int $ticketId) {
        AuthMiddleware::check();
        $stmt = $this->db->prepare("
            SELECT t.*, u.display_name_encrypted as creator_name_enc 
            FROM tickets t 
            JOIN users u ON t.created_by = u.id 
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            http_response_code(404);
            echo json_encode(['error' => 'Ticket nicht gefunden']);
            return;
        }

        $ticket['creator_name'] = Cipher::decrypt($ticket['creator_name_enc'], $this->encKey);
        unset($ticket['creator_name_enc']);

        // Kommentare entschlüsseln
        $stmtComments = $this->db->prepare("
            SELECT c.*, u.display_name_encrypted as author_name_enc 
            FROM ticket_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.ticket_id = ?
            ORDER BY c.created_at ASC
        ");
        $stmtComments->execute([$ticketId]);
        $comments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);
        $ticket['comments'] = $this->decryptResults($comments, 'author_name_enc', 'author_name');

        echo json_encode($ticket);
    }

    /**
     * Ticket folgen oder Entfolgen (Toggle)
     */
    public function toggleSubscription() {
        $user = AuthMiddleware::check();
        $data = json_decode(file_get_contents('php://input'), true);
        $ticketId = $data['ticketId'];

        // Prüfen ob Abo existiert
        $stmtCheck = $this->db->prepare("SELECT 1 FROM ticket_subscriptions WHERE user_id = ? AND ticket_id = ?");
        $stmtCheck->execute([$user['id'], $ticketId]);

        if ($stmtCheck->fetch()) {
            $stmt = $this->db->prepare("DELETE FROM ticket_subscriptions WHERE user_id = ? AND ticket_id = ?");
            $stmt->execute([$user['id'], $ticketId]);
            $status = 'unsubscribed';
        } else {
            $stmt = $this->db->prepare("INSERT INTO ticket_subscriptions (user_id, ticket_id) VALUES (?, ?)");
            $stmt->execute([$user['id'], $ticketId]);
            $status = 'subscribed';
        }

        echo json_encode(['status' => 'success', 'subscription' => $status]);
    }

    /**
     * Kommentar hinzufügen und ggf. E-Mail triggern
     */
    public function addComment() {
        $user = AuthMiddleware::check();
        $data = json_decode(file_get_contents('php://input'), true);

        $stmt = $this->db->prepare("
            INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['ticketId'], 
            $user['id'], 
            $data['comment'], 
            $data['isInternal'] ?? false
        ]);

        // Feature: Benachrichtigung an Follower senden, wenn nicht intern
        if (!($data['isInternal'] ?? false)) {
            // Hier könnte man eine Schleife über alle Subscriber ziehen und den TicketMailService nutzen
        }

        echo json_encode(['status' => 'success']);
    }


    /**
     * Hilfsmethode zum Entschlüsseln von Listen
     */
    private function decryptResults(array $results, string $sourceKey, string $targetKey): array {
        foreach ($results as &$item) {
            if (!empty($item[$sourceKey])) {
                $item[$targetKey] = Cipher::decrypt($item[$sourceKey], $this->encKey);
            }
            unset($item[$sourceKey]);
        }
        return $results;
    }

    /**
     * Summary of mapCategoryToMail
     * @param string $cat
     * @return string
     */
    private function mapCategoryToMail(string $cat): string {
        return match($cat) {
            'network'  => $_ENV['TICKET_MAIL_NETWORK'],
            'facility' => $_ENV['TICKET_MAIL_FACILITY'],
            default    => $_ENV['TICKET_MAIL_IT_SUPPORT']
        };
    }
}