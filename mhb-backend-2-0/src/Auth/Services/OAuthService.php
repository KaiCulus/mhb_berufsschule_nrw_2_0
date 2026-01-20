<?php
namespace Kai\MhbBackend20\Auth\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;

class OAuthService {
    
    /**
     * Validiert das ID-Token von Microsoft und prüft die Gruppenmitgliedschaft.
     */
    public function validateIdToken(string $idToken): array
    {
        try {
            $publicKey = $this->getMicrosoftPublicKey($idToken);
            $key = new Key($publicKey, 'RS256');
            $decoded = JWT::decode($idToken, $key);

            // Standard Validierungen
            if (!isset($decoded->iss) || strpos($decoded->iss, $_ENV['MHB_BE_MSAL_TENANT_ID']) === false) {
                throw new OAuthException('Invalid issuer claim', 401);
            }
            if (!isset($decoded->aud) || $decoded->aud !== $_ENV['MHB_BE_MSAL_CLIENT_ID']) {
                throw new OAuthException('Invalid audience claim', 401);
            }
            if (!isset($decoded->exp) || $decoded->exp < time()) {
                throw new OAuthException('Token has expired', 401);
            }

            // NEU: Gruppenprüfung (Säule 1 - Authorization)
            $userData = (array)$decoded;
            $this->validateGroupMembership($userData);

            error_log("JWT & Group Validation successful for: " . ($userData['email'] ?? $userData['upn'] ?? 'unknown'));
            
            return $userData;

        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new OAuthException('Token expired: ' . $e->getMessage(), 401);
        } catch (OAuthException $e) {
            // Re-throw OAuthExceptions (wie Group Validation) damit der Code erhalten bleibt
            throw $e;
        } catch (\Exception $e) {
            error_log("Token validation failed: " . $e->getMessage());
            throw new OAuthException('Token validation failed: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Prüft, ob die erforderliche Group-ID im Token vorhanden ist.
     */
    private function validateGroupMembership(array $userData): void
    {
        $requiredGroup = $_ENV['MHB_BE_MSAL_TEACHER_ACCESS_GROUP'] ?? null;
        
        // Wenn keine Gruppe in der ENV definiert ist, lassen wir alle durch (optional)
        // oder werfen einen Fehler (sicherer).
        if (!$requiredGroup) {
            error_log("WARNUNG: MHB_BE_MSAL_TEACHER_ACCESS_GROUP ist nicht in der .env definiert!");
            return; 
        }

        if (!isset($userData['groups']) || !in_array($requiredGroup, $userData['groups'])) {
            error_log("Zugriff verweigert: User ist nicht Mitglied der erforderlichen Gruppe.");
            throw new OAuthException('Forbidden: User does not have the required group membership.', 403);
        }
    }

    private function getMicrosoftPublicKey(string $idToken): string
    {
        $tenantId = $_ENV['MHB_BE_MSAL_TENANT_ID'];
        $keysUrl = "https://login.microsoftonline.com/$tenantId/discovery/v2.0/keys";

        $tokenParts = explode('.', $idToken);
        $tokenHeader = json_decode(base64_decode($tokenParts[0]), true);
        $kid = $tokenHeader['kid'] ?? null;

        if (!$kid) throw new \RuntimeException('No kid found in ID Token header');

        $ch = curl_init($keysUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $response = curl_exec($ch);
        $keys = json_decode($response, true);
        curl_close($ch);

        $selectedCert = null;
        foreach ($keys['keys'] as $keyData) {
            if ($keyData['kid'] === $kid) {
                $selectedCert = $keyData['x5c'][0];
                break;
            }
        }

        if (!$selectedCert) throw new \RuntimeException("No matching public key found");

        return "-----BEGIN CERTIFICATE-----\n" . chunk_split($selectedCert, 64) . "-----END CERTIFICATE-----";
    }
}