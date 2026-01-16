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
    public function validateIdToken(string $idToken): array
    {
        try {
            $publicKey = $this->getMicrosoftPublicKey();
            $decoded = JWT::decode($idToken, new Key($publicKey, 'RS256'));
        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new OAuthException('Invalid token claims: Token expired', 401);
        } catch (\Exception $e) {
            throw new OAuthException('Invalid token claims: ' . $e->getMessage(), 401);
        }

        // ✅ Prüfe die Claims (iss, aud, exp)
        if (
            strpos($decoded->iss, $_ENV['MHB_BE_MSAL_TENANT_ID']) === false ||
            $decoded->aud !== $_ENV['MHB_BE_MSAL_CLIENT_ID'] ||
            $decoded->exp < time()
        ) {
            throw new OAuthException('Invalid token claims', 401);
        }

        return (array)$decoded;
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
