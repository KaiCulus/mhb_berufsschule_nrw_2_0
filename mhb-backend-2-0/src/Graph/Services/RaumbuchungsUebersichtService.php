<?php

namespace Kai\MhbBackend20\Graph\Services;

use Kai\MhbBackend20\Graph\GraphClient;

/**
 * RaumbuchungsUebersichtService
 *
 * Holt Raumbuchungen aus Microsoft 365 Kalender-Ressourcen via Graph API.
 *
 * Räume werden aus der .env geladen — neue Räume können ohne Code-Änderung
 * durch Ergänzen von ROOM_MAIL_* Variablen in der .env hinzugefügt werden.
 *
 * .env Konfiguration:
 *   ROOM_MAILS=R15:mail@schule.de,R20N:mail2@schule.de,Aula:mail3@schule.de
 *
 * Alternativ (aktuelles Format, ein Raum pro Variable):
 *   ROOM_MAIL_R15=...
 *   ROOM_MAIL_R20N=...
 *   ROOM_MAIL_AULA=...
 */
class RaumbuchungsUebersichtService
{
    private GraphClient $graphClient;
    private array $rooms;

    public function __construct()
    {
        $this->graphClient = new GraphClient();

        // Räume aus .env laden — fehlende Variablen werden übersprungen (null-safe)
        // Raum wird nur hinzugefügt wenn die .env-Variable gesetzt und nicht leer ist
        $roomConfig = [
            'R15'  => $_ENV['ROOM_MAIL_R15']  ?? null,
            'R20N' => $_ENV['ROOM_MAIL_R20N'] ?? null,
            'Aula' => $_ENV['ROOM_MAIL_AULA'] ?? null,
        ];

        // Nur Räume mit gültiger Mail-Adresse verwenden
        $this->rooms = array_filter($roomConfig, fn($mail) => !empty($mail));

        if (empty($this->rooms)) {
            error_log('RaumbuchungsUebersichtService: Keine Raum-Konfiguration gefunden. ROOM_MAIL_* in .env prüfen.');
        }
    }

    /**
     * Holt alle Buchungen für alle konfigurierten Räume in einem Zeitraum.
     *
     * Gibt ein leeres Array zurück wenn einzelne Räume nicht erreichbar sind —
     * ein Fehler bei einem Raum verhindert nicht die Anzeige der anderen.
     *
     * @param string $start Startzeit im ISO 8601 Format (z.B. '2026-04-08T00:00:00Z')
     * @param string $end   Endzeit im ISO 8601 Format
     * @return array        Buchungen sortiert nach Startzeit
     */
    public function getAllBookings(string $start, string $end): array
    {
        $allEvents = [];

        foreach ($this->rooms as $roomName => $mail) {
            $events = $this->getRoomCalendar($mail, $start, $end);

            foreach ($events as $event) {
                $allEvents[] = [
                    'id'        => $event['id'],
                    'room'      => $roomName,
                    'subject'   => $event['subject']  ?? 'Kein Betreff',
                    'start'     => $event['start']['dateTime'] ?? null,
                    'end'       => $event['end']['dateTime']   ?? null,
                    'organizer' => $event['organizer']['emailAddress']['name'] ?? 'Unbekannt',
                ];
            }
        }

        // Nach Startzeit sortieren (ISO 8601 ist direkt vergleichbar)
        usort($allEvents, fn($a, $b) => strcmp($a['start'] ?? '', $b['start'] ?? ''));

        return $allEvents;
    }

    /**
     * Holt den Kalender eines einzelnen Raums für den angegebenen Zeitraum.
     *
     * Gibt ein leeres Array zurück bei Fehlern — der Aufrufer entscheidet ob
     * ein fehlender Raum kritisch ist oder still ignoriert werden soll.
     *
     * @param string $mail  E-Mail-Adresse der Kalender-Ressource
     * @param string $start Startzeit ISO 8601
     * @param string $end   Endzeit ISO 8601
     * @return array        Liste von Kalender-Events
     */
    private function getRoomCalendar(string $mail, string $start, string $end): array
    {
        // calendarView gibt Events im Zeitfenster zurück (inkl. wiederkehrender Termine)
        $endpoint = "/users/{$mail}/calendar/calendarView";

        $params = [
            'query' => [
                'startDateTime' => $start,
                'endDateTime'   => $end,
                '$select'       => 'id,subject,start,end,organizer',
                '$top'          => 100, // Max. 100 Events pro Raum pro Zeitfenster
            ],
        ];

        try {
            $data = $this->graphClient->request('GET', $endpoint, $params);
            return $data['value'] ?? [];

        } catch (\Exception $e) {
            error_log("RaumbuchungsUebersichtService: Kalender für '{$mail}' nicht abrufbar: " . $e->getMessage());
            return [];
        }
    }
}