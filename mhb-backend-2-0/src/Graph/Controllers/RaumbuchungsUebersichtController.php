<?php

namespace Kai\MhbBackend20\Graph\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Graph\Services\RaumbuchungsUebersichtService;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;

class RaumbuchungsUebersichtController extends BaseController {
    
    private RaumbuchungsUebersichtService $bookingService;

    public function __construct() {
        $this->bookingService = new RaumbuchungsUebersichtService();
    }

    /**
     * GET api/bookings/overview
     * Holt die Buchungen für alle Räume in einem Zeitfenster
     */
    public function getOverview() {
        AuthMiddleware::check();

        try {
            // 1. Parameter aus der URL holen mit Standardwerten
            // Wir nutzen ISO 8601 Format, wie es die Graph API meist erwartet
            $start = $this->getQueryParam('start', date('Y-m-d\T00:00:00\Z'));
            $end   = $this->getQueryParam('end', date('Y-m-d\T23:59:59\Z', strtotime('+7 days')));

            // 2. Service aufrufen
            $bookings = $this->bookingService->getAllBookings($start, $end);

            // 3. Antwort senden
            $this->jsonResponse($bookings);

        } catch (\Throwable $t) {
            // Wir loggen intern den Fehler (optional) und senden eine saubere Antwort
            // In der Produktion sollte man 'file' und 'line' ggf. nur im Debug-Mode mitsenden
            $this->errorResponse($t->getMessage(), 500);
        }
    }
}