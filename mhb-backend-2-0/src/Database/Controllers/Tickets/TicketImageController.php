<?php

namespace Kai\MhbBackend20\Database\Controllers\Tickets;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use Kai\MhbBackend20\Database\Policies\TicketPolicy;
use Kai\MhbBackend20\Database\Services\Tickets\TicketService;
use Kai\MhbBackend20\Database\Services\Tickets\TicketImageService;
use Kai\MhbBackend20\Database\Services\Tickets\ImageUploadException;

/**
 * TicketImageController
 *
 * Verwaltet Bilder an Tickets: Upload, Auflistung, Löschung und
 * auth-geschützte Auslieferung.
 *
 * Sichtbarkeit:
 *   Jeder authentifizierte User darf alle Tickets und damit auch alle
 *   zugehörigen Bilder einsehen. Für getImages() und serveImage() genügt
 *   deshalb eine gültige Session. Beim Hochladen und Löschen gelten
 *   engere Regeln (siehe TicketPolicy).
 */
class TicketImageController extends BaseController
{
    private TicketService $tickets;
    private TicketImageService $images;

    public function __construct()
    {
        $this->tickets = new TicketService();
        $this->images  = new TicketImageService();
    }

    /**
     * POST api/tickets/images/{ticketId}
     *
     * Lädt ein oder mehrere Bilder zu einem Ticket hoch.
     * Erlaubt: JPEG, PNG, WEBP — max. 5 MB pro Datei, max. 5 Bilder pro Ticket.
     * Nur Ersteller und Processors.
     *
     * @param int $ticketId Ticket-ID aus der URL
     */
    public function uploadImages(int $ticketId): void
    {
        $user   = AuthMiddleware::check();
        $ticket = $this->tickets->findBasic($ticketId);

        if ($ticket === null) {
            $this->errorResponse('Ticket nicht gefunden.', 404);
        }

        if (!TicketPolicy::canUploadImage($ticket, $user)) {
            $this->errorResponse('Keine Berechtigung.', 403);
        }

        if (!isset($_FILES['images'])) {
            $this->errorResponse('Keine Bilder übermittelt.', 400);
        }

        try {
            $uploaded = $this->images->store($ticketId, $_FILES['images'], (int) $user['id']);
        } catch (ImageUploadException $e) {
            $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }

        $this->jsonResponse(['status' => 'success', 'uploaded' => $uploaded], 201);
    }

    /**
     * GET api/tickets/images/{ticketId}
     *
     * Alle Bilder eines Tickets als Metadaten inkl. serve-URL.
     *
     * @param int $ticketId Ticket-ID aus der URL
     */
    public function getImages(int $ticketId): void
    {
        AuthMiddleware::check();

        $this->jsonResponse($this->images->getForTicket($ticketId));
    }

    /**
     * DELETE api/tickets/images/delete/{imageId}
     *
     * Löscht ein einzelnes Bild.
     * Nur Uploader, Ticket-Ersteller oder Processors.
     *
     * @param int $imageId Bild-ID aus der URL
     */
    public function deleteImage(int $imageId): void
    {
        $user  = AuthMiddleware::check();
        $image = $this->images->findWithTicketOwner($imageId);

        if ($image === null) {
            $this->errorResponse('Bild nicht gefunden.', 404);
        }

        if (!TicketPolicy::canDeleteImage($image, $user)) {
            $this->errorResponse('Keine Berechtigung.', 403);
        }

        $this->images->delete($imageId, $image['filename']);

        $this->jsonResponse(['status' => 'success']);
    }

    /**
     * GET api/tickets/images/serve/{imageId}
     *
     * Liefert eine Bilddatei auth-geschützt aus dem Dateisystem aus.
     * PHP prüft zuerst die Session und streamt die Datei erst danach per
     * readfile() — so ist kein Direktzugriff auf storage/ nötig.
     *
     * HTTP-Caching:
     *   Cache-Control und ETag sorgen dafür, dass der Browser Bilder lokal
     *   vorhält statt sie bei jedem Seitenaufruf neu zu laden.
     *
     * @param int $imageId Bild-ID aus der URL
     */
    public function serveImage(int $imageId): void
    {
        // Auth zuerst — kein Bild ohne gültige Session
        AuthMiddleware::check();

        $image = $this->images->findForServing($imageId);

        if ($image === null) {
            $this->errorResponse('Bild nicht gefunden.', 404);
        }

        try {
            $file = $this->images->resolveFile($image['filename']);
        } catch (ImageUploadException $e) {
            $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }

        $etag = '"' . md5($image['filename'] . $file['size']) . '"';

        // ETag-basiertes Caching: 304 wenn der Client das Bild noch hat
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? null) === $etag) {
            http_response_code(304);
            exit;
        }

        header('Content-Type: '   . $file['mime']);
        header('Content-Length: ' . $file['size']);
        header('Content-Disposition: inline; filename="' . rawurlencode($image['original_name']) . '"');
        header('ETag: '           . $etag);
        header('Last-Modified: '  . gmdate('D, d M Y H:i:s', $file['mtime']) . ' GMT');
        header('Cache-Control: private, max-age=3600'); // 1 Stunde im Browser cachen
        header('X-Content-Type-Options: nosniff');

        readfile($file['path']);
        exit;
    }
}