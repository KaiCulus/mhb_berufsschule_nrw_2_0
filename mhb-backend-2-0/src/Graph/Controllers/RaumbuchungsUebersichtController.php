<?php

namespace Kai\MhbBackend20\Graph\Controllers;

use Kai\MhbBackend20\Graph\Services\RaumbuchungsUebersichtService;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;

class RaumbuchungsUebersichtController {
    private RaumbuchungsUebersichtService $bookingService;

    public function __construct() {
        $this->bookingService = new RaumbuchungsUebersichtService();
    }

    public function getOverview() {
        try {
            AuthMiddleware::check();
            $start = $_GET['start'] ?? date('Y-m-d\T00:00:00\Z');
            $end = $_GET['end'] ?? date('Y-m-d\T23:59:59\Z', strtotime('+7 days'));

            $bookings = $this->bookingService->getAllBookings($start, $end);

            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'data' => $bookings]);
        } catch (\Throwable $t) { // Fängt auch Fatal Errors ab
            header('Content-Type: application/json', true, 500);
            echo json_encode([
                'status' => 'error',
                'message' => $t->getMessage(),
                'file' => $t->getFile(),
                'line' => $t->getLine()
            ]);
        }
        exit;
    }
}