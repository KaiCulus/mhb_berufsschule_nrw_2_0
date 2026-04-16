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
 *   Bild-Upload → Archivierung → Wiederherstellung
 *
 * Berechtigungsmodell:
 *   - Authentifizierte User:  Tickets erstellen, eigene einsehen, kommentieren
 *   - Ersteller:             Eigenes Ticket bearbeiten und löschen
 *   - Processor-Gruppe:      Alle Tickets bearbeiten, als gelöst markieren,
 *                            Cleanup, Archiv einsehen, Tickets wiederherstellen
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

    /**
     * Erlaubte MIME-Typen für Bild-Uploads.
     */
    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Maximale Dateigröße pro Bild (5 MB).
     */
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024;

    /**
     * Maximale Anzahl an Bildern pro Ticket.
     */
    private const MAX_IMAGES_PER_TICKET = 5;

    private \PDO $db;
    private TicketMailService $mailService;
    private string $encKey;

    public function __construct()
    {
        $this->db          = DB::getInstance()->getConnection();
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
                "Ein neues Ticket wurde für den Raum <b>{$room}</b> erstellt...",
                $user['id']  // ← Ersteller kriegt schon die Bestätigungsmail
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
            WHERE t.status != 'archived'
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
            WHERE t.status != 'archived'
              AND (
                t.created_by = ?
                OR s.user_id IS NOT NULL
                OR (rs.user_id IS NOT NULL AND t.location_type = 'building')
              )
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

        // Bilder laden
        $ticket['images'] = $this->fetchTicketImages($ticketId);

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

        $stmtT = $this->db->prepare("SELECT title, assigned_group_mail FROM tickets WHERE id = ?");
        $stmtT->execute([(int) $data['ticketId']]);
        $ticket = $stmtT->fetch();

        if (!$ticket) {
            $this->errorResponse('Ticket nicht gefunden.', 404);
        }

        $cleanComment = $this->sanitize($data['comment']);

        $this->db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment) VALUES (?, ?, ?)")
                 ->execute([(int) $data['ticketId'], $user['id'], $cleanComment]);

        $authorName  = htmlspecialchars($user['name']);
        $commentHtml = nl2br(htmlspecialchars($cleanComment));
        $this->notifySubscribers(
            (int) $data['ticketId'],
            $ticket['title'],
            "Neue Notiz von <b>{$authorName}</b>:<br><i>{$commentHtml}</i>",
            $user['id']  // ← Kommentator nicht benachrichtigen
        );

        // Zuständige Gruppe benachrichtigen — aber nur wenn der Kommentator
        // kein Processor ist (sonst schreibt die Gruppe sich selbst an)
        if (!AuthMiddleware::hasGroup(self::ROLE_PROCESSOR) && !empty($ticket['assigned_group_mail'])) {
            $title = htmlspecialchars($ticket['title']);
            $this->mailService->sendNotification(
                $ticket['assigned_group_mail'],
                "Neue Notiz zu Ticket #{$data['ticketId']}: {$title}",
                "<div style='font-family:Arial,sans-serif;'>Neue Notiz von <b>{$authorName}</b>:<br><i>{$commentHtml}</i></div>"
            );
        }

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
                "Status wurde auf <b>'{$statusHtml}'</b> geändert.",
                $user['id']  // ← Ändernden User nicht benachrichtigen
            );
        }

        $this->jsonResponse(['status' => 'success']);
    }

    /**
     * POST api/tickets/resolve
     *
     * Löst ein Ticket auf:
     *   - Processor:  Markiert als 'resolved_by_staff' (bleibt 7 Tage erhalten)
     *   - Ersteller:  Setzt Status auf 'archived' — Ticket bleibt in der DB,
     *                 wird aber aus allen aktiven Ansichten herausgefiltert
     *
     * Erwarteter Request-Body:
     *   { "ticketId": 42 }
     */
    public function resolveTicket(): void
    {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest(['ticketId' => 'int']);
        $ticketId = (int) $data['ticketId'];

        $stmt = $this->db->prepare("
            SELECT t.created_by, t.title, u.email_encrypted AS creator_email_enc
            FROM tickets t
            JOIN users u ON t.created_by = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            $this->errorResponse('Ticket nicht gefunden.', 404);
        }

        // Ersteller-Check hat Vorrang — auch ein Processor soll sein eigenes
        // Ticket direkt archivieren können statt es nur als erledigt zu markieren
        if ((int) $ticket['created_by'] === $user['id']) {
            $this->updateTicketStatus($ticketId, 'archived');
            $this->jsonResponse(['status' => 'archived']);
        } elseif (AuthMiddleware::hasGroup(self::ROLE_PROCESSOR)) {
            $this->updateTicketStatus($ticketId, 'resolved_by_staff');

            // Ersteller informieren, dass sein Ticket vom Bearbeiter als erledigt markiert wurde
            $creatorEmail = Cipher::decrypt($ticket['creator_email_enc'], $this->encKey);
            $title        = htmlspecialchars($ticket['title']);
            $this->mailService->sendNotification(
                $creatorEmail,
                "Dein Ticket #{$ticketId} wurde bearbeitet",
                "<div style='font-family:Arial,sans-serif;'>
                    Dein Ticket '<b>{$title}</b>' wurde vom Bearbeiter als erledigt markiert.
                    <br><br>Falls du noch Rückfragen hast, kannst du das Ticket weiterhin kommentieren.
                </div>"
            );

            $this->jsonResponse(['status' => 'resolved']);
        } else {
            $this->errorResponse('Nicht autorisiert.', 403);
        }
    }

    /**
     * POST api/tickets/cleanup
     *
     * Setzt alle Tickets mit Status 'resolved_by_staff' die älter als 7 Tage
     * sind auf 'archived'. Nur für Processors zugänglich.
     */
    public function cleanupOldTickets(): void
    {
        $this->requireGroup(self::ROLE_PROCESSOR);

        $stmt = $this->db->prepare("
            UPDATE tickets
            SET status = 'archived', updated_at = NOW()
            WHERE status = 'resolved_by_staff'
              AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();

        $this->jsonResponse(['status' => 'success', 'archived_count' => $stmt->rowCount()]);
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
    // Bild-Upload
    // =========================================================================

    /**
     * POST api/tickets/images/{ticketId}
     *
     * Lädt ein oder mehrere Bilder zu einem Ticket hoch.
     * Erlaubte Typen: JPEG, PNG, WEBP. Maximale Größe: 5 MB pro Datei.
     * Maximal 5 Bilder pro Ticket.
     *
     * Nur der Ersteller des Tickets oder Processors dürfen Bilder hochladen.
     *
     * @param int $ticketId Ticket-ID aus der URL
     */
    public function uploadImages(int $ticketId): void
    {
        $user = AuthMiddleware::check();

        // Ticket-Existenz und Berechtigung prüfen
        $stmtTicket = $this->db->prepare("SELECT created_by FROM tickets WHERE id = ?");
        $stmtTicket->execute([$ticketId]);
        $ticket = $stmtTicket->fetch();

        if (!$ticket) {
            $this->errorResponse('Ticket nicht gefunden.', 404);
        }

        $isProcessor = AuthMiddleware::hasGroup(self::ROLE_PROCESSOR);
        if (!$isProcessor && (int) $ticket['created_by'] !== $user['id']) {
            $this->errorResponse('Keine Berechtigung.', 403);
        }

        // Aktuelle Bildanzahl prüfen
        $stmtCount = $this->db->prepare("SELECT COUNT(*) FROM ticket_images WHERE ticket_id = ?");
        $stmtCount->execute([$ticketId]);
        $currentCount = (int) $stmtCount->fetchColumn();

        if (!isset($_FILES['images'])) {
            $this->errorResponse('Keine Bilder übermittelt.', 400);
        }

        // $_FILES['images'] normalisieren — unterstützt sowohl single als auch multiple uploads
        $files = $this->normalizeFileArray($_FILES['images']);

        if (($currentCount + count($files)) > self::MAX_IMAGES_PER_TICKET) {
            $remaining = self::MAX_IMAGES_PER_TICKET - $currentCount;
            $this->errorResponse("Maximal " . self::MAX_IMAGES_PER_TICKET . " Bilder pro Ticket erlaubt. Noch {$remaining} möglich.", 400);
        }

        $uploadDir = $_ENV['TICKET_IMAGE_PATH'] ?? '/var/www/uploads/ticket_images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploaded = [];

        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->errorResponse('Fehler beim Upload einer Datei.', 400);
            }

            if ($file['size'] > self::MAX_IMAGE_SIZE) {
                $this->errorResponse('Eine Datei überschreitet die maximale Größe von 5 MB.', 400);
            }

            // MIME-Typ über finfo prüfen — nicht dem Client-Header vertrauen
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            if (!in_array($mimeType, self::ALLOWED_IMAGE_TYPES, strict: true)) {
                $this->errorResponse('Ungültiger Dateityp. Erlaubt: JPEG, PNG, WEBP.', 400);
            }

            // Sicheren Dateinamen generieren (UUID + Originalerweiterung)
            $ext      = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            };
            $filename = bin2hex(random_bytes(16)) . '.' . $ext;
            $destPath = $uploadDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $this->errorResponse('Datei konnte nicht gespeichert werden.', 500);
            }

            $this->db->prepare("
                INSERT INTO ticket_images (ticket_id, filename, original_name, uploaded_by)
                VALUES (?, ?, ?, ?)
            ")->execute([$ticketId, $filename, $file['name'], $user['id']]);

            $uploaded[] = $filename;
        }

        $this->jsonResponse(['status' => 'success', 'uploaded' => $uploaded], 201);
    }

    /**
     * GET api/tickets/images/{ticketId}
     *
     * Gibt alle Bilder eines Tickets als URL-Liste zurück.
     *
     * @param int $ticketId Ticket-ID aus der URL
     */
    public function getImages(int $ticketId): void
    {
        AuthMiddleware::check();

        $this->jsonResponse($this->fetchTicketImages($ticketId));
    }

    /**
     * DELETE api/tickets/images/{imageId}
     *
     * Löscht ein einzelnes Bild.
     * Nur der Uploader, der Ticket-Ersteller oder Processors dürfen löschen.
     *
     * @param int $imageId Bild-ID aus der URL
     */
    public function deleteImage(int $imageId): void
    {
        $user = AuthMiddleware::check();

        $stmt = $this->db->prepare("
            SELECT ti.*, t.created_by AS ticket_creator
            FROM ticket_images ti
            JOIN tickets t ON ti.ticket_id = t.id
            WHERE ti.id = ?
        ");
        $stmt->execute([$imageId]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            $this->errorResponse('Bild nicht gefunden.', 404);
        }

        $isProcessor     = AuthMiddleware::hasGroup(self::ROLE_PROCESSOR);
        $isUploader      = (int) $image['uploaded_by'] === $user['id'];
        $isTicketCreator = (int) $image['ticket_creator'] === $user['id'];

        if (!$isProcessor && !$isUploader && !$isTicketCreator) {
            $this->errorResponse('Keine Berechtigung.', 403);
        }

        // Datei vom Dateisystem löschen
        $uploadDir = $_ENV['TICKET_IMAGE_PATH'] ?? '/var/www/uploads/ticket_images/';
        $filePath  = $uploadDir . $image['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->db->prepare("DELETE FROM ticket_images WHERE id = ?")->execute([$imageId]);

        $this->jsonResponse(['status' => 'success']);
    }

    /**
     * GET api/tickets/images/serve/{imageId}
     *
     * Liefert eine Bilddatei auth-geschützt aus dem Dateisystem aus.
     * Umgeht den Webserver — PHP prüft die Session und streamt die Datei
     * erst danach per readfile(). So ist kein Direktzugriff auf storage/ nötig.
     *
     * HTTP-Caching:
     *   Setzt Cache-Control und ETag damit der Browser Bilder lokal cached
     *   und nicht bei jedem Seitenaufruf neu lädt.
     *
     * @param int $imageId Bild-ID aus der URL
     */
    public function serveImage(int $imageId): void
    {
        // Auth zuerst — kein Bild ohne gültige Session
        AuthMiddleware::check();

        $stmt = $this->db->prepare("
            SELECT ti.filename, ti.original_name, ti.ticket_id
            FROM ticket_images ti
            WHERE ti.id = ?
        ");
        $stmt->execute([$imageId]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            $this->errorResponse('Bild nicht gefunden.', 404);
        }

        $uploadDir = rtrim($_ENV['TICKET_IMAGE_PATH'] ?? '/var/www/uploads/ticket_images', '/') . '/';
        $filePath  = $uploadDir . basename($image['filename']); // basename() verhindert Path-Traversal

        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->errorResponse('Datei nicht verfügbar.', 404);
        }

        // MIME-Typ aus der tatsächlichen Datei lesen — nicht aus der DB
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        // Sicherheits-Fallback: nur erlaubte Typen ausliefern
        if (!in_array($mimeType, self::ALLOWED_IMAGE_TYPES, strict: true)) {
            $this->errorResponse('Ungültiger Dateityp.', 415);
        }

        $fileSize = filesize($filePath);
        $etag     = '"' . md5($image['filename'] . $fileSize) . '"';
        $lastMod  = filemtime($filePath);

        // ETag-basiertes Caching: 304 zurückgeben wenn Client das Bild noch hat
        if (
            isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
            $_SERVER['HTTP_IF_NONE_MATCH'] === $etag
        ) {
            http_response_code(304);
            exit;
        }

        // Header setzen und Datei streamen
        header('Content-Type: '        . $mimeType);
        header('Content-Length: '      . $fileSize);
        header('Content-Disposition: inline; filename="' . rawurlencode($image['original_name']) . '"');
        header('ETag: '                . $etag);
        header('Last-Modified: '       . gmdate('D, d M Y H:i:s', $lastMod) . ' GMT');
        header('Cache-Control: private, max-age=3600'); // 1 Stunde im Browser cachen
        header('X-Content-Type-Options: nosniff');

        readfile($filePath);
        exit;
    }

    // =========================================================================
    // Archiv
    // =========================================================================

    /**
     * GET api/tickets/archive
     *
     * Gibt alle Tickets mit Status 'archived' zurück, absteigend nach updated_at.
     * Nur für Processors zugänglich.
     */
    public function getArchivedTickets(): void
    {
        $this->requireGroup(self::ROLE_PROCESSOR);

        $stmt = $this->db->prepare("
            SELECT t.*, u.display_name_encrypted AS creator_name_enc
            FROM tickets t
            JOIN users u ON t.created_by = u.id
            WHERE t.status = 'archived'
            ORDER BY t.updated_at DESC
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->jsonResponse(
            $this->decryptResults($results, 'creator_name_enc', 'creator_name')
        );
    }

    /**
     * POST api/tickets/restore
     *
     * Stellt ein archiviertes Ticket wieder her.
     * Setzt den Status von 'archived' zurück auf 'open'.
     * Nur für Processors zugänglich.
     *
     * Erwarteter Request-Body:
     *   { "ticketId": 42 }
     */
    public function restoreTicket(): void
    {
        $this->requireGroup(self::ROLE_PROCESSOR);

        $data     = $this->validateRequest(['ticketId' => 'int']);
        $ticketId = (int) $data['ticketId'];

        $stmt = $this->db->prepare("SELECT id FROM tickets WHERE id = ? AND status = 'archived'");
        $stmt->execute([$ticketId]);

        if (!$stmt->fetch()) {
            $this->errorResponse('Archiviertes Ticket nicht gefunden.', 404);
        }

        $this->updateTicketStatus($ticketId, 'open');

        $this->jsonResponse(['status' => 'success', 'ticket_id' => $ticketId]);
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

        $whereClause  = implode(' AND ', array_map(fn($k) => "{$k} = ?", array_keys($conditions)));
        $values       = array_values($conditions);

        $stmtCheck = $this->db->prepare("SELECT 1 FROM {$table} WHERE {$whereClause}");
        $stmtCheck->execute($values);

        if ($stmtCheck->fetch()) {
            $this->db->prepare("DELETE FROM {$table} WHERE {$whereClause}")->execute($values);
            return 'unsubscribed';
        }

        $cols         = implode(', ', array_keys($conditions));
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
    private function notifySubscribers(int $ticketId, string $title, string $message, ?int $excludeUserId = null): void
    {
        $stmt = $this->db->prepare("
            SELECT u.id AS user_id, u.email_encrypted FROM users u
            JOIN ticket_subscriptions s ON u.id = s.user_id
            WHERE s.ticket_id = :tid1

            UNION

            SELECT u.id AS user_id, u.email_encrypted FROM users u
            JOIN tickets t ON u.id = t.created_by
            WHERE t.id = :tid2

            UNION

            SELECT u.id AS user_id, u.email_encrypted FROM users u
            JOIN ticket_room_subscriptions rs ON u.id = rs.user_id
            JOIN tickets t ON rs.room_name = t.room
            WHERE t.id = :tid3 AND t.location_type = 'building'
        ");

        $stmt->execute([
            ':tid1' => $ticketId,
            ':tid2' => $ticketId,
            ':tid3' => $ticketId,
        ]);

        $subject = "Update zu Ticket #{$ticketId}: " . htmlspecialchars($title);
        $body    = "<div style='font-family:Arial,sans-serif;'>{$message}</div>";

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($excludeUserId !== null && (int) $row['user_id'] === $excludeUserId) {
                continue;
            }
            $email = Cipher::decrypt($row['email_encrypted'], $this->encKey);
            $this->mailService->sendNotification($email, $subject, $body);
        }
    }

    /**
     * Lädt alle Bilder eines Tickets und gibt sie als Array mit URLs zurück.
     *
     * Die URL zeigt auf den auth-geschützten Serve-Endpunkt (serveImage),
     * nicht direkt auf das Dateisystem — so sind Bilder ohne gültige Session
     * nicht abrufbar.
     *
     * @param int $ticketId Ticket-ID
     * @return array Array mit Bild-Metadaten und serve-URLs
     */
    private function fetchTicketImages(int $ticketId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, filename, original_name, uploaded_at
            FROM ticket_images
            WHERE ticket_id = ?
            ORDER BY uploaded_at ASC
        ");
        $stmt->execute([$ticketId]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // URL zeigt auf serveImage() — Dateiname wird bewusst nicht mitgegeben,
        // damit das Frontend keine Direktzugriffe auf storage/ konstruieren kann.
        $apiBase = rtrim($_ENV['APP_URL'] ?? '', '/');

        foreach ($images as &$image) {
            $image['url'] = $apiBase . '/api/tickets/images/serve/' . $image['id'];
            unset($image['filename']); // Dateiname bleibt serverseitig
        }
        unset($image);

        return $images;
    }

    /**
     * Normalisiert das $_FILES-Array für Einzel- und Mehrfach-Uploads.
     *
     * PHP strukturiert $_FILES['images'] bei multiple=true anders als bei single.
     * Diese Methode vereinheitlicht beide Formate zu einem Array von Datei-Arrays.
     *
     * @param array $filesInput $_FILES['images']
     * @return array Normalisiertes Array von Datei-Einträgen
     */
    private function normalizeFileArray(array $filesInput): array
    {
        if (!is_array($filesInput['name'])) {
            // Einzelne Datei
            return [$filesInput];
        }

        // Mehrere Dateien: PHP's transponiertes Format umkehren
        $normalized = [];
        foreach ($filesInput['name'] as $i => $name) {
            $normalized[] = [
                'name'     => $name,
                'type'     => $filesInput['type'][$i],
                'tmp_name' => $filesInput['tmp_name'][$i],
                'error'    => $filesInput['error'][$i],
                'size'     => $filesInput['size'][$i],
            ];
        }

        return $normalized;
    }
}
