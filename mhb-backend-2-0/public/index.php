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
        'MHB_BE_MSAL_ADMIN_VERWALTUNG',
        'TICKETHELPER_ADDRESS',
        'TICKET_MAIL_FACILITY',
        'TICKET_MAIL_NETWORK',
        'TICKET_MAIL_IT_SUPPORT'
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
         * Statische ROUTING TABELLE
         */
        $routes = [
            'oauth/login'             => ['GET'  => 'handleOAuthLogin'],
            'oauth/callback'          => ['GET'  => 'handleOAuthCallback'],
            'oauth/logout'            => ['GET'  => 'handleOAuthLogout'],
            'api/sync/get-permissions' => ['GET'  => 'handleGetPermissions'],
            'api/user/profile'        => ['GET'  => 'handleUserProfile'],
            'api/test-letter'         => ['GET'  => 'handleTestLetter'],
            'api/favorites'            => [
                'POST'   => 'handleAddFavorite',
                'DELETE' => 'handleRemoveFavorite'
            ],
            'api/aliases' => ['POST' => 'handleAddAlias'],
            'api/aliases/vote' => ['POST' => 'handleToggleAliasVote'],
            'api/tickets' => [
                'GET'  => 'handleGetAllTickets',
                'POST' => 'handleCreateTicket'
            ],
            'api/tickets/subscribe' => ['POST' => 'handleToggleTicketSubscription'],
            'api/tickets/comment' => ['POST' => 'handleAddTicketComment'],
            'api/tickets/update-field' => ['POST' => 'handleUpdateTicketField'],
            'api/tickets/resolve'      => ['POST' => 'handleResolveTicket'],
            'api/tickets/cleanup'      => ['POST' => 'handleCleanupOldTickets'],
        ];

        // Statische Routen prüfen
        if (isset($routes[$path][$method])) {
            $handler = $routes[$path][$method];
            $handler();
            return;
        }
        /**
         * Dynamische Routen
         */
        // Dokumente Synchronisieren
        if (preg_match('#^api/sync/execute/([^/]+)$#', $path, $matches)) {
            if ($method === 'POST') {
                handleSyncExecute($matches[1]);
                return;
            }
        }

        // Dokumente nach Scope abrufen
        if (preg_match('#^api/documents/([^/]+)$#', $path, $matches)) {
            if ($method === 'GET') {
                handleGetDocumentsByScope($matches[1]);
                return;
            }
        }

        // Favoriten nach UserID abrufen.
        if (preg_match('#^api/favorites/(\d+)$#', $path, $matches)) {
            if ($method === 'GET') {
                handleGetFavorites($matches[1]);
                return;
            }
        }

        //Aliasse abrufen
        if (preg_match('#^api/aliases/([^/]+)/(\d+)$#', $path, $matches)) {
            if ($method === 'GET') {
                handleGetAliases($matches[1], (int)$matches[2]);
                return;
            }
        }

        // Dashboard: Tickets eines Users abrufen
        if (preg_match('#^api/tickets/user/(\d+)$#', $path, $matches)) {
            if ($method === 'GET') {
                handleGetTicketsByUser((int)$matches[1]);
                return;
            }
        }

        // Detailansicht eines Tickets
        if (preg_match('#^api/tickets/detail/(\d+)$#', $path, $matches)) {
            if ($method === 'GET') {
                handleGetTicketDetail((int)$matches[1]);
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

function handleGetDocumentsByScope(string $scope): void
{
    $controller = new \Kai\MhbBackend20\Database\Controllers\DocumentController();
    $controller->getByScope($scope);
}

function handleTestLetter(): void {
    (new \Kai\MhbBackend20\Api\Controllers\TestController())->getThirdLetter();
}

/**
 * HANDLER FÜR FAVORITEN
 */
function handleGetFavorites(string $userId): void
{
    $controller = new \Kai\MhbBackend20\Database\Controllers\FavoriteController();
    $controller->getFavorites((int)$userId);
}

function handleAddFavorite(): void
{
    header('Content-Type: application/json');
    $controller = new \Kai\MhbBackend20\Database\Controllers\FavoriteController();
    $controller->addFavorite();
}

function handleRemoveFavorite(): void
{
    header('Content-Type: application/json');
    $controller = new \Kai\MhbBackend20\Database\Controllers\FavoriteController();
    $controller->removeFavorite();
}

/**
 * HANDLER FÜR ALIAS-VOTING
 */

function handleGetAliases(string $docId, int $userId): void
{
    header('Content-Type: application/json');
    $controller = new \Kai\MhbBackend20\Database\Controllers\AliasController();
    $results = $controller->getAliases($docId, $userId);
    echo json_encode($results);
}

function handleAddAlias(): void
{
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['docId'], $data['aliasText'], $data['userId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing data']);
        return;
    }

    $controller = new \Kai\MhbBackend20\Database\Controllers\AliasController();
    $newId = $controller->addAlias($data['docId'], $data['aliasText'], (int)$data['userId']);
    echo json_encode(['status' => 'success', 'id' => $newId]);
}

function handleToggleAliasVote(): void
{
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['aliasId'], $data['userId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing data']);
        return;
    }

    $controller = new \Kai\MhbBackend20\Database\Controllers\AliasController();
    $result = $controller->toggleVote((int)$data['aliasId'], (int)$data['userId']);
    echo json_encode($result);
}

/**
 * HANDLER FÜR TICKETS
 */


function handleCreateTicket(): void {
    header('Content-Type: application/json');
    (new \Kai\MhbBackend20\Database\Controllers\TicketController())->createTicket();
}

function handleGetAllTickets(): void {
    header('Content-Type: application/json');
    (new \Kai\MhbBackend20\Database\Controllers\TicketController())->getAll();
}

function handleGetTicketsByUser(int $userId): void {
    header('Content-Type: application/json');
    (new \Kai\MhbBackend20\Database\Controllers\TicketController())->getByUser($userId);
}

function handleGetTicketDetail(int $ticketId): void {
    header('Content-Type: application/json');
    (new \Kai\MhbBackend20\Database\Controllers\TicketController())->getDetail($ticketId);
}

function handleToggleTicketSubscription(): void {
    header('Content-Type: application/json');
    (new \Kai\MhbBackend20\Database\Controllers\TicketController())->toggleSubscription();
}

function handleAddTicketComment(): void {
    header('Content-Type: application/json');
    (new \Kai\MhbBackend20\Database\Controllers\TicketController())->addComment();
}

function handleUpdateTicketField(): void {
    header('Content-Type: application/json');
    (new \Kai\MhbBackend20\Database\Controllers\TicketController())->updateField();
}

function handleResolveTicket(): void {
    header('Content-Type: application/json');
    (new \Kai\MhbBackend20\Database\Controllers\TicketController())->resolveTicket();
}

function handleCleanupOldTickets(): void {
    header('Content-Type: application/json');
    (new \Kai\MhbBackend20\Database\Controllers\TicketController())->cleanupOldTickets();
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
