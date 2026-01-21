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

            //Email nutzen, um DB Eintrag für Nutzer anzulegen/ Die in der DB enthaltene Nutzer ID herauszufinden, um diese später für DB_Anfragen bereitzuhalten.

            $email = $userData['email'] ?? $userData['upn'];
            $db = \Kai\MhbBackend20\Database\DB::getInstance()->getConnection();

            // Suchen via Hash -> Sonst neuen Eintrag anlegen.
            $emailHash = hash('sha256', $email);
            $stmt = $db->prepare("SELECT id FROM users WHERE email_hash = ?");
            $stmt->execute([$emailHash]);
            $userRecord = $stmt->fetch();

            if ($userRecord) {
                $dbId = $userRecord['id'];
            } else {
                // Neu anlegen mit Verschlüsselung 
                $encryptionKey = $_ENV['APP_ENCRYPTION_KEY'];
                $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
                $encrypted = openssl_encrypt($email, 'aes-256-cbc', $encryptionKey, 0, $iv);
                $storageValue = base64_encode($iv . $encrypted);

                $ins = $db->prepare("INSERT INTO users (email_hash, email_encrypted) VALUES (?, ?)");
                $ins->execute([$emailHash, $storageValue]);
                $dbId = $db->lastInsertId();
            }

            // 5. Optional: Daten in Session speichern (für spätere Backend-Calls)
            $_SESSION['user'] = $userData;
            $_SESSION['access_token'] = $token->getToken();

            // NEU: Gruppen abrufen für die spätere Admin-Prüfung
            try {
                //TODO: Eventuell graphClient konfigurieren falls wir später nochmal live von der API anfragen wollen.
                //$graphClient = new \Kai\MhbBackend20\Graph\GraphClient();
                // Wir nutzen den soeben erhaltenen Access Token für den Client
                // Falls dein GraphClient das Token selbst via Client Credentials holt, 
                // müssen wir hier unterscheiden: Wir brauchen die Gruppen des AKTUELLEN Users.
                
                // Einfachste Methode für den Anfang: Gruppen aus dem ID-Token (falls konfiguriert)
                $_SESSION['user_groups'] = $userData['groups'] ?? [];
                
            } catch (\Exception $e) {
                error_log("Gruppen konnten nicht geladen werden: " . $e->getMessage());
                $_SESSION['user_groups'] = [];
            }
            
            // 6. Redirect zum Frontend MIT Daten in der URL
            // Wir kodieren die User-Daten als JSON, damit sie sauber übertragen werden
            $queryParams = http_build_query([
                'access_token' => $token->getToken(),
                'id_token' => $token->getValues()['id_token'],
                'db_id' => $dbId,
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

    /**
     * Beendet die lokale Session und leitet zum Microsoft-Logout weiter.
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Lokale PHP-Session zerstören
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        // 2. Microsoft Logout URL zusammenbauen
        // post_logout_redirect_uri ist die URL, zu der MS den User nach dem Logout zurückschickt
        $tenantId = $_ENV['MHB_BE_MSAL_TENANT_ID'];
        $postLogoutRedirect = $_ENV['MHB_FRONTEND_URL']; 
        
        $msLogoutUrl = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/logout?" . http_build_query([
            'post_logout_redirect_uri' => $postLogoutRedirect
        ]);

        // 3. Umleiten zu Microsoft
        header('Location: ' . $msLogoutUrl);
        exit;
    }

}