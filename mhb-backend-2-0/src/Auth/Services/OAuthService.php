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
        $keysUrl  = "https://login.microsoftonline.com/{$tenantId}/discovery/v2.0/keys";
        $cacheKey = 'ms_jwks_' . md5($tenantId);

        // ── 1. Parse kid from token header ──────────────────────────────────────
        $tokenParts = explode('.', $idToken);
        if (count($tokenParts) < 2) {
            throw new \RuntimeException('Malformed JWT: not enough segments.');
        }

        $headerJson  = base64_decode(strtr($tokenParts[0], '-_', '+/') . str_repeat('=', (4 - strlen($tokenParts[0]) % 4) % 4));
        $tokenHeader = json_decode($headerJson, true);
        $kid         = $tokenHeader['kid'] ?? null;

        if (!$kid) {
            throw new \RuntimeException('No kid found in JWT header.');
        }

        // ── 2. Try APCu cache first ──────────────────────────────────────────────
        $keys = null;
        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch($cacheKey, $success);
            if ($success) {
                $keys = $cached;
            }
        }

        // ── 3. Fetch from Microsoft if not cached ────────────────────────────────
        if ($keys === null) {
            $ch = curl_init($keysUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,      // fail fast if Microsoft is slow
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FAILONERROR    => false,   // we check HTTP code ourselves
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException('JWKS fetch failed (cURL): ' . $curlError);
            }
            if ($httpCode !== 200) {
                throw new \RuntimeException("JWKS fetch failed: HTTP {$httpCode}");
            }

            $keys = json_decode($response, true);

            if (!isset($keys['keys']) || !is_array($keys['keys'])) {
                throw new \RuntimeException('JWKS response malformed or empty.');
            }

            // Cache for 1 hour — Microsoft rotates keys very infrequently
            if (function_exists('apcu_store')) {
                apcu_store($cacheKey, $keys, 3600);
            }
        }

        // ── 4. Find the matching key by kid ─────────────────────────────────────
        foreach ($keys['keys'] as $keyData) {
            if (($keyData['kid'] ?? '') === $kid) {
                if (empty($keyData['x5c'][0])) {
                    throw new \RuntimeException("Matched key (kid={$kid}) has no x5c certificate.");
                }
                return "-----BEGIN CERTIFICATE-----\n"
                    . chunk_split($keyData['x5c'][0], 64)
                    . "-----END CERTIFICATE-----";
            }
        }

        // ── 5. kid not found — cache may be stale, retry once ───────────────────
        if (function_exists('apcu_delete')) {
            apcu_delete($cacheKey);
        }
        throw new \RuntimeException(
            "No matching public key found for kid={$kid}. Cache cleared — next request will re-fetch."
        );
    }
}