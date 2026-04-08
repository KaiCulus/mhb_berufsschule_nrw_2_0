<?php

namespace Kai\MhbBackend20\Graph;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * GraphClient
 *
 * Zentraler HTTP-Client für die Microsoft Graph API.
 *
 * Authentifizierung:
 *   Verwendet den OAuth 2.0 Client Credentials Flow — d.h. die App authentifiziert
 *   sich mit eigenen Credentials (client_id + client_secret), nicht im Namen eines Users.
 *   Das ist der korrekte Flow für Server-zu-Server-Kommunikation (z.B. Mails senden,
 *   SharePoint-Dateien lesen).
 *
 * Token-Caching:
 *   Access Tokens werden innerhalb einer Request-Lebenszeit zwischengespeichert
 *   und bei Ablauf automatisch erneuert. Microsoft-Tokens laufen nach 3600 Sekunden ab.
 */
class GraphClient
{
    private Client $httpClient;
    private ?string $accessToken = null;
    private int $tokenExpiresAt  = 0; // Unix-Timestamp des Token-Ablaufs
    private array $config;

    public function __construct()
    {
        $configPath = ROOT_PATH . '/config/graph.php';

        if (!file_exists($configPath)) {
            throw new \RuntimeException("Graph-Konfigurationsdatei nicht gefunden: {$configPath}");
        }

        $this->config = require $configPath;

        $baseUrl          = rtrim($this->config['base_url'], '/') . '/';
        $this->httpClient = new Client([
            'base_uri' => $baseUrl,
            'timeout'  => $this->config['request_timeout'] ?? 30.0,
        ]);
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Führt eine authentifizierte Graph API Anfrage durch.
     *
     * Der Access Token wird automatisch geholt und bei Ablauf erneuert.
     * Guzzle-Exceptions werden gefangen und als RuntimeException weitergegeben —
     * interne API-Details (URLs, Credentials) werden nicht nach außen geleitet.
     *
     * @param string $method   HTTP-Methode (GET, POST, PATCH, DELETE)
     * @param string $endpoint Graph-Endpunkt, z.B. '/users/me/sendMail'
     * @param array  $options  Guzzle-Request-Optionen (z.B. ['json' => [...]])
     * @return array           Geparste JSON-Antwort
     * @throws \RuntimeException Bei Netzwerkfehlern oder HTTP-Fehlerantworten
     */
    public function request(string $method, string $endpoint, array $options = []): array
    {
        $endpoint = ltrim($endpoint, '/');

        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Accept'        => 'application/json',
        ]);

        try {
            $response = $this->httpClient->request($method, $endpoint, $options);
            return json_decode($response->getBody(), true) ?? [];
        } catch (GuzzleException $e) {
            // Internes Detail loggen, nach außen generische Meldung
            error_log("GraphClient: {$method} /{$endpoint} fehlgeschlagen: " . $e->getMessage());
            throw new \RuntimeException("Microsoft Graph API Anfrage fehlgeschlagen.");
        }
    }

    /**
     * Liest alle Kind-Elemente (Dateien und Ordner) eines SharePoint Drive-Items.
     *
     * @param string $driveId Drive-ID aus der SharePoint/OneDrive Konfiguration
     * @param string $itemId  Item-ID des Elternordners
     * @return array          Liste von Drive-Items
     */
    public function getDriveChildren(string $driveId, string $itemId): array
    {
        $data = $this->request('GET', "drives/{$driveId}/items/{$itemId}/children");
        return $data['value'] ?? [];
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Gibt ein gültiges Access Token zurück.
     *
     * Gibt das gecachte Token zurück wenn es noch mindestens 60 Sekunden gültig ist.
     * Holt ansonsten ein neues Token via Client Credentials Flow.
     *
     * Puffer von 60 Sekunden verhindert Race Conditions bei knappem Ablauf.
     *
     * @return string Gültiger Bearer Token
     * @throws \RuntimeException Wenn kein Token geholt werden konnte
     */
    private function getAccessToken(): string
    {
        // Token noch gültig (mit 60s Puffer) → direkt zurückgeben
        if ($this->accessToken !== null && time() < $this->tokenExpiresAt - 60) {
            return $this->accessToken;
        }

        $url = "https://login.microsoftonline.com/{$this->config['tenant_id']}/oauth2/v2.0/token";

        try {
            $response = (new Client())->post($url, [
                'form_params' => [
                    'client_id'     => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'scope'         => 'https://graph.microsoft.com/.default',
                    'grant_type'    => 'client_credentials',
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if (empty($data['access_token'])) {
                throw new \RuntimeException('Microsoft hat kein Access Token zurückgegeben.');
            }

            $this->accessToken   = $data['access_token'];
            // expires_in = Sekunden bis Ablauf (Standard: 3600) — Ablaufzeit berechnen
            $this->tokenExpiresAt = time() + (int) ($data['expires_in'] ?? 3600);

            return $this->accessToken;

        } catch (GuzzleException $e) {
            error_log("GraphClient: Token-Abruf fehlgeschlagen: " . $e->getMessage());
            throw new \RuntimeException("Microsoft Access Token konnte nicht abgerufen werden.");
        }
    }
}