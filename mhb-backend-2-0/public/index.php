<?php
require __DIR__ . '/../vendor/autoload.php';
session_start();

// Lade .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$dotenv->required([
    'MHB_BE_MSAL_CLIENT_ID',
    'MHB_BE_MSAL_CLIENT_SECRET_VALUE',
    'MHB_BE_MSAL_REDIRECT_URI',
    'MHB_BE_MSAL_TENANT_ID',
]);

// Debug: Logge die aufgerufene Route TODO:Entfernen
error_log("Aufgerufene Route: " . $_SERVER['REQUEST_URI']);
error_log("Methode: " . $_SERVER['REQUEST_METHOD']);

// Simple Router (später durch z. B. FastRoute ersetzen)
$route = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($route === '/oauth/login' && $method === 'GET') {
        $controller = new \Kai\MhbBackend20\Auth\Controllers\OAuthController();
        $controller->redirectToOAuth();
    } elseif ($route === '/oauth/callback' && $method === 'GET') {
        $controller = new \Kai\MhbBackend20\Auth\Controllers\OAuthController();
        $result = $controller->handleCallback();
        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Interner Serverfehler: ' . $e->getMessage()]);
} catch (\Kai\MhbBackend20\Auth\Exceptions\OAuthException $e) {
    http_response_code($e->getCode());
    echo json_encode(['error' => $e->getMessage()]);
} catch (\Dotenv\Exception\ValidationException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Serverfehler: Konfiguration unvollständig']);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Interner Serverfehler']);
}
