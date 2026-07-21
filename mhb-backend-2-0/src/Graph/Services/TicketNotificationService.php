<?php

namespace Kai\MhbBackend20\Graph\Services;

use Kai\MhbBackend20\Database\Services\Tickets\TicketSubscriptionService;

/**
 * TicketNotificationService
 *
 * Einziger Ort an dem Ticket-E-Mails zusammengebaut und versendet werden.
 *
 * Vorher lagen die HTML-Strings inline im TicketController verstreut, was
 * sowohl den Controller aufgebläht als auch das Escaping unübersichtlich
 * gemacht hat. Jetzt gilt durchgängig:
 *
 *   Templates escapen ihre eigenen Werte — dieser Service übergibt Rohdaten.
 *
 * Der Versand selbst geht weiter über den TicketMailService (Graph API).
 */
class TicketNotificationService
{
    private TicketMailService $mailService;
    private TicketSubscriptionService $subscriptions;
    private string $templatePath;
    private string $frontendUrl;

    public function __construct(?TicketSubscriptionService $subscriptions = null)
    {
        $this->mailService   = new TicketMailService();
        $this->subscriptions = $subscriptions ?? new TicketSubscriptionService();

        // Templates liegen ausserhalb von src/ — Pfad relativ zum Projekt-Root
        $this->templatePath = dirname(__DIR__, 3) . '/resources/mail/ticket/';
        $this->frontendUrl   = rtrim($_ENV['MHB_FRONTEND_URL'] ?? '', '/');
    }

    // =========================================================================
    // Ticket-Erstellung
    // =========================================================================

    /**
     * Versendet Bestätigung an den Ersteller und Info an die Fachabteilung.
     *
     * @param int    $ticketId
     * @param array  $data       Validierte Request-Daten (roh, unescaped)
     * @param array  $user       Session-User (id, name, email)
     * @param string $targetMail Adresse der zuständigen Fachabteilung
     */
    public function sendTicketCreated(int $ticketId, array $data, array $user, string $targetMail): void
    {
        // 1) Bestätigung an den Ersteller
        $this->mailService->sendNotification(
            $user['email'],
            "Bestätigung: Ticket #{$ticketId}",
            $this->wrap(
                $this->render('created_confirmation', [
                    'ticketId' => $ticketId,
                    'title'    => $data['title'],
                    'userName' => $user['name'],
                ]),
                $ticketId
            )
        );

        // 2) Detaillierte Info an die Fachabteilung
        $this->mailService->sendNotification(
            $targetMail,
            "NEUES TICKET: #{$ticketId} - {$data['title']}",
            $this->render('created_department', [
                'ticketId'     => $ticketId,
                'title'        => $data['title'],
                'userName'     => $user['name'],
                'userEmail'    => $user['email'],
                'category'     => $data['category'],
                'subCategory'  => $data['sub_category'] ?? 'Keine Angabe',
                'priority'     => $data['priority'],
                'locationType' => $data['location_type'],
                'building'     => $data['building'] ?? '',
                'room'         => $data['room']     ?? '',
                'description'  => $data['description'] ?? '',
            ])
        );
    }

    /**
     * Informiert Raum-Abonnenten über ein neues Ticket in ihrem Raum.
     * Der Ersteller wird ausgenommen — er hat bereits die Bestätigung erhalten.
     */
    public function notifyRoomSubscribers(int $ticketId, string $title, string $room, int $creatorId): void
    {
        $this->broadcast(
            $ticketId,
            $title,
            $this->render('room_ticket_created', ['room' => $room]),
            $creatorId
        );
    }

    // =========================================================================
    // Ticket-Updates
    // =========================================================================

    /**
     * Benachrichtigt Abonnenten über eine neue Notiz und informiert
     * zusätzlich die zuständige Fachabteilung.
     *
     * Die Fachabteilung wird nur angeschrieben wenn der Kommentator selbst
     * kein Processor ist — sonst würde die Gruppe sich selbst benachrichtigen.
     *
     * @param string|null $groupMail Adresse der Fachabteilung, null = überspringen
     */
    public function notifyCommentAdded(
        int $ticketId,
        string $ticketTitle,
        string $comment,
        array $author,
        ?string $groupMail,
        bool $authorIsProcessor
    ): void {
        $body = $this->render('comment_added', [
            'authorName' => $author['name'],
            'comment'    => $comment,
        ]);

        $this->broadcast($ticketId, $ticketTitle, $body, (int) $author['id']);

        if (!$authorIsProcessor && !empty($groupMail)) {
            $this->mailService->sendNotification(
                $groupMail,
                "Neue Notiz zu Ticket #{$ticketId}: " . htmlspecialchars($ticketTitle),
                $this->wrap($body, $ticketId)
            );
        }
    }

    /**
     * Benachrichtigt Abonnenten über eine Statusänderung.
     * Der ändernde User wird ausgenommen.
     */
    public function notifyStatusChanged(int $ticketId, string $ticketTitle, string $status, int $editorId): void
    {
        $this->broadcast(
            $ticketId,
            $ticketTitle,
            $this->render('status_changed', ['status' => $status]),
            $editorId
        );
    }

    /**
     * Informiert den Ersteller, dass ein Processor sein Ticket
     * als erledigt markiert hat.
     *
     * @param string $creatorEmail Klartext-Adresse (bereits entschlüsselt)
     */
    public function notifyResolvedByStaff(int $ticketId, string $title, string $creatorEmail): void
    {
        $this->mailService->sendNotification(
            $creatorEmail,
            "Dein Ticket #{$ticketId} wurde bearbeitet",
            $this->wrap($this->render('resolved_by_staff', ['title' => $title]), $ticketId)
        );
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Verschickt eine Nachricht an alle Abonnenten eines Tickets.
     *
     * @param string   $body          Bereits gerendertes, escapetes HTML
     * @param int|null $excludeUserId Auslösender User — erhält keine Mail
     */
    private function broadcast(int $ticketId, string $title, string $body, ?int $excludeUserId = null): void
    {
        $recipients = $this->subscriptions->getNotificationRecipients($ticketId, $excludeUserId);

        if ($recipients === []) {
            return;
        }

        $subject = "Update zu Ticket #{$ticketId}: " . htmlspecialchars($title);
        $html    = $this->wrap($body, $ticketId);

        foreach ($recipients as $email) {
            $this->mailService->sendNotification($email, $subject, $html);
        }
    }

    /**
     * Rendert ein Template zu einem HTML-String.
     *
     * Das Template ist selbst für das Escaping seiner Werte verantwortlich.
     *
     * @param string $template Dateiname ohne .php
     * @param array  $vars     Werden als lokale Variablen extrahiert
     * @throws \RuntimeException wenn das Template fehlt
     */
    private function render(string $template, array $vars = []): string
    {
        $file = $this->templatePath . $template . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException("Mail-Template nicht gefunden: {$template}");
        }

        extract($vars, EXTR_SKIP);

        ob_start();
        require $file;

        return trim(ob_get_clean());
    }

    /**
     * Legt das Basis-Layout um einen gerenderten Inhalt.
     *
     * @param int|null $ticketId Wenn gesetzt, wird ein Deep-Link-Button angehängt
     */
    private function wrap(string $content, ?int $ticketId = null): string
    {
        $ticketUrl = $ticketId !== null ? $this->ticketUrl($ticketId) : null;

        ob_start();
        require $this->templatePath . 'layout.php';

        return trim(ob_get_clean());
    }

    /**
     * Deep-Link auf die Ticketseite mit vorausgewähltem Ticket.
     * Das Frontend liest ?ticket= aus und öffnet das Detail-Modal.
     */
    private function ticketUrl(int $ticketId): string
    {
        return $this->frontendUrl . '/tickets?ticket=' . $ticketId;
    }
}