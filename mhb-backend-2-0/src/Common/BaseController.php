<?php

namespace Kai\MhbBackend20\Common;

/**
 * BaseController
 *
 * Abstrakte Basisklasse für alle Controller der Anwendung.
 *
 * Stellt zentral bereit:
 *   - JSON-Antworten (jsonResponse, errorResponse)
 *   - Request-Parsing (getJsonInput, getQueryParam, input)
 *   - Eingabe-Validierung (validateRequest)
 *   - XSS-Schutz (sanitize)
 *   - Berechtigungsprüfung (requireGroup)
 *
 * Alle Controller erben von dieser Klasse und müssen keine eigene
 * HTTP-Handling-Logik implementieren.
 */
abstract class BaseController
{
    // =========================================================================
    // HTTP Response
    // =========================================================================

    /**
     * Sendet eine standardisierte JSON-Antwort und beendet den Request.
     *
     * @param array $data       Daten die als JSON ausgegeben werden
     * @param int   $statusCode HTTP-Statuscode (Standard: 200)
     */
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code($statusCode);
        }

        echo json_encode($data);
        exit;
    }

    /**
     * Sendet eine standardisierte JSON-Fehlerantwort und beendet den Request.
     *
     * @param string $message    Fehlermeldung für den Client
     * @param int    $statusCode HTTP-Statuscode (Standard: 400)
     */
    protected function errorResponse(string $message, int $statusCode = 400): void
    {
        $this->jsonResponse([
            'status'  => 'error',
            'message' => $message,
        ], $statusCode);
    }

    // =========================================================================
    // Request Parsing
    // =========================================================================

    /**
     * Liest den JSON-Body des Requests aus (php://input).
     *
     * Gibt ein leeres Array zurück wenn kein valides JSON gesendet wurde,
     * anstatt einen Fehler zu werfen — Validierung übernimmt validateRequest().
     *
     * @return array Geparster Request-Body oder []
     */
    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data  = json_decode($input, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Liest einen einzelnen GET-Parameter aus $_GET.
     *
     * @param string $key     Name des Parameters
     * @param mixed  $default Rückgabewert wenn Parameter fehlt (Standard: null)
     * @return mixed
     */
    protected function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Liest einen einzelnen Wert aus dem JSON-Request-Body.
     *
     * Bequeme Kurzform für einzelne Felder ohne vollständige Validierung.
     *
     * @param string $key     Feldname im JSON-Body
     * @param mixed  $default Rückgabewert wenn Feld fehlt (Standard: null)
     * @return mixed
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        return $this->getJsonInput()[$key] ?? $default;
    }

    // =========================================================================
    // Validierung & Sanitizing
    // =========================================================================

    /**
     * Validiert den JSON-Request-Body anhand eines Regelwerks.
     *
     * Unterstützte Regeln: 'string', 'int', 'email', 'array'
     *
     * Beispiel:
     *   $data = $this->validateRequest([
     *       'title'    => 'string',
     *       'quantity' => 'int',
     *       'email'    => 'email',
     *   ]);
     *
     * Schlägt die Validierung fehl, wird direkt eine 400-Fehlerantwort gesendet.
     *
     * @param array $rules Feldname → Regelname
     * @return array Vollständiger Request-Body (auch nicht-validierte Felder)
     */
    protected function validateRequest(array $rules): array
    {
        $data   = $this->getJsonInput();
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            // Pflichtprüfung: null und leerer String sind nicht erlaubt
            if ($value === null || $value === '') {
                $errors[] = "Feld '{$field}' ist erforderlich.";
                continue;
            }

            // Typ- und Formatprüfung
            switch ($rule) {
                case 'int':
                    // filter_var statt is_numeric — verhindert dass '3.5' als int gilt
                    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                        $errors[] = "'{$field}' muss eine Ganzzahl sein.";
                    }
                    break;

                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "'{$field}' ist keine gültige E-Mail-Adresse.";
                    }
                    break;

                case 'string':
                    if (!is_string($value)) {
                        $errors[] = "'{$field}' muss ein Text sein.";
                    }
                    break;

                case 'array':
                    if (!is_array($value)) {
                        $errors[] = "'{$field}' muss ein Array sein.";
                    }
                    break;
            }
        }

        if (!empty($errors)) {
            $this->errorResponse('Validierung fehlgeschlagen: ' . implode(' ', $errors));
        }

        return $data;
    }

    /**
     * Bereinigt einen String gegen XSS-Angriffe.
     *
     * Wandelt HTML-Sonderzeichen in Entities um (z.B. < → &lt;).
     * Sollte auf alle Nutzereingaben angewendet werden, die in HTML ausgegeben werden.
     *
     * Hinweis: Für JSON-Antworten ist dies nicht nötig, da json_encode()
     * HTML-Zeichen automatisch escaped.
     *
     * @param string $value Roher Eingabestring
     * @return string Bereinigter String
     */
    protected function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    // =========================================================================
    // Autorisierung
    // =========================================================================

    /**
     * Erzwingt Gruppen-Mitgliedschaft für den aktuellen Request.
     *
     * Bricht den Request mit 403 ab wenn der User nicht in der Gruppe ist.
     * Delegiert an AuthMiddleware::requireGroup().
     *
     * Verwendung:
     *   $this->requireGroup('MHB_BE_MSAL_ADMIN_VERWALTUNG');
     *   $this->requireGroup('MHB_BE_MSAL_TICKETPROCESSORS');
     *
     * @param string $envKey .env-Schlüssel der Gruppen-ID
     */
    protected function requireGroup(string $envKey): void
    {
        \Kai\MhbBackend20\Auth\Middleware\AuthMiddleware::requireGroup($envKey);
    }

    // =========================================================================
    // Zukünftige Erweiterungen (vorbereitet, noch nicht aktiv)
    // =========================================================================

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    | Extrahiert page/limit Parameter aus der URL für paginierte Endpunkte.
    | Verwendung: [$page, $limit, $offset] = array_values($this->getPaginationParams());
    |
    | protected function getPaginationParams(): array
    | {
    |     $page   = max(1, (int)($_GET['page']  ?? 1));
    |     $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20))); // max. 100 pro Seite
    |     return [
    |         'page'   => $page,
    |         'limit'  => $limit,
    |         'offset' => ($page - 1) * $limit,
    |     ];
    | }
    */

    /*
    |--------------------------------------------------------------------------
    | Cache Headers
    |--------------------------------------------------------------------------
    | Setzt Cache-Control Header für Endpunkte mit selten ändernden Daten.
    | Verwendung: $this->setCacheHeader(3600); // 1 Stunde cachen
    |
    | protected function setCacheHeader(int $seconds = 3600): void
    | {
    |     header("Cache-Control: public, max-age={$seconds}");
    | }
    */
}