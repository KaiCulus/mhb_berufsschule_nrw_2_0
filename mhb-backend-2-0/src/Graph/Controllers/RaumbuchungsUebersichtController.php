<?php

namespace Kai\MhbBackend20\Graph\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Graph\Services\RaumbuchungsUebersichtService;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;

/**
 * RaumbuchungsUebersichtController
 *
 * Liefert Raumbuchungs-Übersichten aus Microsoft 365 Kalender-Ressourcen.
 * Delegiert die Graph API Kommunikation an den RaumbuchungsUebersichtService.
 */
class RaumbuchungsUebersichtController extends BaseController
{
    private RaumbuchungsUebersichtService $bookingService;

    public function __construct()
    {
        $this->bookingService = new RaumbuchungsUebersichtService();
    }

    /**
     * GET api/rooms/bookings?start=...&end=...
     *
     * Gibt alle Raumbuchungen in einem Zeitfenster zurück.
     *
     * Query-Parameter (optional, beide im ISO 8601 Format):
     *   start — Beginn des Zeitfensters (Standard: heute 00:00 UTC)
     *   end   — Ende des Zeitfensters   (Standard: heute + 7 Tage 23:59 UTC)
     *
     * Antwort: Array von Buchungsobjekten sortiert nach Startzeit
     */
    public function getOverview(): void
    {
        AuthMiddleware::check();

        $start = $this->getQueryParam('start', date('Y-m-d\T00:00:00\Z'));
        $end   = $this->getQueryParam('end',   date('Y-m-d\T23:59:59\Z', strtotime('+7 days')));

        try {
            $bookings = $this->bookingService->getAllBookings($start, $end);
            $this->jsonResponse($bookings);

        } catch (\Throwable $e) {
            // Internes Detail loggen — generische Meldung an den Client
            $errorId = uniqid('booking_err_');
            error_log("[{$errorId}] RaumbuchungsUebersicht fehlgeschlagen: " . $e->getMessage());
            $this->errorResponse("Raumbuchungen konnten nicht geladen werden. Referenz: {$errorId}", 500);
        }
    }
}