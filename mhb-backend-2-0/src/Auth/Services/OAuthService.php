<?php
namespace Kai\MhbBackend20\Auth\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;

class OAuthService {
    
    /**
     * Validiert das ID-Token und stellt sicher, dass Gruppen geladen sind.
     */
    public function validateIdToken(string $idToken, ?string $accessToken = null): array
    {
        try {
            $publicKey = $this->getMicrosoftPublicKey($idToken);
            $key = new Key($publicKey, 'RS256');
            $decoded = JWT::decode($idToken, $key);

            $userData = (array)$decoded;

            // 1. Standard Claims prüfen
            $this->validateClaims($userData);

            // 2. Gruppen-Fallback: Falls 'groups' im Token fehlt (Overage), via Graph holen
            if (!isset($userData['groups']) && $accessToken) {
                error_log("Overage erkannt oder Gruppen fehlen im Token. Rufe Graph API auf...");
                $userData['groups'] = $this->fetchUserGroupsFromGraph($accessToken);
            }

            // 3. Harte Zugangsprüfung (Lehrer-Gruppe)
            $this->validateGroupMembership($userData);
            return $userData;

        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new OAuthException('Token expired: ' . $e->getMessage(), 401);
        } catch (OAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            error_log("Token validation failed: " . $e->getMessage());
            throw new OAuthException('Token validation failed: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Holt Gruppen direkt von Microsoft Graph (für Overage-Szenarien)
     */
    public function fetchUserGroupsFromGraph(string $accessToken): array 
    {
        $url = 'https://graph.microsoft.com/v1.0/me/memberOf?$select=id';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Graph API Error: " . $response);
            return [];
        }

        $data = json_decode($response, true);
        return array_map(fn($g) => $g['id'], $data['value'] ?? []);
    }

    private function validateClaims(array $data): void
    {
        if (!isset($data['iss']) || strpos($data['iss'], $_ENV['MHB_BE_MSAL_TENANT_ID']) === false) {
            throw new OAuthException('Invalid issuer', 401);
        }
        if (!isset($data['aud']) || $data['aud'] !== $_ENV['MHB_BE_MSAL_CLIENT_ID']) {
            throw new OAuthException('Invalid audience', 401);
        }
    }

    private function validateGroupMembership(array $userData): void
    {
        $requiredGroup = $_ENV['MHB_BE_MSAL_TEACHER_ACCESS_GROUP'] ?? null;
        if (!$requiredGroup) return;

        $userGroups = $userData['groups'] ?? [];

        if (!in_array($requiredGroup, $userGroups)) {
           
            throw new OAuthException('Forbidden: Missing required group membership.', 403);
        }
    }

    private function getMicrosoftPublicKey(string $idToken): string
    {
        $tenantId = $_ENV['MHB_BE_MSAL_TENANT_ID'];
        $keysUrl = "https://login.microsoftonline.com/$tenantId/discovery/v2.0/keys";

        $tokenParts = explode('.', $idToken);
        $tokenHeader = json_decode(base64_decode($tokenParts[0]), true);
        $kid = $tokenHeader['kid'] ?? null;

        if (!$kid) throw new \RuntimeException('No kid found in header');

        $response = file_get_contents($keysUrl); // Einfacherer Abruf als cURL für GET
        $keys = json_decode($response, true);

        foreach ($keys['keys'] as $keyData) {
            if ($keyData['kid'] === $kid) {
                return "-----BEGIN CERTIFICATE-----\n" . chunk_split($keyData['x5c'][0], 64) . "-----END CERTIFICATE-----";
            }
        }

        throw new \RuntimeException("No matching public key found");
    }
}