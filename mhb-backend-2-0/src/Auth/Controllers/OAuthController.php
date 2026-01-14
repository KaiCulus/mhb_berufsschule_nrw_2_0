<?php
namespace Kai\MhbBackend20\Auth\Controllers;

use Kai\MhbBackend20\Auth\Services\OAuthService;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;
use League\OAuth2\Client\Provider\GenericProvider;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class OAuthController {
    private GenericProvider $provider;
    private OAuthService $oauthService;

    public function __construct() {
        $this->provider = new GenericProvider([
            'clientId' => $_ENV['MHB_BE_MSAL_CLIENT_ID'],
            'clientSecret' => $_ENV['MHB_BE_MSAL_CLIENT_SECRET_VALUE'],
            'redirectUri' => $_ENV['MHB_BE_MSAL_REDIRECT_URI'],
            'urlAuthorize' => 'https://login.microsoftonline.com/' . $_ENV['MHB_BE_MSAL_TENANT_ID'] . '/oauth2/v2.0/authorize',
            'urlAccessToken' => 'https://login.microsoftonline.com/' . $_ENV['MHB_BE_MSAL_TENANT_ID'] . '/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
        ]);
        $this->oauthService = new OAuthService();
    }

    /**
     * Leitet den Nutzer zu Microsoft für den Login um.
     */
   public function redirectToOAuth(): void {
        $scopes = ['openid', 'profile', 'email', 'User.Read'];
        $authUrl = $this->provider->getAuthorizationUrl(['scope' => $scopes]);
        $_SESSION['oauth2state'] = $this->provider->getState();

        // TODO:Temporär für Debugging
        echo "<h1>Debug: OAuth-URL</h1>";
        echo "<p>Klicke auf den Link, um zu Microsoft umgeleitet zu werden:</p>";
        echo "<a href='$authUrl' target='_blank'>$authUrl</a>";
        exit;

        // TODO:Kommentiere die Umleitung aus, bis du sicher bist, dass die URL korrekt ist
        // header('Location: ' . $authUrl);
        // exit;
    }

    /**
     * Behandelt den Callback von Microsoft nach dem Login.
     * @throws OAuthException
     */
    public function handleCallback(): array {
        // 1. CSRF-Schutz: State prüfen
        if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
            throw new OAuthException('Invalid state parameter (possible CSRF attack)');
        }

        // 2. Access-Token holen
        $token = $this->provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        // 3. ID-Token validieren (optional, aber empfohlen)
        $userData = $this->oauthService->validateIdToken($token->getValues()['id_token']);

        // 4. Nutzerdaten zurückgeben (oder in Session speichern)
        return [
            'access_token' => $token->getToken(),
            'refresh_token' => $token->getRefreshToken(),
            'user' => $userData,
        ];
    }
}
