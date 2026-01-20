<?php
// src/Graph/GraphClient.php
namespace Kai\MhbBackend20\Graph;

class GraphClient {
    private string $token;

    public function __construct(string $accessToken) {
        $this->token = $accessToken;
    }

    public function getMyProfile() {
        return $this->request('GET', '/me');
    }

    private function request($method, $endpoint) {
        $ch = curl_init("https://graph.microsoft.com/v1.0" . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $this->token]);
        $response = curl_exec($ch);
        return json_decode($response, true);
    }
}