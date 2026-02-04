<?php

namespace Kai\MhbBackend20\Common;

abstract class BaseController
{
    /**
     * Sendet eine standardisierte JSON-Antwort
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
     * Liest den JSON-Body einer Anfrage (php://input) aus
     */
    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Sendet eine Fehlermeldung
     */
    protected function errorResponse(string $message, int $statusCode = 400): void
    {
        $this->jsonResponse([
            'status' => 'error',
            'message' => $message
        ], $statusCode);
    }

    /**
     * Erweiterte Validierung von Request-Daten
     */
    protected function validateRequest(array $rules): array
    {
        $data = $this->getJsonInput();
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            // 1. Pflichtprüfung
            if ($value === null || $value === '') {
                $errors[] = "Feld '$field' ist erforderlich.";
                continue;
            }

            // 2. Typprüfung & Format
            switch ($rule) {
                case 'int':
                    if (!is_numeric($value)) $errors[] = "'$field' muss eine Ganzzahl sein.";
                    break;
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) $errors[] = "'$field' ist keine gültige E-Mail.";
                    break;
                case 'string':
                    if (!is_string($value)) $errors[] = "'$field' muss Text sein.";
                    break;
                case 'array':
                    if (!is_array($value)) $errors[] = "'$field' muss ein Array sein.";
                    break;
            }
        }

        if (!empty($errors)) {
            $this->errorResponse("Validierung fehlgeschlagen: " . implode(' ', $errors));
        }

        return $data;
    }

    /**
     * Bereinigt einen String von potenziell gefährlichem HTML (XSS Schutz)
     */
    protected function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }


    /**
     * Holt einen Wert aus $_GET
     */
    protected function getQueryParam(string $key, $default = null) {
        return $_GET[$key] ?? $default;
    }

    /**
     * Holt einen Wert aus dem validierten JSON-Input (bequemer als direktes Array-Handling)
     */
    protected function input(string $key, $default = null) {
        $data = $this->getJsonInput();
        return $data[$key] ?? $default;
    }

    /**
     * Erzwingt eine Gruppe basierend auf dem .env Key.
     * Beispiel: $this->requireGroup('MHB_BE_MSAL_TICKETPROCESSORS');
     * Beispiel: $this->requireGroup('MHB_BE_MSAL_ADMIN_VERWALTUNG');
     */
    protected function requireGroup(string $envKey): void
    {
        \Kai\MhbBackend20\Auth\Middleware\AuthMiddleware::requireGroup($envKey);
    }



    /* --- ZUKÜNFTIGE ERWEITERUNGEN (Auskommentiert) --- */

    /**
     * Extrahiert Query-Parameter für Pagination (z.B. ?page=1&limit=20)
     */
    /*
    protected function getPaginationParams(): array
    {
        return [
            'page'  => (int)($_GET['page'] ?? 1),
            'limit' => (int)($_GET['limit'] ?? 20),
            'offset' => ((int)($_GET['page'] ?? 1) - 1) * (int)($_GET['limit'] ?? 20)
        ];
    }
    */

    /**
     * Setzt Cache-Control Header für statische API-Antworten
     */
    /*
    protected function setCacheHeader(int $seconds = 3600): void
    {
        header("Cache-Control: public, max-age=$seconds");
    }
    */

}