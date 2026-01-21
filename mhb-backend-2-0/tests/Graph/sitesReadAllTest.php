<?php
require 'vendor/autoload.php';
require __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();
use GuzzleHttp\Client;

$tenantId = $_ENV['MHB_BE_MSAL_TENANT_ID'];
$clientId = $_ENV['MHB_BE_MSAL_CLIENT_ID'];
$clientSecret = $_ENV['MHB_BE_MSAL_CLIENT_SECRET_VALUE'];
$driveId = $_ENV['MHB_MS_GRAPH_DRIVE_ID_VERWALTUNG'];
$folderId = $_ENV['MHB_MS_GRAPH_FOLDER_ID']; // Die ID des MHB_2.0 Ordners

$client = new Client();

// 1. Access Token holen (Client Credentials Flow)
$response = $client->post("https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token", [
    'form_params' => [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'scope' => 'https://graph.microsoft.com/.default',
        'grant_type' => 'client_credentials',
    ]
]);

$token = json_decode($response->getBody())->access_token;

// 2. Ordner-Inhalt abrufen
try {
    $apiUrl = "https://graph.microsoft.com/v1.0/drives/$driveId/items/$folderId/children";
    
    $graphResponse = $client->get($apiUrl, [
        'headers' => [
            'Authorization' => "Bearer $token",
            'Accept' => 'application/json',
        ]
    ]);

    $files = json_decode($graphResponse->getBody())->value;

    echo "Erfolg! Gefundene Dateien:\n";
    foreach ($files as $file) {
        echo "- " . $file->name . " (ID: " . $file->id . ")\n";
    }

} catch (\Exception $e) {
    echo "Fehler beim Abruf: " . $e->getMessage();
}