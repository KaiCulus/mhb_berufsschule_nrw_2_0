<?php
/**
 * index.php - Einstiegspunkt für das Backend
 */

require_once __DIR__ . '/../vendor/autoload.php';

// 1. Basiskonfiguration laden
loadEnvironmentConfiguration();
configureSecureSession();
setupCorsHeaders();

// 2. Anfrage verarbeiten
handleRequest();

/**
 * Konfiguriert die Session mit sicheren Einstellungen
 */
function configureSecureSession(): void
{
    // Hinweis: domain sollte im Prod-Betrieb dynamisch aus .env kommen
    $domain = parse_url($_ENV['MHB_FRONTEND_URL'] ?? 'http://localhost', PHP_URL_HOST);

    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => $domain, 
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None' // Wichtig für Cross-Origin (Lokal HTTPS erforderlich!)
    ]);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Setzt die CORS-Header für Vue-Frontend Kommunikation
 */
function setupCorsHeaders(): void
{
    $allowedOrigin = $_ENV['MHB_FRONTEND_URL'] ?? 'http://localhost:5173';
    
    header("Access-Control-Allow-Origin: $allowedOrigin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Lädt die Umgebungskonfiguration
 */
function loadEnvironmentConfiguration(): void
{
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();

    $dotenv->required([
        'MHB_BE_MSAL_CLIENT_ID',
        'MHB_BE_MSAL_CLIENT_SECRET_VALUE',
        'MHB_BE_MSAL_REDIRECT_URI',
        'MHB_BE_MSAL_TENANT_ID',
        'MHB_BE_MSAL_ADMIN_VERWALTUNG' // Neu für Rollen-Check
    ]);
}

/**
 * Zentrales Routing "Gehirn"
 */
function handleRequest(): void
{
    $method = $_SERVER['REQUEST_METHOD'];
    $path = getRequestPath();

    try {
        /**
         * ROUTING TABELLE
         */
        $routes = [
            'oauth/login'             => ['GET'  => 'handleOAuthLogin'],
            'oauth/callback'          => ['GET'  => 'handleOAuthCallback'],
            'oauth/logout'            => ['GET'  => 'handleOAuthLogout'],
            'api/sync/get-permissions' => ['GET'  => 'handleGetPermissions'],
            'api/user/profile'        => ['GET'  => 'handleUserProfile'],
            'api/test-letter'         => ['GET'  => 'handleTestLetter'],
        ];

        // 1. Statische Routen prüfen
        if (isset($routes[$path][$method])) {
            $handler = $routes[$path][$method];
            $handler();
            return;
        }

        // 2. Dynamische Routen prüfen (z.B. api/sync/execute/verwaltung)
        if (preg_match('#^api/sync/execute/([^/]+)$#', $path, $matches)) {
            if ($method === 'POST') {
                handleSyncExecute($matches[1]);
                return;
            }
        }

        if ($path === '') {
            handleRoot();
            return;
        }

        handleNotFound();
    } catch (Exception $e) {
        handleError($e);
    }
}

/**
 * HANDLER FÜR SYNC & PERMISSIONS
 */

function handleGetPermissions(): void
{
    // Optional: Hier AuthMiddleware::check() vorschalten
    $controller = new \Kai\MhbBackend20\Graph\Controllers\GraphSyncController();
    $controller->getPermissions();
}

function handleSyncExecute(string $type): void
{
    $controller = new \Kai\MhbBackend20\Graph\Controllers\GraphSyncController();
    $controller->executeSync($type);
}

/**
 * BESTEHENDE HANDLER
 */
function handleOAuthLogin(): void {
    (new \Kai\MhbBackend20\Auth\Controllers\OAuthController())->redirectToOAuth();
}

function handleOAuthCallback(): void {
    (new \Kai\MhbBackend20\Auth\Controllers\OAuthController())->handleCallback();
}

function handleOAuthLogout(): void {
    (new \Kai\MhbBackend20\Auth\Controllers\OAuthController())->logout();
}

function handleUserProfile(): void {
    $userData = \Kai\MhbBackend20\Auth\Middleware\AuthMiddleware::check();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'user' => $userData]);
}

function handleTestLetter(): void {
    (new \Kai\MhbBackend20\Api\Controllers\TestController())->getThirdLetter();
}

/**
 * Hilfsfunktionen
 */
function getRequestPath(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = trim($path, '/');
    
    if (strpos($path, 'index.php') === 0) {
        $path = substr($path, strlen('index.php'));
        $path = trim($path, '/');
    }

    return $path;
}

function handleRoot(): void { echo json_encode(['message' => 'MHB Backend API']); }
function handleNotFound(): void { http_response_code(404); echo json_encode(['error' => 'Not Found', 'path' => getRequestPath()]); }
function handleMethodNotAllowed(): void { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); }
function handleError(Exception $e): void {
    error_log($e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
}