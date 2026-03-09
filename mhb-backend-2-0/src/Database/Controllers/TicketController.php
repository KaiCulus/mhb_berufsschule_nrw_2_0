<?php

namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Database\DB;
use Kai\MhbBackend20\Graph\Services\TicketMailService;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use Kai\MhbBackend20\Common\Cipher;
use PDO;

class TicketController extends BaseController {
    // Zentrales Mapping der Rollen
    private const ROLE_PROCESSOR = 'MHB_BE_MSAL_TICKETPROCESSORS';

    private $db;
    private TicketMailService $mailService;
    private string $encKey;

    public function __construct() {
        $this->db = DB::getInstance()->getConnection();
        $this->mailService = new TicketMailService();
        $this->encKey = $_ENV['APP_ENCRYPTION_KEY'];
    }

    /**
     * POST api/tickets
     */
    public function createTicket() {
        $user = AuthMiddleware::check(); 
        
        $data = $this->validateRequest([
            'title'         => 'string',
            'category'      => 'string',
            'priority'      => 'string',
            'location_type' => 'string'
        ]);

        $targetMail = $this->mapCategoryToMail($data['category']);
        $cleanDesc  = $this->sanitize($data['description'] ?? '');

        $stmt = $this->db->prepare("
            INSERT INTO tickets (title, description, category, sub_category, priority, location_type, building, room, created_by, assigned_group_mail)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['title'], 
            $cleanDesc, 
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
        
        // Benachrichtigungen mit ausführlichem Body
        $this->sendInitialNotifications((int)$ticketId, $data, $user, $targetMail);

        // Wir nutzen hier eine leicht angepasste Nachricht für die "Raum-Follower"
    if ($data['location_type'] === 'building' && !empty($data['room'])) {
            $room = strtoupper(trim($data['room']));
            $this->notifySubscribers(
                $ticketId, 
                $data['title'], 
                "Ein neues Ticket wurde für den Raum <b>$room</b> erstellt, dem du folgst."
            );
        }
        $this->jsonResponse(['status' => 'success', 'ticket_id' => $ticketId], 201);
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
        
        $this->jsonResponse($this->decryptResults($stmt->fetchAll(PDO::FETCH_ASSOC), 'creator_name_enc', 'creator_name'));
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
        
        $this->jsonResponse($this->decryptResults($stmt->fetchAll(PDO::FETCH_ASSOC), 'creator_name_enc', 'creator_name'));
    }

    public function getDetail(int $ticketId) {
        $user = AuthMiddleware::check();
        
        $stmt = $this->db->prepare("
            SELECT t.*, u.display_name_encrypted as creator_name_enc, lu.display_name_encrypted as last_editor_name_enc
            FROM tickets t 
            JOIN users u ON t.created_by = u.id 
            LEFT JOIN users lu ON t.last_edited_by = lu.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) $this->errorResponse('Ticket nicht gefunden', 404);

        $ticket['creator_name'] = Cipher::decrypt($ticket['creator_name_enc'], $this->encKey);
        if (!empty($ticket['last_editor_name_enc'])) {
            $ticket['last_editor_name'] = Cipher::decrypt($ticket['last_editor_name_enc'], $this->encKey);
        }
        
        $isProcessor = AuthMiddleware::hasGroup(self::ROLE_PROCESSOR);
        $isCreator = (int)$ticket['created_by'] === (int)$user['id'];
        $ticket['can_edit_status'] = ($isProcessor || $isCreator);

        $stmtComments = $this->db->prepare("
            SELECT c.*, u.display_name_encrypted as author_name_enc 
            FROM ticket_comments c JOIN users u ON c.user_id = u.id
            WHERE c.ticket_id = ? ORDER BY c.created_at ASC
        ");
        $stmtComments->execute([$ticketId]);
        $ticket['comments'] = $this->decryptResults($stmtComments->fetchAll(PDO::FETCH_ASSOC), 'author_name_enc', 'author_name');

        $this->jsonResponse($ticket);
    }

    public function addComment() {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest(['ticketId' => 'int', 'comment' => 'string']);

        // Prüfung ob Ticket existiert
        $stmtT = $this->db->prepare("SELECT title FROM tickets WHERE id = ?");
        $stmtT->execute([$data['ticketId']]);
        $ticket = $stmtT->fetch();
        if (!$ticket) $this->errorResponse('Ticket nicht gefunden', 404);

        $cleanComment = $this->sanitize($data['comment']);
        $stmt = $this->db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->execute([$data['ticketId'], $user['id'], $cleanComment]);
        
        $this->notifySubscribers(
            (int)$data['ticketId'], 
            $ticket['title'], 
            "Neue Notiz von <b>{$user['name']}</b>:<br><i>" . nl2br(htmlspecialchars($cleanComment)) . "</i>"
        );

        $this->jsonResponse(['status' => 'success']);
    }

    public function toggleSubscription() {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest(['ticketId' => 'int']);

        // Nutzung der generischen Methode
        $status = $this->toggleGenericSubscription(
            'ticket_subscriptions', 
            ['user_id' => $user['id'], 'ticket_id' => (int)$data['ticketId']]
        );

        $this->jsonResponse(['status' => 'success', 'subscription' => $status]);
    }

    public function toggleRoomSubscription() {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest(['room' => 'string']);
        $room = strtoupper(trim($data['room']));

        $status = $this->toggleGenericSubscription(
            'ticket_room_subscriptions', 
            ['user_id' => $user['id'], 'room_name' => $room]
        );

        $this->jsonResponse(['status' => 'success', 'subscription' => $status, 'room' => $room]);
    }
    public function getRoomSubscriptions(int $userId) {
        AuthMiddleware::check();
        $stmt = $this->db->prepare("SELECT room_name FROM ticket_room_subscriptions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $rooms = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->jsonResponse($rooms);
    }

    

    public function updateField() {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest(['ticketId' => 'int', 'field' => 'string', 'value' => 'string']);
        
        $stmtCheck = $this->db->prepare("SELECT title, created_by FROM tickets WHERE id = ?");
        $stmtCheck->execute([(int)$data['ticketId']]);
        $ticket = $stmtCheck->fetch();

        if (!$ticket) $this->errorResponse('Ticket nicht gefunden', 404);

        $isProcessor = AuthMiddleware::hasGroup(self::ROLE_PROCESSOR);
        if (!$isProcessor && (int)$ticket['created_by'] !== (int)$user['id']) {
            $this->errorResponse('Keine Berechtigung', 403);
        }

        $allowedFields = ['title', 'description', 'category', 'sub_category', 'priority', 'location_type', 'building', 'room', 'status'];
        if (!in_array($data['field'], $allowedFields)) $this->errorResponse('Ungültiges Feld', 400);

        $cleanValue = $this->sanitize($data['value']);
        $stmt = $this->db->prepare("UPDATE tickets SET {$data['field']} = ?, last_edited_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$cleanValue, $user['id'], (int)$data['ticketId']]);

        if ($data['field'] === 'status') {
            $this->notifySubscribers((int)$data['ticketId'], $ticket['title'], "Status wurde auf <b>'$cleanValue'</b> geändert.");
        }

        $this->jsonResponse(['status' => 'success']);
    }

    public function resolveTicket() {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest(['ticketId' => 'int']);
        $ticketId = (int)$data['ticketId'];
        
        $stmt = $this->db->prepare("SELECT created_by FROM tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) $this->errorResponse('Ticket nicht gefunden', 404);

        if (AuthMiddleware::hasGroup(self::ROLE_PROCESSOR)) {
            $this->updateTicketStatus($ticketId, 'resolved_by_staff');
            $this->jsonResponse(['status' => 'resolved']);
        } elseif ((int)$ticket['created_by'] === (int)$user['id']) {
            $stmt = $this->db->prepare("DELETE FROM tickets WHERE id = ?");
            $stmt->execute([$ticketId]);
            $this->jsonResponse(['status' => 'deleted']);
        } else {
            $this->errorResponse('Nicht autorisiert', 403);
        }
    }
    public function getCanDeleteTicket($ticketId) {
        $user = AuthMiddleware::check(); // Aktuellen User holen
        
        $stmt = $this->db->prepare("SELECT created_by FROM tickets WHERE id = ?");
        $stmt->execute([(int)$ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            $this->errorResponse('Ticket nicht gefunden', 404);
        }

        // Prüfung: Ist die User-ID identisch mit dem Ersteller?
        $isOwner = (int)$ticket['created_by'] === (int)$user['id'];

        $this->jsonResponse([
            'can_delete' => $isOwner
        ]);
    }


    public function cleanupOldTickets() {
        $this->requireGroup(self::ROLE_PROCESSOR);

        $stmt = $this->db->prepare("
            DELETE FROM tickets 
            WHERE status = 'resolved_by_staff' 
            AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        $this->jsonResponse(['status' => 'success', 'deleted_count' => $stmt->rowCount()]);
    }

    /**
     * Private Helpers
     */
    private function updateTicketStatus(int $id, string $status): void {
        $stmt = $this->db->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $id]);
    }

    /**
     * Kern-Logik für alle Toggle-Aktionen (Tickets, Räume, Favoriten etc.)
     */
    private function toggleGenericSubscription(string $table, array $conditions): string {
        $whereClause = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($conditions)));
        $values = array_values($conditions);

        $stmtCheck = $this->db->prepare("SELECT 1 FROM $table WHERE $whereClause");
        $stmtCheck->execute($values);

        if ($stmtCheck->fetch()) {
            $stmt = $this->db->prepare("DELETE FROM $table WHERE $whereClause");
            $stmt->execute($values);
            return 'unsubscribed';
        } else {
            $cols = implode(', ', array_keys($conditions));
            $nodes = implode(', ', array_fill(0, count($conditions), '?'));
            $stmt = $this->db->prepare("INSERT INTO $table ($cols) VALUES ($nodes)");
            $stmt->execute($values);
            return 'subscribed';
        }
    }

    private function sendInitialNotifications(int $ticketId, array $data, array $user, string $targetMail) {
        // Bestätigung an User
        $this->mailService->sendNotification(
            $user['email'], 
            "Bestätigung: Ticket #$ticketId", 
            "Hallo {$user['name']}, dein Ticket '<b>" . htmlspecialchars($data['title']) . "</b>' wurde erfolgreich erstellt."
        );
        
        // Info an Fachabteilung mit vollem Body
        $location = ($data['location_type'] === 'building') 
            ? "Gebäude: " . htmlspecialchars($data['building'] ?? '') . ", Raum: " . htmlspecialchars($data['room'] ?? '') 
            : "Sonstiger Ort: " . htmlspecialchars($data['room'] ?? '');

        $mailBody = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <h2 style='color: #0e64a6;'>Neues Ticket erstellt: #$ticketId</h2>
                <p><strong>Titel:</strong> " . htmlspecialchars($data['title']) . "</p>
                <hr>
                <p><strong>Von:</strong> {$user['name']} ({$user['email']})</p>
                <p><strong>Kategorie:</strong> {$data['category']} (" . ($data['sub_category'] ?? 'Keine Angabe') . ")</p>
                <p><strong>Priorität:</strong> " . strtoupper($data['priority']) . "</p>
                <p><strong>Ort:</strong> $location</p>
                <hr>
                <p><strong>Beschreibung:</strong><br>" . nl2br(htmlspecialchars($data['description'] ?? '')) . "</p>
            </div>
        ";
        $this->mailService->sendNotification($targetMail, "NEUES TICKET: #$ticketId - " . $data['title'], $mailBody);
    }

    private function decryptResults(array $results, string $sourceKey, string $targetKey): array {
        foreach ($results as &$item) {
            if (!empty($item[$sourceKey])) {
                $item[$targetKey] = Cipher::decrypt($item[$sourceKey], $this->encKey);
            }
            unset($item[$sourceKey]);
        }
        return $results;
    }

    private function mapCategoryToMail(string $cat): string {
        return match($cat) {
            'network'  => $_ENV['TICKET_MAIL_NETWORK'],
            'facility' => $_ENV['TICKET_MAIL_FACILITY'],
            default    => $_ENV['TICKET_MAIL_IT_SUPPORT']
        };
    }

    private function notifySubscribers(int $ticketId, string $title, string $message) {
        $stmt = $this->db->prepare("
            -- 1. Leute, die das Ticket direkt abonniert haben
            SELECT u.email_encrypted FROM users u
            JOIN ticket_subscriptions s ON u.id = s.user_id WHERE s.ticket_id = ?
            
            UNION
            
            -- 2. Der Ersteller des Tickets
            SELECT u.email_encrypted FROM users u
            JOIN tickets t ON u.id = t.created_by WHERE t.id = ?

            UNION

            -- 3. Leute, die den Raum abonniert haben, in dem das Ticket liegt
            SELECT u.email_encrypted FROM users u
            JOIN ticket_room_subscriptions rs ON u.id = rs.user_id
            JOIN tickets t ON rs.room_name = t.room
            WHERE t.id = ? AND t.location_type = 'building'
        ");
        
        // Wir übergeben die ticketId dreimal für die drei Teile des UNIONs
        $stmt->execute([$ticketId, $ticketId, $ticketId]);
        $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($emails as $encEmail) {
            $email = Cipher::decrypt($encEmail, $this->encKey);
            $this->mailService->sendNotification(
                $email, 
                "Update zu Ticket #$ticketId: $title", 
                "<div style='font-family:Arial,sans-serif;'>$message</div>"
            );
        }
    }
}