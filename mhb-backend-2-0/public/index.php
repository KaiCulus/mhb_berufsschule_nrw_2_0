<?php

/**
 * index.php
 *
 * Einziger Einstiegspunkt (Front Controller) für das MHB Backend.
 *
 * Ablauf jedes Requests:
 *   1. Umgebungsvariablen laden und validieren (.env)
 *   2. PHP-Session mit sicheren Cookie-Parametern konfigurieren
 *   3. CORS-Header setzen (nur in der Entwicklungsumgebung)
 *   4. Request-Methode und -Pfad auflösen
 *   5. Passende Route suchen und zugehörigen Controller aufrufen
 */

define('ROOT_PATH', dirname(__DIR__));
require_once __DIR__ . '/../vendor/autoload.php';

loadEnvironmentConfiguration();
configureSecureSession();
setupCorsHeaders();
handleRequest();

// =============================================================================
// Routing
// =============================================================================

/**
 * Löst den eingehenden Request auf und ruft den passenden Controller auf.
 *
 * Routing-Reihenfolge:
 *   1. Statische Routen (exakter String-Match) — z.B. 'api/tickets'
 *   2. Dynamische Routen (Regex-Match) — z.B. 'api/tickets/detail/42'
 *   3. Root-Pfad ('') → API-Status-Antwort
 *   4. Kein Match → 404
 *
 * Alle Controller-Fehler werden zentral in handleError() gefangen.
 */
function handleRequest(): void
{
    $method = $_SERVER['REQUEST_METHOD'];
    $path   = getRequestPath();
    $debug  = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

    if ($debug) {
        error_log("DEBUG: {$method} '{$path}'");
    }

    try {
        $routes = require_once ROOT_PATH . '/config/routes.php';

        // 1. Statische Routen — O(1) Lookup via Array-Key
        if (isset($routes['static'][$path][$method])) {
            [$controllerClass, $action] = $routes['static'][$path][$method];

            if ($debug) {
                error_log("DEBUG: Static match → {$controllerClass}::{$action}");
            }

            (new $controllerClass())->$action();
            return;
        }

        // 2. Dynamische Routen — Regex-Match mit URL-Parametern
        foreach ($routes['dynamic'] as $regex => $config) {
            if (!preg_match($regex, $path, $matches) || !isset($config[$method])) {
                continue;
            }

            // matches[0] = vollständiger Pfad, matches[1..n] = Capture Groups
            array_shift($matches);

            [$controllerClass, $action] = $config[$method];

            if ($debug) {
                error_log("DEBUG: Dynamic match {$regex} → {$controllerClass}::{$action} | Params: " . implode(', ', $matches));
            }

            // Splat-Operator übergibt Capture Groups als einzelne Argumente:
            // ['DOC123', '5'] → controller->action('DOC123', '5')
            (new $controllerClass())->$action(...$matches);
            return;
        }

        // 3. Root-Pfad → einfache API-Status-Antwort (kein debug-Flag nach außen)
        if ($path === '' || $path === '/') {
            header('Content-Type: application/json');
            echo json_encode(['message' => 'MHB Backend API Online']);
            return;
        }

        // 4. Keine Route gefunden
        if ($debug) {
            error_log("DEBUG: No route for '{$path}'. Static routes: " . implode(', ', array_keys($routes['static'])));
        }

        handleNotFound();

    } catch (\Throwable $e) {
        handleError($e);
    }
}

// =============================================================================
// Bootstrap-Funktionen
// =============================================================================

/**
 * Lädt die .env-Datei und validiert alle Pflicht-Variablen.
 *
 * Fehlt eine Pflicht-Variable, bricht die Anwendung mit einer klaren Fehlermeldung ab —
 * besser ein früher Fehler beim Start als ein kryptischer Fehler tief im Code.
 */
function loadEnvironmentConfiguration(): void
{
    $dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
    $dotenv->load();

    $dotenv->required([
        'APP_ENV',
        'APP_ENCRYPTION_KEY',
        'MHB_FRONTEND_URL',
        'MHB_BE_MSAL_CLIENT_ID',
        'MHB_BE_MSAL_CLIENT_SECRET_VALUE',
        'MHB_BE_MSAL_REDIRECT_URI',
        'MHB_BE_MSAL_TENANT_ID',
        'MHB_BE_MSAL_ADMIN_VERWALTUNG',
        'TICKETHELPER_ADDRESS',
        'TICKET_MAIL_FACILITY',
        'TICKET_MAIL_NETWORK',
        'TICKET_MAIL_IT_SUPPORT',
    ]);
}

/**
 * Konfiguriert die PHP-Session mit produktionssicheren Cookie-Parametern.
 *
 * Muss vor dem ersten session_start() aufgerufen werden.
 *
 * Cookie-Flags:
 *   - secure:   Nur über HTTPS senden (in Production erzwungen)
 *   - httponly: Nicht per JavaScript lesbar (schützt vor XSS-Token-Diebstahl)
 *   - samesite: Lax — erlaubt OAuth-Redirect-Callbacks, blockiert CSRF
 */
function configureSecureSession(): void
{
    $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
    $domain       = parse_url($_ENV['MHB_FRONTEND_URL'] ?? 'http://localhost', PHP_URL_HOST);

    session_set_cookie_params([
        'lifetime' => 86400,   // 24 Stunden
        'path'     => '/',
        'domain'   => $domain,
        'secure'   => $isProduction,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Setzt CORS-Header für Cross-Origin-Requests vom Vue-Frontend.
 *
 * In Production läuft Frontend und Backend auf derselben Origin (same-server),
 * daher werden CORS-Header dort nicht gesetzt — sie wären wirkungslos und
 * könnten in Kombination mit Proxys zu Problemen führen.
 *
 * In der Entwicklungsumgebung (APP_ENV=development) läuft der Vite-Dev-Server
 * auf einem anderen Port und benötigt CORS für Session-Cookies (withCredentials).
 *
 * OPTIONS-Preflight-Requests werden direkt beantwortet, ohne den Request-Handler
 * zu durchlaufen.
 */
function setupCorsHeaders(): void
{
    // CORS nur in der Entwicklungsumgebung aktiv
    if (($_ENV['APP_ENV'] ?? 'production') !== 'development') {
        return;
    }

    $allowedOrigin = $_ENV['MHB_FRONTEND_URL'] ?? 'http://localhost:5173';

    header("Access-Control-Allow-Origin: {$allowedOrigin}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

    // Preflight-Request direkt beantworten — kein Routing nötig
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// =============================================================================
// Pfad-Auflösung
// =============================================================================

/**
 * Extrahiert den normalisierten Routen-Pfad aus der Request-URI.
 *
 * Entfernt:
 *   - Query-String (?foo=bar)
 *   - Führende und abschließende Slashes
 *   - Den Unterordner-Präfix in Production (/mhb_2_0/mhb_be/)
 *   - Direkte index.php-Aufrufe (/index.php/api/tickets → api/tickets)
 *
 * Beispiele:
 *   /mhb_2_0/mhb_be/api/tickets  → api/tickets
 *   /api/tickets                  → api/tickets   (lokal ohne Unterordner)
 *   /index.php/api/tickets        → api/tickets
 */
function getRequestPath(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
    $path = trim($path, '/');

    // Unterordner-Präfix in Production entfernen
    // Wird über .env gesteuert, damit der Code lokal und auf dem Server identisch ist
    $basePath = trim($_ENV['APP_BASE_PATH'] ?? '', '/');
    if ($basePath !== '' && str_starts_with($path, $basePath)) {
        $path = ltrim(substr($path, strlen($basePath)), '/');
    }

    // Direkter index.php-Aufruf normalisieren
    $path = preg_replace('/^index\.php\/?/', '', $path);

    return $path;
}

// =============================================================================
// Error Handler
// =============================================================================

/**
 * Sendet eine 404-Antwort für nicht gefundene Routen.
 */
function handleNotFound(): void
{
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not Found']);
}

/**
 * Zentraler Error-Handler für ungefangene Exceptions aus Controllern.
 *
 * In Production: Nur generische Fehlermeldung nach außen — Details nur im Log.
 * In Development: Vollständige Fehlermeldung zur schnellen Diagnose.
 */
function handleError(\Throwable $e): void
{
    $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';

    error_log('Unhandled error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    http_response_code(500);
    header('Content-Type: application/json');

    echo json_encode([
        'status'  => 'error',
        'message' => $isProduction ? 'Interner Serverfehler.' : $e->getMessage(),
    ]);
}