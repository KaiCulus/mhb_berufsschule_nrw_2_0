<?php

namespace Kai\MhbBackend20\Auth\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Auth\Services\OAuthService;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;
use Kai\MhbBackend20\Common\Cipher;
use Kai\MhbBackend20\Database\DB;
use League\OAuth2\Client\Provider\GenericProvider;

/**
 * OAuthController
 *
 * Steuert den gesamten Microsoft OAuth 2.0 / MSAL Authentifizierungsfluss:
 *   - Login:    Weiterleitung zur Microsoft-Loginseite
 *   - Callback: Token-Austausch, User-Sync, Session-Aufbau
 *   - Logout:   Lokale Session + Microsoft-Session beenden
 *   - Me:       Aktuellen User + Berechtigungen zurückgeben
 *
 * Abhängigkeiten werden per Constructor Injection übergeben,
 * damit Unit-Tests Mocks einschleusen können.
 */
class OAuthController extends BaseController
{
    private GenericProvider $provider;
    private OAuthService $oauthService;
    private string $encKey;

    public function __construct(
        ?GenericProvider $provider = null,
        ?OAuthService $oauthService = null
    ) {
        $this->encKey = $_ENV['APP_ENCRYPTION_KEY'];

        // Standardmäßig echten MSAL-Provider verwenden; im Test überschreibbar
        $this->provider = $provider ?: new GenericProvider([
            'clientId'                => $_ENV['MHB_BE_MSAL_CLIENT_ID'],
            'clientSecret'            => $_ENV['MHB_BE_MSAL_CLIENT_SECRET_VALUE'],
            'redirectUri'             => $_ENV['MHB_BE_MSAL_REDIRECT_URI'],
            'urlAuthorize'            => "https://login.microsoftonline.com/{$_ENV['MHB_BE_MSAL_TENANT_ID']}/oauth2/v2.0/authorize",
            'urlAccessToken'          => "https://login.microsoftonline.com/{$_ENV['MHB_BE_MSAL_TENANT_ID']}/oauth2/v2.0/token",
            'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
        ]);

        $this->oauthService = $oauthService ?: new OAuthService();
    }

    // =========================================================================
    // Public Endpoints
    // =========================================================================

    /**
     * GET oauth/login
     *
     * Generiert die Microsoft-Autorisierungs-URL und leitet den Browser dorthin.
     * Speichert den CSRF-State in der Session, damit er im Callback geprüft werden kann.
     */
    public function redirectToOAuth(): void
    {
        $this->ensureSession();

        $authUrl = $this->provider->getAuthorizationUrl([
            'scope' => implode(' ', ['openid', 'profile', 'email', 'User.Read']),
        ]);

        // State-Token gegen CSRF-Angriffe auf den OAuth-Callback speichern
        $_SESSION['oauth2state'] = $this->provider->getState();

        header('Location: ' . $authUrl);
        exit;
    }

    /**
     * GET oauth/callback
     *
     * Verarbeitet die Rückleitung von Microsoft nach erfolgreichem Login:
     *   1. CSRF-State validieren
     *   2. Authorization Code gegen Access + ID Token tauschen
     *   3. ID-Token prüfen und User-Daten extrahieren
     *   4. User in der Datenbank anlegen oder aktualisieren
     *   5. Session befüllen und zum Dashboard weiterleiten
     *
     * Fehler werden serverseitig geloggt; der Browser bekommt nur eine
     * opaque Referenz-ID, niemals interne Details.
     */
    public function handleCallback(): void
    {
        $this->ensureSession();

        try {
            // 1. CSRF-State prüfen — schützt vor Request Forgery auf den Callback
            $state = $this->getQueryParam('state');
            if (empty($state) || empty($_SESSION['oauth2state']) || $state !== $_SESSION['oauth2state']) {
                unset($_SESSION['oauth2state']);
                throw new OAuthException('Invalid state — possible CSRF attempt.', 403);
            }
            unset($_SESSION['oauth2state']); // State wird nur einmal benötigt

            // 2. Authorization Code prüfen
            $code = $this->getQueryParam('code');
            if (empty($code)) {
                throw new OAuthException('Authorization code missing.', 400);
            }

            // 3. Token Exchange: Code → Access Token + ID Token
            $token    = $this->provider->getAccessToken('authorization_code', ['code' => $code]);
            $userData = $this->oauthService->validateIdToken(
                $token->getValues()['id_token'],
                $token->getToken()
            );

            // 4. User in DB anlegen oder aktualisieren (E-Mail & Name werden verschlüsselt)
            $dbId = $this->syncUserWithDatabase($userData);

            // 5. Session befüllen — nur das Minimum, keine Tokens im Session-Store
            $_SESSION['user']         = $userData;
            $_SESSION['user']['id']   = $dbId;
            $_SESSION['user_groups']  = $userData['groups'] ?? [];

            header('Location: ' . $_ENV['MHB_FRONTEND_URL'] . '/dashboard');
            exit;

        } catch (\Exception $e) {
            // Internes Detail loggen, nach außen nur opaque Referenz-ID senden
            $errorId = uniqid('auth_err_');
            error_log("[{$errorId}] OAuth Callback Error: " . $e->getMessage());

            $params = http_build_query(['login_failed' => 'true', 'ref' => $errorId]);
            header('Location: ' . $_ENV['MHB_FRONTEND_URL'] . '/?' . $params);
            exit;
        }
    }

    /**
     * GET oauth/logout
     *
     * Zwei-stufiger Logout:
     *   1. Lokale PHP-Session vollständig löschen (Session-Daten + Cookie)
     *   2. Microsoft-Session beenden (Single Sign-Out via post_logout_redirect_uri)
     */
    public function logout(): void
    {
        $this->ensureSession();

        // 1a. Session-Daten leeren
        $_SESSION = [];

        // 1b. Session-Cookie im Browser löschen
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // 1c. Server-seitige Session-Datei entfernen
        session_destroy();

        // 2. Zur Microsoft-Logout-URL weiterleiten
        $msLogoutUrl = sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/logout?%s',
            $_ENV['MHB_BE_MSAL_TENANT_ID'],
            http_build_query(['post_logout_redirect_uri' => $_ENV['MHB_FRONTEND_URL']])
        );

        header('Location: ' . $msLogoutUrl);
        exit;
    }

    /**
     * GET api/me
     *
     * Gibt den aktuellen User und seine Berechtigungen zurück.
     * Wird vom Frontend beim App-Start aufgerufen, um die Session zu prüfen.
     * Die AuthMiddleware hat die Session bereits validiert, bevor dieser
     * Controller aufgerufen wird.
     */
    public function getCurrentUser(): void
    {
        $this->ensureSession();

        if (!isset($_SESSION['user'])) {
            $this->errorResponse('Not authenticated.', 401);
        }

        $this->jsonResponse([
            'id'          => $_SESSION['user']['id'],
            'user'        => $_SESSION['user'],
            'permissions' => [
                'verwaltung'   => \Kai\MhbBackend20\Auth\Middleware\AuthMiddleware::hasGroup('MHB_BE_MSAL_ADMIN_VERWALTUNG'),
                'is_processor' => \Kai\MhbBackend20\Auth\Middleware\AuthMiddleware::hasGroup('MHB_BE_MSAL_TICKETPROCESSORS'),
            ],
        ]);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Legt einen neuen User an oder aktualisiert den Anzeigenamen eines bestehenden.
     *
     * Datenschutz-Design:
     *   - email_hash:           SHA-256 der normalisierten E-Mail — ermöglicht schnellen
     *                           DB-Lookup ohne Klartextsuche
     *   - email_encrypted:      AES-256-GCM verschlüsselt — nur bei Bedarf entschlüsselbar
     *   - display_name_encrypted: AES-256-GCM verschlüsselt — wird bei jedem Login aktualisiert,
     *                           falls der Name in Azure geändert wurde
     *
     * @param array $userData Validierte User-Daten aus dem ID-Token
     * @return int Datenbank-ID des Users
     */
    private function syncUserWithDatabase(array $userData): int
    {
        $email       = $userData['email'] ?? $userData['upn'];
        $displayName = $userData['name'] ?? 'Unbekannter Nutzer';

        // E-Mail normalisieren und hashen für den DB-Lookup
        $emailHash = hash('sha256', strtolower(trim($email)));

        $db   = DB::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT id FROM users WHERE email_hash = ?');
        $stmt->execute([$emailHash]);
        $userRecord = $stmt->fetch();

        // Verschlüsselte Werte für den DB-Store vorbereiten
        $encEmail = Cipher::encrypt($email, $this->encKey);
        $encName  = Cipher::encrypt($displayName, $this->encKey);

        if ($userRecord) {
            // Bestehender User: nur den Namen aktualisieren (E-Mail ändert sich nicht)
            $dbId = $userRecord['id'];
            $db->prepare('UPDATE users SET display_name_encrypted = ? WHERE id = ?')
               ->execute([$encName, $dbId]);
        } else {
            // Neuer User: vollständigen Datensatz anlegen
            $db->prepare('INSERT INTO users (email_hash, email_encrypted, display_name_encrypted) VALUES (?, ?, ?)')
               ->execute([$emailHash, $encEmail, $encName]);
            $dbId = (int) $db->lastInsertId();
        }

        return $dbId;
    }

    /**
     * Stellt sicher, dass eine PHP-Session aktiv ist.
     * Verhindert "headers already sent"-Fehler durch doppelten session_start()-Aufruf.
     */
    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}