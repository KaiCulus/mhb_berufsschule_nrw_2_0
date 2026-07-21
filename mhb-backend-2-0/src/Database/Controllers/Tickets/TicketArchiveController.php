<?php

namespace Kai\MhbBackend20\Database\Controllers\Tickets;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Database\Policies\TicketPolicy;
use Kai\MhbBackend20\Database\Services\Tickets\TicketService;

/**
 * TicketArchiveController
 *
 * Archiv-Verwaltung: Einsicht, Wiederherstellung und automatisches
 * Aufräumen alter erledigter Tickets.
 *
 * Alle Endpunkte hier sind ausschließlich für die Processor-Gruppe.
 */
class TicketArchiveController extends BaseController
{
    /** Tickets werden nach so vielen Tagen automatisch archiviert. */
    private const CLEANUP_AFTER_DAYS = 7;

    private TicketService $tickets;

    public function __construct()
    {
        $this->tickets = new TicketService();
    }

    /**
     * GET api/tickets/archive
     *
     * Alle archivierten Tickets, zuletzt geändertes zuerst.
     */
    public function getArchivedTickets(): void
    {
        $this->requireGroup(TicketPolicy::ROLE_PROCESSOR);

        $this->jsonResponse($this->tickets->getArchived());
    }

    /**
     * POST api/tickets/restore
     *
     * Stellt ein archiviertes Ticket wieder her (Status zurück auf 'open').
     *
     * Erwarteter Request-Body:
     *   { "ticketId": 42 }
     */
    public function restoreTicket(): void
    {
        $this->requireGroup(TicketPolicy::ROLE_PROCESSOR);

        $data     = $this->validateRequest(['ticketId' => 'int']);
        $ticketId = (int) $data['ticketId'];

        if (!$this->tickets->isArchived($ticketId)) {
            $this->errorResponse('Archiviertes Ticket nicht gefunden.', 404);
        }

        $this->tickets->updateStatus($ticketId, 'open');

        $this->jsonResponse(['status' => 'success', 'ticket_id' => $ticketId]);
    }

    /**
     * POST api/tickets/cleanup
     *
     * Archiviert alle Tickets die seit mehr als CLEANUP_AFTER_DAYS Tagen
     * als 'resolved_by_staff' markiert sind.
     */
    public function cleanupOldTickets(): void
    {
        $this->requireGroup(TicketPolicy::ROLE_PROCESSOR);

        $count = $this->tickets->archiveResolvedOlderThan(self::CLEANUP_AFTER_DAYS);

        $this->jsonResponse(['status' => 'success', 'archived_count' => $count]);
    }
}