<?php
namespace Kai\MhbBackend20\Auth\Controllers;

use Kai\MhbBackend20\Auth\Services\OAuthService;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;
use League\OAuth2\Client\Provider\GenericProvider;

class OAuthController {
    private GenericProvider $provider;
    private OAuthService $oauthService;

    public function __construct(
        ?GenericProvider $provider = null,
        ?OAuthService $oauthService = null
    ) {
        // Fragt erst ab, ob $provider gesetzt ist.
        $this->provider = $provider ?: new GenericProvider([
            'clientId' => $_ENV['MHB_BE_MSAL_CLIENT_ID'],
            'clientSecret' => $_ENV['MHB_BE_MSAL_CLIENT_SECRET_VALUE'],
            'redirectUri' => $_ENV['MHB_BE_MSAL_REDIRECT_URI'],
            'urlAuthorize' => 'https://login.microsoftonline.com/' . $_ENV['MHB_BE_MSAL_TENANT_ID'] . '/oauth2/v2.0/authorize',
            'urlAccessToken' => 'https://login.microsoftonline.com/' . $_ENV['MHB_BE_MSAL_TENANT_ID'] . '/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
        ]);
        $this->oauthService = $oauthService ?: new OAuthService();
    }

    /**
     * Leitet den Nutzer zu Microsoft für den Login um.
     */
    public function redirectToOAuth(): void
    {
        // Session muss gestartet sein (passiert in index.php, aber sicherheitshalber checken)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $scopes = ['openid', 'profile', 'email', 'User.Read'];
        $authUrl = $this->provider->getAuthorizationUrl([
            'scope' => implode(' ', $scopes),
        ]);
        
        $_SESSION['oauth2state'] = $this->provider->getState();
        
        error_log('Login gestartet. State: ' . $_SESSION['oauth2state']);
        header('Location: ' . $authUrl);
        exit;
    }

    /**
     * Behandelt den Callback von Microsoft nach dem Login.
     */
    public function handleCallback(): void
    {
        // WICHTIG: Session starten, um auf oauth2state zuzugreifen
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        try {
            // 1. CSRF-Schutz: State prüfen
            if (empty($_GET['state']) || empty($_SESSION['oauth2state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
                // State aus Session löschen um Wiederverwendung zu verhindern
                unset($_SESSION['oauth2state']);
                throw new OAuthException('Invalid state verification failed.', 403);
            }

            // 2. Code prüfen
            if (empty($_GET['code'])) {
                throw new OAuthException('Authorization code missing', 400);
            }

            // 3. Access-Token holen
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);

            // 4. Nutzerdaten validieren (via ID Token)
            $userData = $this->oauthService->validateIdToken($token->getValues()['id_token']);

            // 5. Optional: Daten in Session speichern (für spätere Backend-Calls)
            $_SESSION['user'] = $userData;
            $_SESSION['access_token'] = $token->getToken();
            
            // 6. Redirect zum Frontend MIT Daten in der URL
            // Wir kodieren die User-Daten als JSON, damit sie sauber übertragen werden
            $queryParams = http_build_query([
                'access_token' => $token->getToken(),
                'user' => json_encode($userData)
            ]);

            // Stelle sicher, dass MHB_FRONTEND_URL in der .env ohne Trailing Slash definiert ist (z.B. http://localhost:5173)
            $redirectUrl = $_ENV['MHB_FRONTEND_URL'] . '/dashboard?' . $queryParams;
            
            header('Location: ' . $redirectUrl);
            exit;

        } catch (\Exception $e) {
            error_log('OAuth Error: ' . $e->getMessage());
            
            // Bei Fehler: Redirect zum Frontend mit Fehlermeldung
            $errorParams = http_build_query([
                'error' => 'Login fehlgeschlagen',
                'details' => $e->getMessage()
            ]);
            
            header('Location: ' . $_ENV['MHB_FRONTEND_URL'] . '/?login_failed=true&' . $errorParams);
            exit;
        }
    }
}