<?php

namespace Kai\MhbBackend20\Graph;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class GraphClient {
    private Client $httpClient;
    private ?string $accessToken = null;
    private array $config;

    public function __construct() {
        $configPath = ROOT_PATH . '/config/graph.php'; // Nutze die Konstante aus der index.php
        
        if (!file_exists($configPath)) {
            throw new \Exception("Konfigurationsdatei nicht gefunden: $configPath");
        }
        // Eventuell require nutzen für Config-Arrays!
        $this->config = require_once $configPath; 
        
        $baseUrl = rtrim($this->config['base_url'], '/') . '/';
        $this->httpClient = new Client([
            'base_uri' => $baseUrl,
            'timeout'  => $this->config['request_timeout'] ?? 30.0,
        ]);
    }

    /**
     * Holt ein gültiges Access Token via Client Credentials Flow.
     */
    private function getAccessToken(): string {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $url = "https://login.microsoftonline.com/" . $this->config['tenant_id'] . "/oauth2/v2.0/token";
        
        try {
            $response = (new Client())->post($url, [
                'form_params' => [
                    'client_id'     => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'scope'         => 'https://graph.microsoft.com/.default',
                    'grant_type'    => 'client_credentials',
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $this->accessToken = $data['access_token'];
            return $this->accessToken;
            
        } catch (GuzzleException $e) {
            throw new \Exception("Fehler beim Abrufen des Access Tokens: " . $e->getMessage());
        }
    }

    /**
     * Kern-Methode für API-Anfragen
     */
    public function request(string $method, string $endpoint, array $options = []) {
        $endpoint =ltrim($endpoint,"/");
        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Accept'        => 'application/json',
        ]);

        try {
            $response = $this->httpClient->request($method, $endpoint, $options);
            return json_decode($response->getBody(), true);
        } catch (GuzzleException $e) {
            // Hier könnte man später Logging einbauen
            throw new \Exception("Microsoft Graph API Fehler ($endpoint): " . $e->getMessage());
        }
    }

    /**
     * Spezifische Methode für Drive-Items (für den SyncService)
     */
    public function getDriveChildren(string $driveId, string $itemId): array {
        $endpoint = "drives/$driveId/items/$itemId/children";
        $data = $this->request('GET', $endpoint);
        return $data['value'] ?? [];
    }
}