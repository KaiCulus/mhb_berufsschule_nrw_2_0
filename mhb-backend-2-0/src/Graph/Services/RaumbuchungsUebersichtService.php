<?php

namespace Kai\MhbBackend20\Graph\Services;

use Kai\MhbBackend20\Graph\GraphClient;

class RaumbuchungsUebersichtService {
    private GraphClient $graphClient;
    private array $rooms;

    public function __construct() {
        $this->graphClient = new GraphClient();
        // Räume aus der ENV laden
        $this->rooms = [
            'R15'   => $_ENV['ROOM_MAIL_R15'],
            'R20N'  => $_ENV['ROOM_MAIL_R20N'],
            'Aula'  => $_ENV['ROOM_MAIL_AULA']
        ];
    }

    /**
     * Holt die Buchungen für alle Räume in einem Zeitraum
     */
    public function getAllBookings(string $start, string $end): array {
        $allEvents = [];

        foreach ($this->rooms as $roomName => $mail) {
            $events = $this->getRoomCalendar($mail, $start, $end);
            foreach ($events as $event) {
                $allEvents[] = [
                    'id'        => $event['id'],
                    'room'      => $roomName,
                    'subject'   => $event['subject'],
                    'start'     => $event['start']['dateTime'],
                    'end'       => $event['end']['dateTime'],
                    'organizer' => $event['organizer']['emailAddress']['name'] ?? 'Unbekannt'
                ];
            }
        }

        // Nach Startzeit sortieren
        usort($allEvents, fn($a, $b) => strcmp($a['start'], $b['start']));

        return $allEvents;
    }

    private function getRoomCalendar(string $mail, string $start, string $end): array {
        $endpoint = "/users/$mail/calendar/calendarView";
        
        $params = [
            'query' => [
                'startDateTime' => $start,
                'endDateTime'   => $end,
                '$select'       => 'id,subject,start,end,organizer',
                '$top'          => 100
            ]
        ];

        try {
            // WICHTIG: $data ist bereits ein Array, da GraphClient->request() json_decode macht!
            $data = $this->graphClient->request('GET', $endpoint, $params);
            
            // Prüfen, ob 'value' existiert (Standard bei MS Graph Responses)
            return $data['value'] ?? []; 
        } catch (\Exception $e) {
            error_log("Fehler beim Abrufen der Raumdaten für $mail: " . $e->getMessage());
            return [];
        }
    }
}