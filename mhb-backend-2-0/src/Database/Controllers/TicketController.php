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
        $user = AuthMiddleware::check(); 
        $data = json_decode(file_get_contents('php://input'), true);

        $targetMail = $this->mapCategoryToMail($data['category']);

        $stmt = $this->db->prepare("
            INSERT INTO tickets (title, description, category, sub_category, priority, location_type, building, room, created_by, assigned_group_mail)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['title'], 
            $data['description'], 
            $data['category'], 
            $data['sub_category'] ?? null,
            $data['priority'], 
            $data['location_type'], 
            $data['building'] ?? null, 
            $data['room'] ?? null,
            $user['id'],
            $targetMail
        ]);

        $ticketId = $this->db->lastInsertId();

        // E-Mails versenden
        $this->mailService->sendNotification(
            $user['email'], 
            "Bestätigung: Ticket #$ticketId eingegangen",
            "Hallo {$user['name']},<br><br>dein Ticket <b>" . htmlspecialchars($data['title']) . "</b> wurde erfolgreich erstellt."
        );

        $location = ($data['location_type'] === 'building') 
            ? "Gebäude: " . htmlspecialchars($data['building']) . ", Raum: " . htmlspecialchars($data['room']) 
            : "Sonstiger Ort: " . htmlspecialchars($data['room']);

        $mailBody = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <h2 style='color: #0e64a6;'>Neues Ticket erstellt: #$ticketId</h2>
                <p><strong>Titel:</strong> " . htmlspecialchars($data['title']) . "</p>
                <hr>
                <p><strong>Von:</strong> {$user['name']} ({$user['email']})</p>
                <p><strong>Kategorie:</strong> {$data['category']} (" . ($data['sub_category'] ?: 'Keine Angabe') . ")</p>
                <p><strong>Priorität:</strong> " . strtoupper($data['priority']) . "</p>
                <p><strong>Ort:</strong> $location</p>
                <hr>
                <p><strong>Beschreibung:</strong><br>" . nl2br(htmlspecialchars($data['description'])) . "</p>
            </div>
        ";

        $this->mailService->sendNotification($targetMail, "NEUES TICKET: #$ticketId - " . $data['title'], $mailBody);

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

    /**
     * Detailansicht mit erweiterter Berechtigungsprüfung
     */
    public function getDetail(int $ticketId) {
        $user = AuthMiddleware::check();
        
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   u.display_name_encrypted as creator_name_enc,
                   lu.display_name_encrypted as last_editor_name_enc
            FROM tickets t 
            JOIN users u ON t.created_by = u.id 
            LEFT JOIN users lu ON t.last_edited_by = lu.id
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
        if ($ticket['last_editor_name_enc']) {
            $ticket['last_editor_name'] = Cipher::decrypt($ticket['last_editor_name_enc'], $this->encKey);
        }
        
        // BERECHTIGUNG: Ersteller ODER Admin darf editieren
        $isProcessor = $this->isTicketProcessor();
        $isCreator = (int)$ticket['created_by'] === (int)$user['id'];
        $ticket['can_edit_status'] = ($isProcessor || $isCreator);

        // Kommentare laden
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

    public function toggleSubscription() {
        $user = AuthMiddleware::check();
        $data = json_decode(file_get_contents('php://input'), true);
        $ticketId = $data['ticketId'];

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
     * Fix: Spalte 'is_internal' entfernt, da sie in der DB nicht existiert
     */
    public function addComment() {
        $user = AuthMiddleware::check();
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['comment'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Kommentar darf nicht leer sein']);
            return;
        }

        // 'is_internal' wurde aus der Query entfernt
        $stmt = $this->db->prepare("
            INSERT INTO ticket_comments (ticket_id, user_id, comment) 
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([
            $data['ticketId'], 
            $user['id'], 
            $data['comment']
        ]);
        $stmtTicket = $this->db->prepare("SELECT title FROM tickets WHERE id = ?");
        $stmtTicket->execute([$data['ticketId']]);
        $t = $stmtTicket->fetch();

        $this->notifySubscribers(
            (int)$data['ticketId'], 
            $t['title'], 
            "Neue Notiz von <b>{$user['name']}</b>: <br><i>" . nl2br(htmlspecialchars($data['comment'])) . "</i>"
        );

        echo json_encode(['status' => 'success']);
    }

    /**
     * Erweitert: Title und Description sind nun editierbar
     */
    public function updateField() {
        $user = AuthMiddleware::check();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmtCheck = $this->db->prepare("SELECT title, created_by FROM tickets WHERE id = ?");
        $stmtCheck->execute([$data['ticketId']]);
        $ticket = $stmtCheck->fetch();

        $isProcessor = $this->isTicketProcessor();
        $isCreator = $ticket && (int)$ticket['created_by'] === (int)$user['id'];

        if (!$isProcessor && !$isCreator) {
            http_response_code(403);
            return;
        }

        $allowedFields = ['title', 'description', 'category', 'sub_category', 'priority', 'location_type', 'building', 'room', 'status'];
        if (!in_array($data['field'], $allowedFields)) {
            http_response_code(400);
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE tickets 
            SET {$data['field']} = ?, last_edited_by = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$data['value'], $user['id'], $data['ticketId']]);

        // NEU: Wenn der Status geändert wurde, Benachrichtigung senden
        if ($data['field'] === 'status') {
            $this->notifySubscribers(
                (int)$data['ticketId'], 
                $ticket['title'], 
                "Der Status wurde auf <b>'{$data['value']}'</b> geändert (von {$user['name']})."
            );
        }

        echo json_encode(['status' => 'success', 'editor' => $user['name']]);
    }

    public function resolveTicket() {
        $user = AuthMiddleware::check();
        $data = json_decode(file_get_contents('php://input'), true);
        $ticketId = $data['ticketId'];
        
        $isProcessor = $this->isTicketProcessor();

        $stmt = $this->db->prepare("SELECT created_by FROM tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            http_response_code(404);
            return;
        }

        if ($isProcessor) {
            $stmt = $this->db->prepare("UPDATE tickets SET status = 'resolved_by_staff', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$ticketId]);
            echo json_encode(['status' => 'resolved']);
        } 
        elseif ((int)$ticket['created_by'] === (int)$user['id']) {
            $stmt = $this->db->prepare("DELETE FROM tickets WHERE id = ?");
            $stmt->execute([$ticketId]);
            echo json_encode(['status' => 'deleted']);
        } 
        else {
            http_response_code(403);
        }
    }

    public function cleanupOldTickets() {
        if (!$this->isTicketProcessor()) {
            http_response_code(403);
            return;
        }

        $stmt = $this->db->prepare("
            DELETE FROM tickets 
            WHERE status = 'resolved_by_staff' 
            AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        echo json_encode(['status' => 'success', 'deleted_count' => $stmt->rowCount()]);
    }

/**
 * Private Hilfsfunktionen
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

    private function isTicketProcessor(): bool {
        // Falls AuthMiddleware::isTicketProcessor() existiert, nutze diese, sonst die lokale Logik
        if (method_exists(AuthMiddleware::class, 'isTicketProcessor')) {
            return AuthMiddleware::isTicketProcessor();
        }
        $userGroups = $_SESSION['user_groups'] ?? []; 
        return in_array($_ENV['MHB_BE_MSAL_TICKETPROCESSORS'], $userGroups);
    }

    private function mapCategoryToMail(string $cat): string {
        return match($cat) {
            'network'  => $_ENV['TICKET_MAIL_NETWORK'],
            'facility' => $_ENV['TICKET_MAIL_FACILITY'],
            default    => $_ENV['TICKET_MAIL_IT_SUPPORT']
        };
    }


    private function notifySubscribers(int $ticketId, string $title, string $message) {
        // 1. Alle E-Mails von Abonnenten + Ersteller holen
        // Wir nutzen ein UNION, um Dubletten zu vermeiden
        $stmt = $this->db->prepare("
            SELECT DISTINCT u.email_encrypted 
            FROM users u
            JOIN ticket_subscriptions s ON u.id = s.user_id
            WHERE s.ticket_id = ?
            UNION
            SELECT u.email_encrypted 
            FROM users u
            JOIN tickets t ON u.id = t.created_by
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId, $ticketId]);
        $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($emails as $encEmail) {
            $email = Cipher::decrypt($encEmail, $this->encKey);
            $this->mailService->sendNotification(
                $email,
                "Update zu Ticket #$ticketId: $title",
                "<div style='font-family:Arial,sans-serif;'>
                    <h3>Status-Update zu deinem Ticket</h3>
                    <p>$message</p>
                    <hr>
                    <p><small>Du erhältst diese Mail, weil du das Ticket erstellt oder abonniert hast.</small></p>
                </div>"
            );
        }
    }
}