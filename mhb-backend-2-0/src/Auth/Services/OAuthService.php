<?php
namespace Kai\MhbBackend20\Auth\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;

class OAuthService {
    
    /**
     * Validiert das ID-Token von Microsoft.
     */
    public function validateIdToken(string $idToken): array
    {
        try {
            // 1. Hole den passenden öffentlichen Schlüssel basierend auf dem Token
            $publicKey = $this->getMicrosoftPublicKey($idToken);

            // 2. Validierung der Signatur mit dem Key-Objekt
            // Das Zertifikat wird von der Library automatisch als Public Key genutzt
            $key = new Key($publicKey, 'RS256');
            $decoded = JWT::decode($idToken, $key);

            // 3. Validierung der Claims (Issuer, Audience, Expiration)
            // Prüfe Tenant ID im Issuer
            if (!isset($decoded->iss) || strpos($decoded->iss, $_ENV['MHB_BE_MSAL_TENANT_ID']) === false) {
                throw new OAuthException('Invalid issuer claim', 401);
            }

            // Prüfe Client ID (App ID)
            if (!isset($decoded->aud) || $decoded->aud !== $_ENV['MHB_BE_MSAL_CLIENT_ID']) {
                throw new OAuthException('Invalid audience claim', 401);
            }

            // Prüfe Ablaufdatum
            if (!isset($decoded->exp) || $decoded->exp < time()) {
                throw new OAuthException('Token has expired', 401);
            }

            error_log("JWT Validation successful for user: " . ($decoded->email ?? $decoded->upn ?? 'unknown'));
            
            return (array)$decoded;

        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new OAuthException('Token expired: ' . $e->getMessage(), 401);
        } catch (\Exception $e) {
            error_log("Token validation failed: " . $e->getMessage());
            throw new OAuthException('Token validation failed: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Lädt die öffentlichen Schlüssel von Microsoft und extrahiert den richtigen Key.
     */
    private function getMicrosoftPublicKey(string $idToken): string
    {
        $tenantId = $_ENV['MHB_BE_MSAL_TENANT_ID'];
        $keysUrl = "https://login.microsoftonline.com/$tenantId/discovery/v2.0/keys";

        // 1. Finde die 'kid' im Header des aktuellen Tokens
        $tokenParts = explode('.', $idToken);
        $tokenHeader = json_decode(base64_decode($tokenParts[0]), true);
        $kid = $tokenHeader['kid'] ?? null;

        if (!$kid) {
            throw new \RuntimeException('No kid found in ID Token header');
        }

        // 2. Hole Keys von Microsoft (In Produktion solltest du das cachen!)
        $ch = curl_init($keysUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $response = curl_exec($ch);
        $keys = json_decode($response, true);
        curl_close($ch);

        if (empty($keys['keys'])) {
            throw new \RuntimeException('Failed to load Microsoft public keys');
        }

        // 3. Suche den Key, der zur 'kid' des Tokens passt
        $selectedCert = null;
        foreach ($keys['keys'] as $keyData) {
            if ($keyData['kid'] === $kid) {
                $selectedCert = $keyData['x5c'][0];
                break;
            }
        }

        if (!$selectedCert) {
            throw new \RuntimeException("No matching public key found for kid: $kid");
        }

        // 4. Formatiere als X.509 Zertifikat
        return "-----BEGIN CERTIFICATE-----\n" . 
               chunk_split($selectedCert, 64) . 
               "-----END CERTIFICATE-----";
    }
}