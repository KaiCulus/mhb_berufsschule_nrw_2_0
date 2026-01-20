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
    // Wichtig: Muss vor session_start() stehen
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', 'true');
    ini_set('session.cookie_httponly', 'true');

    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => 'localhost',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None'
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
    // Nutze die URL aus der .env falls vorhanden, sonst Fallback auf localhost:5173
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
         * Hier kannst du einfach neue Endpunkte hinzufügen.
         */
        $routes = [
            // Säule 1: Auth
            ''               => ['GET' => 'handleRoot'],
            'oauth/login'    => ['GET' => 'handleOAuthLogin'],
            'oauth/callback' => ['GET' => 'handleOAuthCallback'],
            'oauth/logout'   => ['GET' => 'handleOAuthLogout'],

            // Säule 2 & 3: Beispiel für geschützte API (Graph + DB)
            'api/user/profile' => ['GET' => 'handleUserProfile'],

            //Test API TODO: Später entfernen
            'api/test-letter' => ['GET' => 'handleTestLetter'],
        ];

        if (!isset($routes[$path])) {
            handleNotFound();
            return;
        }

        if (!isset($routes[$path][$method])) {
            handleMethodNotAllowed();
            return;
        }

        $handler = $routes[$path][$method];
        
        // Wenn der Handler ein String ist, rufen wir die Funktion auf
        if (is_string($handler)) {
            $handler();
        } 
    } catch (Exception $e) {
        handleError($e);
    }
}

/**
 * BEISPIEL: Ein kombinierter Handler für Auth, Graph und DB
 */
function handleUserProfile(): void 
{
    // 1. Auth-Säule: Token validieren
    $userData = \Kai\MhbBackend20\Auth\Middleware\AuthMiddleware::check();
    
    // 2. Graph-Säule: (Optional) Weitere Daten von MS holen
    // $graph = new \Kai\MhbBackend20\Graph\GraphClient($userData['access_token']);
    
    // 3. DB-Säule: Verbindung nutzen
    // $db = \Kai\MhbBackend20\Database\DB::getConnection();

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'user' => $userData
    ]);
}

/**
 * Handler für die Auth-Säule
 */
function handleOAuthLogin(): void
{
    $controller = new \Kai\MhbBackend20\Auth\Controllers\OAuthController();
    $controller->redirectToOAuth();
}

function handleOAuthCallback(): void
{
    $controller = new \Kai\MhbBackend20\Auth\Controllers\OAuthController();
    $controller->handleCallback();
}

function handleOAuthLogout(): void
{
    $controller = new \Kai\MhbBackend20\Auth\Controllers\OAuthController();
    $controller->logout();
}

//TODO: handleTestLetter Später entfernen
function handleTestLetter(): void
{
    $controller = new \Kai\MhbBackend20\Api\Controllers\TestController();
    $controller->getThirdLetter();
}

/**
 * Hilfsfunktionen
 */
function getRequestPath(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = trim($path, '/');
    
    // index.php aus dem Pfad entfernen, falls sie direkt aufgerufen wird
    if (strpos($path, 'index.php') === 0) {
        $path = substr($path, strlen('index.php'));
        $path = trim($path, '/');
    }

    return $path;
}

function handleRoot(): void { echo json_encode(['message' => 'MHB Backend API']); }
function handleNotFound(): void { http_response_code(404); echo json_encode(['error' => 'Not Found']); }
function handleMethodNotAllowed(): void { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); }
function handleError(Exception $e): void {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
}