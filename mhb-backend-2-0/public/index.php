<?php
/**
 * index.php - Einstiegspunkt für das Backend
 */
define('ROOT_PATH', dirname(__DIR__));
require_once __DIR__ . '/../vendor/autoload.php';

// 1. Basiskonfiguration laden
loadEnvironmentConfiguration();
configureSecureSession();
setupCorsHeaders();

// 2. Anfrage verarbeiten
handleRequest();

/**
 * Zentrales Routing "Gehirn" mit Debug-Funktion
 */
function handleRequest(): void
{
    $method = $_SERVER['REQUEST_METHOD'];
    $path = getRequestPath();
    
    // Debug-Modus über .env steuern
    $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

    if ($debug) {
        error_log("DEBUG: Processing Request - Method: $method, Path: '$path'");
    }

    try {
        $routes = require_once ROOT_PATH . '/config/routes.php';

        // 1. Statische Routen prüfen
        if (isset($routes['static'][$path][$method])) {
            [$controllerClass, $action] = $routes['static'][$path][$method];
            if ($debug){
                error_log("DEBUG: Static Route matched: $controllerClass -> $action");
            } 
            
            $controller = new $controllerClass();
            $controller->$action();
            return;
        }

        // 2. Dynamische Routen prüfen
        foreach ($routes['dynamic'] as $regex => $config) {
            if (preg_match($regex, $path, $matches) && isset($config[$method])) {
                // matches[0] ist der komplette Pfad, matches[1..n] sind die Parameter
                // Wir entfernen matches[0], damit nur die reinen Parameter übrig bleiben
                array_shift($matches); 

                [$controllerClass, $action] = $config[$method];
                
                if ($debug) {
                    error_log("DEBUG: Dynamic Route matched: $regex | Params: " . implode(', ', $matches));
                }

                $controller = new $controllerClass();
                
                // Der Splat-Operator (...) übergibt jedes Element des Arrays als einzelnes Argument
                // Aus ['DOC123', '5'] wird -> getAliases('DOC123', '5')
                $controller->$action(...$matches);
                return;
            }
        }

        // Wenn wir hier landen, wurde keine Route gefunden
        if ($debug) {
            error_log("DEBUG: No route found for '$path'. Available static routes: " . implode(', ', array_keys($routes['static'])));
        }

        if ($path === '' || $path === '/') {
            echo json_encode(['message' => 'MHB Backend API Online', 'debug' => $debug]);
            return;
        }

        handleNotFound();
    } catch (\Throwable $e) {
        handleError($e);
    }
}

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
    $dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
    $dotenv->load();

    $dotenv->required([
        'MHB_BE_MSAL_CLIENT_ID',
        'MHB_BE_MSAL_CLIENT_SECRET_VALUE',
        'MHB_BE_MSAL_REDIRECT_URI',
        'MHB_BE_MSAL_TENANT_ID',
        'MHB_BE_MSAL_ADMIN_VERWALTUNG',
        'TICKETHELPER_ADDRESS',
        'TICKET_MAIL_FACILITY',
        'TICKET_MAIL_NETWORK',
        'TICKET_MAIL_IT_SUPPORT'
    ]);
}

function getRequestPath(): string {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = trim($path, '/');
    // Entferne Unterordner, falls das Backend nicht im Root läuft
    // Beispiel: /backend/api/tickets -> api/tickets
    return preg_replace('/^index\.php\/?/', '', $path);
}

function handleNotFound(): void {
    http_response_code(404);
    echo json_encode(['error' => 'Not Found', 'path' => getRequestPath()]);
}

function handleError(\Throwable $e): void {
    error_log($e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
function handleRoot(): void { echo json_encode(['message' => 'MHB Backend API']); }
function handleMethodNotAllowed(): void { http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); }
