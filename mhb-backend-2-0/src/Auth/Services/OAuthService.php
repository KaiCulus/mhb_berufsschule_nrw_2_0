<?php
namespace Kai\MhbBackend20\Auth\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;

class OAuthService {
    /**
     * Validiert das ID-Token von Microsoft.
     * @param string $idToken Das JWT-ID-Token
     * @return array Dekodierte Nutzerdaten (email, name, etc.)
     * @throws OAuthException
     */
    public function validateIdToken(string $idToken): array {
        // 1. Microsofts öffentlichen Schlüssel holen (gecacht für Performance)
        $publicKey = $this->getMicrosoftPublicKey();

        // 2. Token dekodieren und validieren
        try {
            $decoded = JWT::decode($idToken, new Key($publicKey, 'RS256'));
        } catch (\Exception $e) {
            throw new OAuthException('Invalid ID token: ' . $e->getMessage());
        }

        // 3. Claims prüfen
        if (
            $decoded->iss !== "https://login.microsoftonline.com/{$_ENV['MHB_BE_MSAL_TENANT_ID']}/v2.0" ||
            $decoded->aud !== $_ENV['MHB_BE_MSAL_CLIENT_ID'] ||
            $decoded->exp < time()
        ) {
            throw new OAuthException('Invalid token claims');
        }

        return (array) $decoded;
    }

    /**
     * Lädt den öffentlichen Schlüssel von Microsoft (mit Caching).
     */
    private function getMicrosoftPublicKey(): string {
        $cacheFile = __DIR__ . '/../../../config/microsoft_keys.json';
        if (file_exists($cacheFile) && filemtime($cacheFile) > time() - 86400) {
            $keys = json_decode(file_get_contents($cacheFile), true);
        } else {
            $keys = json_decode(file_get_contents(
                "https://login.microsoftonline.com/{$_ENV['MHB_BE_MSAL_TENANT_ID']}/discovery/v2.0/keys"
            ), true);
            file_put_contents($cacheFile, json_encode($keys));
        }
        return "-----BEGIN PUBLIC KEY-----\n" . $keys['keys'][0]['x5c'][0] . "\n-----END PUBLIC KEY-----";
    }
}
