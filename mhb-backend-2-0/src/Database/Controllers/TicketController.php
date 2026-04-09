<?php

namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Database\DB;
use Kai\MhbBackend20\Graph\Services\TicketMailService;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use Kai\MhbBackend20\Common\Cipher;
use PDO;

/**
 * TicketController
 *
 * Verwaltet den gesamten Ticket-Lebenszyklus:
 *   Erstellen → Kommentieren → Statusänderungen → Auflösen → Cleanup
 *
 * Berechtigungsmodell:
 *   - Authentifizierte User:  Tickets erstellen, eigene einsehen, kommentieren
 *   - Ersteller:             Eigenes Ticket bearbeiten und löschen
 *   - Processor-Gruppe:      Alle Tickets bearbeiten, als gelöst markieren, Cleanup
 *
 * Sicherheitshinweis:
 *   User-IDs werden ausschließlich aus der Session gelesen — niemals aus URL-Parametern
 *   oder dem Request-Body. Das verhindert IDOR-Angriffe (Insecure Direct Object Reference).
 */
class TicketController extends BaseController
{
    private const ROLE_PROCESSOR = 'MHB_BE_MSAL_TICKETPROCESSORS';

    /**
     * Felder die über updateField() aktualisiert werden dürfen.
     * Whitelist verhindert SQL-Injection durch Spaltenname-Interpolation.
     */
    private const ALLOWED_UPDATE_FIELDS = [
        'title', 'description', 'category', 'sub_category',
        'priority', 'location_type', 'building', 'room', 'status',
    ];

    /**
     * Erlaubte Tabellen für toggleGenericSubscription().
     * Verhindert SQL-Injection durch direkten Tabellennamen im Query.
     */
    private const ALLOWED_SUBSCRIPTION_TABLES = [
        'ticket_subscriptions',
        'ticket_room_subscriptions',
    ];

    private \PDO $db;
    private TicketMailService $mailService;
    private string $encKey;

    public function __construct()
    {
        $this->db         = DB::getInstance()->getConnection();
        $this->mailService = new TicketMailService();
        $this->encKey      = $_ENV['APP_ENCRYPTION_KEY'];
    }

    // =========================================================================
    // Ticket CRUD
    // =========================================================================

    /**
     * POST api/tickets
     *
     * Erstellt ein neues Ticket und versendet initiale E-Mail-Benachrichtigungen.
     * Raum-Abonnenten werden benachrichtigt wenn ein Ticket für ihren Raum erstellt wird.
     *
     * Erwarteter Request-Body:
     *   { "title", "category", "priority", "location_type", ["description", "sub_category", "building", "room"] }
     */
    public function createTicket(): void
    {
        $user = AuthMiddleware::check();

        $data = $this->validateRequest([
            'title'         => 'string',
            'category'      => 'string',
            'priority'      => 'string',
            'location_type' => 'string',
        ]);

        $targetMail = $this->mapCategoryToMail($data['category']);
        $cleanDesc  = $this->sanitize($data['description'] ?? '');

        $stmt = $this->db->prepare("
            INSERT INTO tickets
                (title, description, category, sub_category, priority, location_type, building, room, created_by, assigned_group_mail)
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
            $data['room']     ?? null,
            $user['id'],
            $targetMail,
        ]);

        $ticketId = (int) $this->db->lastInsertId();
        // Bestätigung an User + Info an Fachabteilung
        $this->sendInitialNotifications($ticketId, $data, $user, $targetMail);

        // Raum-Abonnenten benachrichtigen falls Ticket einem Raum zugeordnet ist
        if ($data['location_type'] === 'building' && !empty($data['room'])) {
            $room = htmlspecialchars(strtoupper(trim($data['room'])));
            $this->notifySubscribers(
                $ticketId,
                $data['title'],
                "Ein neues Ticket wurde für den Raum <b>{$room}</b> erstellt, dem du folgst."
            );
        }

        $this->jsonResponse(['status' => 'success', 'ticket_id' => $ticketId], 201);
    }

    /**
     * GET api/tickets
     *
     * Gibt alle Tickets zurück (für Processor-Übersicht), absteigend nach Erstelldatum.
     * Ersteller-Namen werden entschlüsselt.
     */
    public function getAll(): void
    {
        AuthMiddleware::check();

        $stmt = $this->db->prepare("
            SELECT t.*, u.display_name_encrypted AS creator_name_enc
            FROM tickets t
            JOIN users u ON t.created_by = u.id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute();

        $this->jsonResponse(
            $this->decryptResults($stmt->fetchAll(PDO::FETCH_ASSOC), 'creator_name_enc', 'creator_name')
        );
    }

    /**
     * GET api/tickets/user/{userId}
     *
     * Gibt alle Tickets zurück die für den aktuellen User relevant sind:
     *   - Selbst erstellte Tickets
     *   - Direkt abonnierte Tickets
     *   - Tickets in abonnierten Räumen
     *
     * Sicherheit: Der URL-Parameter $userId wird ignoriert — die Session-ID wird verwendet.
     * Das verhindert, dass ein User die Tickets eines anderen Users abruft.
     *
     * @param int $userId URL-Parameter — wird bewusst ignoriert
     */
    public function getByUser(int $userId): void
{
    $user = AuthMiddleware::check();

    $stmt = $this->db->prepare("
        SELECT DISTINCT t.*, u.display_name_encrypted AS creator_name_enc
        FROM tickets t
        JOIN users u ON t.created_by = u.id
        LEFT JOIN ticket_subscriptions s
               ON t.id = s.ticket_id AND s.user_id = ?
        LEFT JOIN ticket_room_subscriptions rs
               ON t.room = rs.room_name AND rs.user_id = ?
        WHERE t.created_by = ?
           OR s.user_id IS NOT NULL
           OR (rs.user_id IS NOT NULL AND t.location_type = 'building')
        ORDER BY t.updated_at DESC
    ");

    // Positionale Parameter (?) statt named (:uid) — PDO erlaubt named params
    // nicht mehrfach im selben Statement bei manchen MySQL-Treibern
    $stmt->execute([$user['id'], $user['id'], $user['id']]);

    $this->jsonResponse(
        $this->decryptResults($stmt->fetchAll(PDO::FETCH_ASSOC), 'creator_name_enc', 'creator_name')
    );
}

    /**
     * GET api/tickets/detail/{ticketId}
     *
     * Gibt ein einzelnes Ticket mit allen Kommentaren zurück.
     * Reichert das Ticket mit Bearbeitungsrechten des aktuellen Users an.
     *
     * @param int $ticketId Ticket-ID aus der URL
     */
    public function getDetail(int $ticketId): void
    {
        $user = AuthMiddleware::check();

        $stmt = $this->db->prepare("
            SELECT t.*,
                   u.display_name_encrypted  AS creator_name_enc,
                   lu.display_name_encrypted AS last_editor_name_enc
            FROM tickets t
            JOIN users u  ON t.created_by    = u.id
            LEFT JOIN users lu ON t.last_edited_by = lu.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            $this->errorResponse('Ticket nicht gefunden.', 404);
        }

        // Ersteller- und Bearbeiter-Namen entschlüsseln
        $ticket['creator_name'] = Cipher::decrypt($ticket['creator_name_enc'], $this->encKey);
        unset($ticket['creator_name_enc']);

        if (!empty($ticket['last_editor_name_enc'])) {
            $ticket['last_editor_name'] = Cipher::decrypt($ticket['last_editor_name_enc'], $this->encKey);
        }
        unset($ticket['last_editor_name_enc']);

        // Bearbeitungsrechte für das Frontend berechnen
        $ticket['can_edit_status'] = AuthMiddleware::hasGroup(self::ROLE_PROCESSOR)
            || (int) $ticket['created_by'] === $user['id'];

        // Kommentare laden und Autoren-Namen entschlüsseln
        $stmtComments = $this->db->prepare("
            SELECT c.*, u.display_name_encrypted AS author_name_enc
            FROM ticket_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.ticket_id = ?
            ORDER BY c.created_at ASC
        ");
        $stmtComments->execute([$ticketId]);
        $ticket['comments'] = $this->decryptResults(
            $stmtComments->fetchAll(PDO::FETCH_ASSOC),
            'author_name_enc',
            'author_name'
        );

        $this->jsonResponse($ticket);
    }

    // =========================================================================
    // Ticket-Aktionen
    // =========================================================================

    /**
     * POST api/tickets/comment
     *
     * Fügt einen Kommentar zu einem Ticket hinzu und benachrichtigt Abonnenten.
     *
     * Erwarteter Request-Body:
     *   { "ticketId": 42, "comment": "..." }
     */
    public function addComment(): void
    {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest(['ticketId' => 'int', 'comment' => 'string']);

        $stmtT = $this->db->prepare("SELECT title FROM tickets WHERE id = ?");
        $stmtT->execute([(int) $data['ticketId']]);
        $ticket = $stmtT->fetch();

        if (!$ticket) {
            $this->errorResponse('Ticket nicht gefunden.', 404);
        }

        $cleanComment = $this->sanitize($data['comment']);

        $this->db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment) VALUES (?, ?, ?)")
                 ->execute([(int) $data['ticketId'], $user['id'], $cleanComment]);

        $authorName = htmlspecialchars($user['name']);
        $commentHtml = nl2br(htmlspecialchars($cleanComment));
        $this->notifySubscribers(
            (int) $data['ticketId'],
            $ticket['title'],
            "Neue Notiz von <b>{$authorName}</b>:<br><i>{$commentHtml}</i>"
        );

        $this->jsonResponse(['status' => 'success']);
    }

    /**
     * POST api/tickets/update-field
     *
     * Aktualisiert ein einzelnes Feld eines Tickets.
     * Nur Ersteller und Processors dürfen Felder bearbeiten.
     * Statusänderungen lösen eine Benachrichtigung an Abonnenten aus.
     *
     * Erwarteter Request-Body:
     *   { "ticketId": 42, "field": "status", "value": "in_progress" }
     */
    public function updateField(): void
    {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest(['ticketId' => 'int', 'field' => 'string', 'value' => 'string']);

        // Whitelist-Check VOR dem SQL-Prepare — Spaltenname kann nicht per Binding übergeben werden
        if (!in_array($data['field'], self::ALLOWED_UPDATE_FIELDS, strict: true)) {
            $this->errorResponse('Ungültiges Feld.', 400);
        }

        $stmtCheck = $this->db->prepare("SELECT title, created_by FROM tickets WHERE id = ?");
        $stmtCheck->execute([(int) $data['ticketId']]);
        $ticket = $stmtCheck->fetch();

        if (!$ticket) {
            $this->errorResponse('Ticket nicht gefunden.', 404);
        }

        $isProcessor = AuthMiddleware::hasGroup(self::ROLE_PROCESSOR);
        if (!$isProcessor && (int) $ticket['created_by'] !== $user['id']) {
            $this->errorResponse('Keine Berechtigung.', 403);
        }

        // Feldname kommt aus der Whitelist — Interpolation hier sicher
        $field      = $data['field'];
        $cleanValue = $this->sanitize($data['value']);

        $this->db->prepare("UPDATE tickets SET {$field} = ?, last_edited_by = ?, updated_at = NOW() WHERE id = ?")
                 ->execute([$cleanValue, $user['id'], (int) $data['ticketId']]);

        if ($field === 'status') {
            $statusHtml = htmlspecialchars($cleanValue);
            $this->notifySubscribers(
                (int) $data['ticketId'],
                $ticket['title'],
                "Status wurde auf <b>'{$statusHtml}'</b> geändert."
            );
        }

        $this->jsonResponse(['status' => 'success']);
    }

    /**
     * POST api/tickets/resolve
     *
     * Löst ein Ticket auf:
     *   - Processor:  Markiert als 'resolved_by_staff' (bleibt 7 Tage erhalten)
     *   - Ersteller:  Löscht das Ticket sofort
     *
     * Erwarteter Request-Body:
     *   { "ticketId": 42 }
     */
    public function resolveTicket(): void
    {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest(['ticketId' => 'int']);
        $ticketId = (int) $data['ticketId'];

        $stmt = $this->db->prepare("SELECT created_by FROM tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            $this->errorResponse('Ticket nicht gefunden.', 404);
        }

        if (AuthMiddleware::hasGroup(self::ROLE_PROCESSOR)) {
            $this->updateTicketStatus($ticketId, 'resolved_by_staff');
            $this->jsonResponse(['status' => 'resolved']);
        } elseif ((int) $ticket['created_by'] === $user['id']) {
            $this->db->prepare("DELETE FROM tickets WHERE id = ?")->execute([$ticketId]);
            $this->jsonResponse(['status' => 'deleted']);
        } else {
            $this->errorResponse('Nicht autorisiert.', 403);
        }
    }

    /**
     * POST api/tickets/cleanup
     *
     * Löscht alle Tickets mit Status 'resolved_by_staff' die älter als 7 Tage sind.
     * Nur für Processors zugänglich.
     */
    public function cleanupOldTickets(): void
    {
        $this->requireGroup(self::ROLE_PROCESSOR);

        $stmt = $this->db->prepare("
            DELETE FROM tickets
            WHERE status = 'resolved_by_staff'
              AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();

        $this->jsonResponse(['status' => 'success', 'deleted_count' => $stmt->rowCount()]);
    }

    // =========================================================================
    // Berechtigungen
    // =========================================================================

    /**
     * GET api/tickets/canDeleteTicket/{ticketId}
     *
     * Prüft ob der aktuelle User ein Ticket löschen darf (= ist Ersteller).
     * Wird vom Frontend verwendet um den Löschen-Button zu zeigen/verstecken.
     *
     * @param int $ticketId Ticket-ID aus der URL
     */
    public function getCanDeleteTicket(int $ticketId): void
    {
        $user = AuthMiddleware::check();

        $stmt = $this->db->prepare("SELECT created_by FROM tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            $this->errorResponse('Ticket nicht gefunden.', 404);
        }

        $this->jsonResponse([
            'can_delete' => (int) $ticket['created_by'] === $user['id'],
        ]);
    }

    /**
     * GET api/user/profile
     *
     * Gibt das Profil des aktuellen Users zurück (Name, E-Mail, Gruppen).
     * Wird in der Navigation und auf der Profilseite verwendet.
     */
    public function getCurrentUserProfile(): void
    {
        $user = AuthMiddleware::check();

        $this->jsonResponse([
            'id'     => $user['id'],
            'name'   => $user['name'],
            'email'  => $user['email'],
            'groups' => $user['groups'] ?? [],
        ]);
    }

    // =========================================================================
    // Abonnements
    // =========================================================================

    /**
     * POST api/tickets/subscribe
     *
     * Schaltet das Abonnement des aktuellen Users für ein Ticket um.
     *
     * Erwarteter Request-Body:
     *   { "ticketId": 42 }
     */
    public function toggleSubscription(): void
    {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest(['ticketId' => 'int']);

        $status = $this->toggleGenericSubscription(
            'ticket_subscriptions',
            ['user_id' => $user['id'], 'ticket_id' => (int) $data['ticketId']]
        );

        $this->jsonResponse(['status' => 'success', 'subscription' => $status]);
    }

    /**
     * POST api/tickets/subscribe-room
     *
     * Schaltet das Raum-Abonnement des aktuellen Users um.
     * Raum-Abonnenten erhalten Benachrichtigungen für alle Tickets in diesem Raum.
     *
     * Erwarteter Request-Body:
     *   { "room": "A201" }
     */
    public function toggleRoomSubscription(): void
    {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest(['room' => 'string']);
        $room = strtoupper(trim($data['room']));

        $status = $this->toggleGenericSubscription(
            'ticket_room_subscriptions',
            ['user_id' => $user['id'], 'room_name' => $room]
        );

        $this->jsonResponse(['status' => 'success', 'subscription' => $status, 'room' => $room]);
    }

    /**
     * GET api/tickets/subscribe-room/{userId}
     *
     * Gibt alle Raum-Abonnements des aktuellen Users zurück.
     * Sicherheit: URL-Parameter $userId wird ignoriert — Session-ID wird verwendet.
     *
     * @param int $userId URL-Parameter — wird bewusst ignoriert
     */
    public function getRoomSubscriptions(int $userId): void
    {
        // Session-ID verwenden — URL-Parameter $userId wird ignoriert
        $user = AuthMiddleware::check();

        $stmt = $this->db->prepare("SELECT room_name FROM ticket_room_subscriptions WHERE user_id = ?");
        $stmt->execute([$user['id']]);

        $this->jsonResponse($stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Setzt den Status eines Tickets und aktualisiert updated_at.
     */
    private function updateTicketStatus(int $id, string $status): void
    {
        $this->db->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?")
                 ->execute([$status, $id]);
    }

    /**
     * Generischer Toggle für Abonnement-Tabellen (Subscribe/Unsubscribe).
     *
     * Sicherheit: Tabellen- und Spaltennamen werden gegen eine Whitelist geprüft
     * bevor sie in den SQL-String interpoliert werden.
     *
     * @param string $table      Tabellen-Name (muss in ALLOWED_SUBSCRIPTION_TABLES stehen)
     * @param array  $conditions Spalte → Wert Mapping für WHERE und INSERT
     * @return string 'subscribed' oder 'unsubscribed'
     */
    private function toggleGenericSubscription(string $table, array $conditions): string
    {
        // Tabellen-Whitelist: verhindert SQL-Injection durch Tabellennamen
        if (!in_array($table, self::ALLOWED_SUBSCRIPTION_TABLES, strict: true)) {
            throw new \InvalidArgumentException("Ungültige Tabelle: {$table}");
        }

        $whereClause = implode(' AND ', array_map(fn($k) => "{$k} = ?", array_keys($conditions)));
        $values      = array_values($conditions);

        $stmtCheck = $this->db->prepare("SELECT 1 FROM {$table} WHERE {$whereClause}");
        $stmtCheck->execute($values);

        if ($stmtCheck->fetch()) {
            $this->db->prepare("DELETE FROM {$table} WHERE {$whereClause}")->execute($values);
            return 'unsubscribed';
        }

        $cols        = implode(', ', array_keys($conditions));
        $placeholders = implode(', ', array_fill(0, count($conditions), '?'));
        $this->db->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})")->execute($values);
        return 'subscribed';
    }

    /**
     * Versendet Bestätigungs-Mail an den Ersteller und Info-Mail an die Fachabteilung.
     *
     * @param int    $ticketId   Neu erstellte Ticket-ID
     * @param array  $data       Validierte Request-Daten
     * @param array  $user       Session-User-Daten
     * @param string $targetMail E-Mail-Adresse der zuständigen Fachabteilung
     */
    private function sendInitialNotifications(int $ticketId, array $data, array $user, string $targetMail): void
    {
        $title = htmlspecialchars($data['title']);
        $name  = htmlspecialchars($user['name']);
        $email = htmlspecialchars($user['email']);

        // Bestätigung an den Ersteller
        $this->mailService->sendNotification(
            $user['email'],
            "Bestätigung: Ticket #{$ticketId}",
            "Hallo {$name}, dein Ticket '<b>{$title}</b>' wurde erfolgreich erstellt."
        );

        // Standort-String aufbauen
        if ($data['location_type'] === 'building') {
            $location = 'Gebäude: ' . htmlspecialchars($data['building'] ?? '')
                . ', Raum: '   . htmlspecialchars($data['room']     ?? '');
        } else {
            $location = 'Sonstiger Ort: ' . htmlspecialchars($data['room'] ?? '');
        }

        $category    = htmlspecialchars($data['category']);
        $subCategory = htmlspecialchars($data['sub_category'] ?? 'Keine Angabe');
        $priority    = htmlspecialchars(strtoupper($data['priority']));
        $description = nl2br(htmlspecialchars($data['description'] ?? ''));

        $mailBody = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <h2 style='color: #0e64a6;'>Neues Ticket erstellt: #{$ticketId}</h2>
                <p><strong>Titel:</strong> {$title}</p>
                <hr>
                <p><strong>Von:</strong> {$name} ({$email})</p>
                <p><strong>Kategorie:</strong> {$category} ({$subCategory})</p>
                <p><strong>Priorität:</strong> {$priority}</p>
                <p><strong>Ort:</strong> {$location}</p>
                <hr>
                <p><strong>Beschreibung:</strong><br>{$description}</p>
            </div>
        ";

        $this->mailService->sendNotification(
            $targetMail,
            "NEUES TICKET: #{$ticketId} - {$data['title']}",
            $mailBody
        );
    }

    /**
     * Entschlüsselt einen verschlüsselten Feldwert in einem Ergebnis-Array.
     *
     * Liest das verschlüsselte Feld ($sourceKey), entschlüsselt es und speichert
     * den Klartext unter $targetKey. Das verschlüsselte Feld wird danach entfernt.
     *
     * @param array  $results   DB-Ergebnisse
     * @param string $sourceKey Schlüssel des verschlüsselten Felds
     * @param string $targetKey Schlüssel für den entschlüsselten Wert
     * @return array Ergebnisse mit entschlüsseltem Feld
     */
    private function decryptResults(array $results, string $sourceKey, string $targetKey): array
    {
        foreach ($results as &$item) {
            if (!empty($item[$sourceKey])) {
                $item[$targetKey] = Cipher::decrypt($item[$sourceKey], $this->encKey);
            }
            unset($item[$sourceKey]);
        }
        unset($item); // Referenz aus foreach aufräumen

        return $results;
    }

    /**
     * Gibt die Ziel-E-Mail-Adresse für eine Ticket-Kategorie zurück.
     *
     * @param string $cat Ticket-Kategorie (z.B. 'network', 'facility')
     * @return string E-Mail-Adresse der zuständigen Gruppe
     */
    private function mapCategoryToMail(string $cat): string
    {
        return match ($cat) {
            'network'  => $_ENV['TICKET_MAIL_NETWORK'],
            'facility' => $_ENV['TICKET_MAIL_FACILITY'],
            default    => $_ENV['TICKET_MAIL_IT_SUPPORT'],
        };
    }

    /**
     * Benachrichtigt alle Abonnenten eines Tickets per E-Mail.
     *
     * Abonnenten-Gruppen (per UNION zusammengeführt, keine Duplikate):
     *   1. Direkte Ticket-Abonnenten
     *   2. Ersteller des Tickets
     *   3. Raum-Abonnenten (falls Ticket einem Raum zugeordnet ist)
     *
     * @param int    $ticketId Ticket-ID
     * @param string $title    Ticket-Titel (für den Mail-Betreff)
     * @param string $message  HTML-Nachricht (muss bereits escaped sein)
     */
    private function notifySubscribers(int $ticketId, string $title, string $message): void
    {
        $stmt = $this->db->prepare("
            SELECT u.email_encrypted FROM users u
            JOIN ticket_subscriptions s ON u.id = s.user_id
            WHERE s.ticket_id = :tid1

            UNION

            SELECT u.email_encrypted FROM users u
            JOIN ticket_room_subscriptions rs ON u.id = rs.user_id
            JOIN tickets t ON rs.room_name = t.room
            WHERE t.id = :tid2 AND t.location_type = 'building'
        ");

        $stmt->execute([
            ':tid1' => $ticketId,
            ':tid2' => $ticketId,
        ]);
        $encryptedEmails = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $subject = "Update zu Ticket #{$ticketId}: " . htmlspecialchars($title);
        $body    = "<div style='font-family:Arial,sans-serif;'>{$message}</div>";

        foreach ($encryptedEmails as $encEmail) {
            $email = Cipher::decrypt($encEmail, $this->encKey);
            $this->mailService->sendNotification($email, $subject, $body);
        }
    }
}