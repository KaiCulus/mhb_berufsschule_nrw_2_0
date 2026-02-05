<?php

namespace Kai\MhbBackend20\Auth\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Auth\Services\OAuthService;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;
use Kai\MhbBackend20\Common\Cipher;
use Kai\MhbBackend20\Database\DB;
use League\OAuth2\Client\Provider\GenericProvider;

class OAuthController extends BaseController {
    private GenericProvider $provider;
    private OAuthService $oauthService;
    private string $encKey;

    public function __construct(
        ?GenericProvider $provider = null,
        ?OAuthService $oauthService = null
    ) {
        $this->encKey = $_ENV['APP_ENCRYPTION_KEY'];
        
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

    /**
     * GET auth/login
     */
    public function redirectToOAuth(): void {
        $this->ensureSession();

        $scopes = ['openid', 'profile', 'email', 'User.Read'];
        $authUrl = $this->provider->getAuthorizationUrl([
            'scope' => implode(' ', $scopes),
        ]);
        
        $_SESSION['oauth2state'] = $this->provider->getState();
        
        header('Location: ' . $authUrl);
        exit;
    }

    /**
     * GET auth/callback
     */
    public function handleCallback(): void {
        $this->ensureSession();

        try {
            // 1. Validierung
            $state = $this->getQueryParam('state');
            if (empty($state) || empty($_SESSION['oauth2state']) || ($state !== $_SESSION['oauth2state'])) {
                unset($_SESSION['oauth2state']);
                throw new OAuthException('Invalid state verification failed.', 403);
            }

            $code = $this->getQueryParam('code');
            if (empty($code)) throw new OAuthException('Authorization code missing', 400);

            // 2. Token Exchange
            $token = $this->provider->getAccessToken('authorization_code', ['code' => $code]);
            $userData = $this->oauthService->validateIdToken(
                $token->getValues()['id_token'], 
                $token->getToken() 
            );

            // 3. User in DB verarbeiten (Verschlüsselung via Cipher-Klasse)
            $dbId = $this->syncUserWithDatabase($userData);

            // 4. Session befüllen
            $_SESSION['user'] = $userData;
            $_SESSION['user']['id'] = $dbId; // Wichtig für Ticket-Erstellung etc.
            $_SESSION['access_token'] = $token->getToken();
            $_SESSION['user_groups'] = $userData['groups'] ?? [];
            header('Location: ' . $_ENV['MHB_FRONTEND_URL'] . '/dashboard?');
            exit;

        } catch (\Exception $e) {
            error_log('OAuth Error: ' . $e->getMessage());
            $errorParams = http_build_query(['error' => 'Login fehlgeschlagen', 'details' => $e->getMessage()]);
            header('Location: ' . $_ENV['MHB_FRONTEND_URL'] . '/?login_failed=true&' . $errorParams);
            exit;
        }

        
    }

    /**
     * GET auth/logout
     */
    public function logout(): void {
        $this->ensureSession();

        // 1. Lokale Session löschen
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', 
                time() - 42000, 
                $params["path"], 
                $params["domain"], 
                $params["secure"], 
                $params["httponly"]
            );
        }
        session_destroy();

        // 2. Microsoft Logout URL generieren
        $tenantId = $_ENV['MHB_BE_MSAL_TENANT_ID'];
        $redirectUri = $_ENV['MHB_FRONTEND_URL'];

        $msLogoutUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/logout?" . http_build_query([
            'post_logout_redirect_uri' => $redirectUri
        ]);

        // 3. Weiterleitung (WICHTIG: Doppelte Anführungszeichen verwenden!)
        header("Location: $msLogoutUrl");
        exit;
    }

    public function getCurrentUser(): void {
        $this->ensureSession();
        
        // Die AuthMiddleware hat die Session bereits befüllt
        if (!isset($_SESSION['user'])) {
            $this->errorResponse('Not authenticated', 401);
        }

        $this->jsonResponse([
            'id' => $_SESSION['user']['id'],
            'user' => $_SESSION['user'],
            'permissions' => [
                'verwaltung' => \Kai\MhbBackend20\Auth\Middleware\AuthMiddleware::hasGroup('MHB_BE_MSAL_ADMIN_VERWALTUNG'),
                'is_processor' => \Kai\MhbBackend20\Auth\Middleware\AuthMiddleware::hasGroup('MHB_BE_MSAL_TICKETPROCESSORS'),
            ]
        ]);
    }

    /**
     * Hilfsfunktion für User-Sync & Verschlüsselung
     */
    private function syncUserWithDatabase(array $userData): int {
        $email = $userData['email'] ?? $userData['upn'];
        $displayName = $userData['name'] ?? 'Unbekannter Nutzer';
        $emailHash = hash('sha256', strtolower(trim($email)));
        
        $db = DB::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id FROM users WHERE email_hash = ?");
        $stmt->execute([$emailHash]);
        $userRecord = $stmt->fetch();

        // Nutzung der Cipher-Klasse für konsistente Verschlüsselung
        $encEmail = Cipher::encrypt($email, $this->encKey);
        $encName = Cipher::encrypt($displayName, $this->encKey);

        if ($userRecord) {
            $dbId = $userRecord['id'];
            $upd = $db->prepare("UPDATE users SET display_name_encrypted = ? WHERE id = ?");
            $upd->execute([$encName, $dbId]);
        } else {
            $ins = $db->prepare("INSERT INTO users (email_hash, email_encrypted, display_name_encrypted) VALUES (?, ?, ?)");
            $ins->execute([$emailHash, $encEmail, $encName]);
            $dbId = (int)$db->lastInsertId();
        }
        
        return $dbId;
    }

    private function ensureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    
}
