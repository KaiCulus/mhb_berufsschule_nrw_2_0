<?php
/**
 * index.php - Einstiegspunkt für das Backend
 * Vollständig überarbeitete Version mit robustem Routing
 */

// 1. Autoloader und Basiskonfiguration
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Session-Konfiguration
configureSecureSession();

// 3. CORS-Header
setupCorsHeaders();

// 4. Umgebungskonfiguration
loadEnvironmentConfiguration();

// 5. Anfrage verarbeiten
handleRequest();

/**
 * Konfiguriert die Session mit sicheren Einstellungen
 */
function configureSecureSession(): void
{
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', 'true');
    ini_set('session.cookie_domain', 'localhost');
    ini_set('session.cookie_httponly', 'true');

    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => 'localhost',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);

    session_start();

    if (!isset($_COOKIE['PHPSESSID'])) {
        setcookie(
            'PHPSESSID',
            session_id(),
            [
                'expires' => time() + 86400,
                'path' => '/',
                'domain' => 'localhost',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None'
            ]
        );
    }

    error_log('Session initialized. ID: ' . session_id());
}

/**
 * Setzt die CORS-Header
 */
function setupCorsHeaders(): void
{
    header('Access-Control-Allow-Origin: http://localhost:5173');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
    ]);
}

/**
 * Verarbeitet die Anfrage
 */
function handleRequest(): void
{
    $method = $_SERVER['REQUEST_METHOD'];
    $path = getRequestPath();

    error_log("Full REQUEST_URI: " . $_SERVER['REQUEST_URI']);
    error_log("Parsed path: " . $path);
    error_log("Request method: " . $method);

    try {
        // Routing-Tabelle
        $routes = [
            '' => ['GET' => 'handleRoot'],
            'oauth/login' => ['GET' => 'handleOAuthLogin'],
            'oauth/callback' => ['GET' => 'handleOAuthCallback']
        ];

        // Prüfe ob Route existiert
        if (!array_key_exists($path, $routes)) {
            error_log("Route not found: " . $path);
            handleNotFound();
            return;
        }

        // Prüfe ob Methode unterstützt wird
        if (!array_key_exists($method, $routes[$path])) {
            error_log("Method not allowed: " . $method . " for path: " . $path);
            handleMethodNotAllowed();
            return;
        }

        // Führe den entsprechenden Handler aus
        $handler = $routes[$path][$method];
        $handler();
    } catch (Exception $e) {
        handleError($e);
    }
}

/**
 * Extrahiere den korrekten Pfad aus der Anfrage
 */
function getRequestPath(): string
{
    // Fall 1: Pfad kommt aus .htaccess Umleitung
    if (isset($_GET['path'])) {
        return trim($_GET['path'], '/');
    }

    // Fall 2: Direktzugriff auf index.php
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = trim($path, '/');

    // Entferne "index.php" falls vorhanden
    if (strpos($path, 'index.php') === 0) {
        $path = substr($path, strlen('index.php'));
        $path = trim($path, '/');
    }

    // Gib leeren String für Root zurück
    return $path === '' ? '' : $path;
}

/**
 * Handhabung der Root-Route
 */
function handleRoot(): void
{
    echo "Backend is running. Try /oauth/login";
}

/**
 * Handhabung des OAuth-Logins
 */
function handleOAuthLogin(): void
{
    try {
        error_log("Handling OAuth login request");
        $controller = new \Kai\MhbBackend20\Auth\Controllers\OAuthController();
        $controller->redirectToOAuth();
    } catch (\Exception $e) {
        error_log("Full error details: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());

        http_response_code(500);
        echo json_encode([
            'error' => 'Authentication error',
            'message' => $e->getMessage(),
            'details' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Handhabung des OAuth-Callbacks
 */
function handleOAuthCallback(): void
{
    try {
        error_log("Handling OAuth callback request");
        $controller = new \Kai\MhbBackend20\Auth\Controllers\OAuthController();
        $controller->handleCallback();
    } catch (\Exception $e) {
        error_log("Full error details: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());

        http_response_code(500);
        echo json_encode([
            'error' => 'Authentication error',
            'message' => $e->getMessage(),
            'details' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Fehlerbehandlung für nicht gefundene Routen
 */
function handleNotFound(): void
{
    http_response_code(404);
    echo json_encode([
        'error' => 'Route not found',
        'available_routes' => [
            '/',
            '/oauth/login',
            '/oauth/callback'
        ]
    ]);
}

/**
 * Fehlerbehandlung für nicht erlaubte Methoden
 */
function handleMethodNotAllowed(): void
{
    http_response_code(405);
    echo json_encode([
        'error' => 'Method not allowed',
        'message' => 'This endpoint only supports GET requests'
    ]);
}

/**
 * Allgemeine Fehlerbehandlung
 */
function handleError(Exception $e): void
{
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
