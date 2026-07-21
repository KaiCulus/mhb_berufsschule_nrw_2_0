<?php

namespace Kai\MhbBackend20\Database\Controllers\Tickets;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use Kai\MhbBackend20\Database\Policies\TicketPolicy;
use Kai\MhbBackend20\Database\Services\Tickets\TicketService;
use Kai\MhbBackend20\Database\Services\Tickets\TicketImageService;
use Kai\MhbBackend20\Database\Services\Tickets\TicketSubscriptionService;
use Kai\MhbBackend20\Graph\Services\TicketNotificationService;

/**
 * TicketController
 *
 * Verantwortlich für den Kern-Lebenszyklus eines Tickets:
 *   Erstellen → Lesen → Kommentieren → Felder ändern → Auflösen
 *   sowie Abonnements.
 *
 * Bilder liegen im TicketImageController, Archiv-Funktionen im
 * TicketArchiveController.
 *
 * Der Controller enthält bewusst keine SQL- und keine Mail-Logik mehr —
 * er validiert Eingaben, prüft Berechtigungen über TicketPolicy und
 * delegiert an die Services.
 *
 * Sicherheitshinweis:
 *   User-IDs werden ausschließlich aus der Session gelesen — niemals aus
 *   URL-Parametern oder dem Request-Body. Das verhindert IDOR-Angriffe.
 */
class TicketController extends BaseController
{
    private TicketService $tickets;
    private TicketImageService $images;
    private TicketSubscriptionService $subscriptions;
    private TicketNotificationService $notifications;

    public function __construct()
    {
        $this->tickets       = new TicketService();
        $this->images        = new TicketImageService();
        $this->subscriptions = new TicketSubscriptionService();
        $this->notifications = new TicketNotificationService($this->subscriptions);
    }

    // =========================================================================
    // Ticket CRUD
    // =========================================================================

    /**
     * POST api/tickets
     *
     * Erstellt ein neues Ticket und versendet die initialen Benachrichtigungen.
     *
     * Erwarteter Request-Body:
     *   { "title", "category", "priority", "location_type",
     *     ["description", "sub_category", "building", "room"] }
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

        $data['description'] = $this->sanitize($data['description'] ?? '');
        $targetMail          = $this->mapCategoryToMail($data['category']);

        $ticketId = $this->tickets->create($data, $user['id'], $targetMail);

        // Bestätigung an User + Info an Fachabteilung
        $this->notifications->sendTicketCreated($ticketId, $data, $user, $targetMail);

        // Raum-Abonnenten benachrichtigen falls das Ticket einem Raum zugeordnet ist
        if ($data['location_type'] === 'building' && !empty($data['room'])) {
            $this->notifications->notifyRoomSubscribers(
                $ticketId,
                $data['title'],
                $data['room'],
                (int) $user['id']  // Ersteller kriegt schon die Bestätigungsmail
            );
        }

        $this->jsonResponse(['status' => 'success', 'ticket_id' => $ticketId], 201);
    }

    /**
     * GET api/tickets
     *
     * Alle aktiven Tickets (Processor-Übersicht), neueste zuerst.
     */
    public function getAll(): void
    {
        AuthMiddleware::check();

        $this->jsonResponse($this->tickets->getAll());
    }

    /**
     * GET api/tickets/user/{userId}
     *
     * Alle für den aktuellen User relevanten Tickets.
     *
     * Sicherheit: Der URL-Parameter wird bewusst ignoriert — es wird immer
     * die Session-ID verwendet. Das verhindert, dass ein User die Tickets
     * eines anderen abruft.
     *
     * @param int $userId URL-Parameter — wird bewusst ignoriert
     */
    public function getByUser(int $userId): void
    {
        $user = AuthMiddleware::check();

        $this->jsonResponse($this->tickets->getRelevantForUser((int) $user['id']));
    }

    /**
     * GET api/tickets/detail/{ticketId}
     *
     * Einzelnes Ticket mit Kommentaren, Bildern und Bearbeitungsrechten.
     *
     * @param int $ticketId Ticket-ID aus der URL
     */
    public function getDetail(int $ticketId): void
    {
        $user   = AuthMiddleware::check();
        $ticket = $this->tickets->findDetail($ticketId);

        if ($ticket === null) {
            $this->errorResponse('Ticket nicht gefunden.', 404);
        }

        // Bearbeitungsrechte für das Frontend vorberechnen
        $ticket['can_edit_status'] = TicketPolicy::canEdit($ticket, $user);
        $ticket['comments']        = $this->tickets->getComments($ticketId);
        $ticket['images']          = $this->images->getForTicket($ticketId);

        $this->jsonResponse($ticket);
    }

    // =========================================================================
    // Ticket-Aktionen
    // =========================================================================

    /**
     * POST api/tickets/comment
     *
     * Fügt einen Kommentar hinzu und benachrichtigt Abonnenten
     * sowie ggf. die zuständige Fachabteilung.
     *
     * Erwarteter Request-Body:
     *   { "ticketId": 42, "comment": "..." }
     */
    public function addComment(): void
    {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest(['ticketId' => 'int', 'comment' => 'string']);

        $ticketId = (int) $data['ticketId'];
        $ticket   = $this->tickets->findBasic($ticketId);

        if ($ticket === null) {
            $this->errorResponse('Ticket nicht gefunden.', 404);
        }

        $cleanComment = $this->sanitize($data['comment']);
        $this->tickets->addComment($ticketId, $user['id'], $cleanComment);

        $this->notifications->notifyCommentAdded(
            $ticketId,
            $ticket['title'],
            $cleanComment,
            $user,
            $ticket['assigned_group_mail'] ?? null,
            TicketPolicy::isProcessor()
        );

        $this->jsonResponse(['status' => 'success']);
    }

    /**
     * POST api/tickets/update-field
     *
     * Aktualisiert ein einzelnes Feld. Nur Ersteller und Processors.
     * Statusänderungen lösen eine Benachrichtigung an Abonnenten aus.
     *
     * Erwarteter Request-Body:
     *   { "ticketId": 42, "field": "status", "value": "in_progress" }
     */
    public function updateField(): void
    {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest([
            'ticketId' => 'int',
            'field'    => 'string',
            'value'    => 'string',
        ]);

        // Whitelist-Check vor allem anderen — Spaltennamen sind nicht bindbar
        if (!in_array($data['field'], TicketService::ALLOWED_UPDATE_FIELDS, strict: true)) {
            $this->errorResponse('Ungültiges Feld.', 400);
        }

        $ticketId = (int) $data['ticketId'];
        $ticket   = $this->tickets->findBasic($ticketId);

        if ($ticket === null) {
            $this->errorResponse('Ticket nicht gefunden.', 404);
        }

        if (!TicketPolicy::canEdit($ticket, $user)) {
            $this->errorResponse('Keine Berechtigung.', 403);
        }

        $cleanValue = $this->sanitize($data['value']);
        $this->tickets->updateField($ticketId, $data['field'], $cleanValue, $user['id']);

        if ($data['field'] === 'status') {
            $this->notifications->notifyStatusChanged(
                $ticketId,
                $ticket['title'],
                $cleanValue,
                (int) $user['id']  // Ändernden User nicht benachrichtigen
            );
        }

        $this->jsonResponse(['status' => 'success']);
    }

    /**
     * POST api/tickets/resolve
     *
     * Löst ein Ticket auf:
     *   - Ersteller:  Status 'archived' — Ticket bleibt in der DB, verschwindet
     *                 aber aus allen aktiven Ansichten
     *   - Processor:  Status 'resolved_by_staff' (bleibt 7 Tage erhalten)
     *
     * Der Ersteller-Check hat Vorrang, damit ein Processor sein eigenes Ticket
     * direkt archivieren kann statt es nur als erledigt zu markieren.
     *
     * Erwarteter Request-Body:
     *   { "ticketId": 42 }
     */
    public function resolveTicket(): void
    {
        $user     = AuthMiddleware::check();
        $data     = $this->validateRequest(['ticketId' => 'int']);
        $ticketId = (int) $data['ticketId'];

        $ticket = $this->tickets->findWithCreatorEmail($ticketId);

        if ($ticket === null) {
            $this->errorResponse('Ticket nicht gefunden.', 404);
        }

        if (TicketPolicy::isCreator($ticket, $user)) {
            $this->tickets->updateStatus($ticketId, 'archived');
            $this->jsonResponse(['status' => 'archived']);
        }

        if (TicketPolicy::isProcessor()) {
            $this->tickets->updateStatus($ticketId, 'resolved_by_staff');

            $this->notifications->notifyResolvedByStaff(
                $ticketId,
                $ticket['title'],
                $ticket['creator_email']
            );

            $this->jsonResponse(['status' => 'resolved']);
        }

        $this->errorResponse('Nicht autorisiert.', 403);
    }

    // =========================================================================
    // Berechtigungen
    // =========================================================================

    /**
     * GET api/tickets/canDeleteTicket/{ticketId}
     *
     * Prüft ob der aktuelle User das Ticket löschen darf.
     * Wird vom Frontend genutzt um den Löschen-Button ein-/auszublenden.
     *
     * @param int $ticketId Ticket-ID aus der URL
     */
    public function getCanDeleteTicket(int $ticketId): void
    {
        $user   = AuthMiddleware::check();
        $ticket = $this->tickets->findBasic($ticketId);

        if ($ticket === null) {
            $this->errorResponse('Ticket nicht gefunden.', 404);
        }

        $this->jsonResponse([
            'can_delete' => TicketPolicy::canDelete($ticket, $user),
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

        $status = $this->subscriptions->toggleTicket(
            (int) $user['id'],
            (int) $data['ticketId']
        );

        $this->jsonResponse(['status' => 'success', 'subscription' => $status]);
    }

    /**
     * POST api/tickets/subscribe-room
     *
     * Schaltet das Raum-Abonnement um. Raum-Abonnenten erhalten
     * Benachrichtigungen für alle Tickets in diesem Raum.
     *
     * Erwarteter Request-Body:
     *   { "room": "A201" }
     */
    public function toggleRoomSubscription(): void
    {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest(['room' => 'string']);

        $result = $this->subscriptions->toggleRoom((int) $user['id'], $data['room']);

        $this->jsonResponse([
            'status'       => 'success',
            'subscription' => $result['status'],
            'room'         => $result['room'],
        ]);
    }

    /**
     * GET api/tickets/subscribe-room/{userId}
     *
     * Alle Raum-Abonnements des aktuellen Users.
     * Sicherheit: URL-Parameter wird ignoriert — Session-ID wird verwendet.
     *
     * @param int $userId URL-Parameter — wird bewusst ignoriert
     */
    public function getRoomSubscriptions(int $userId): void
    {
        $user = AuthMiddleware::check();

        $this->jsonResponse($this->subscriptions->getRoomsForUser((int) $user['id']));
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Ziel-E-Mail-Adresse für eine Ticket-Kategorie.
     */
    private function mapCategoryToMail(string $category): string
    {
        return match ($category) {
            'network'  => $_ENV['TICKET_MAIL_NETWORK'],
            'facility' => $_ENV['TICKET_MAIL_FACILITY'],
            default    => $_ENV['TICKET_MAIL_IT_SUPPORT'],
        };
    }
}