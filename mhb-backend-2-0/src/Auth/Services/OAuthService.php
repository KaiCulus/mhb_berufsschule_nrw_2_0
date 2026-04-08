<?php

namespace Kai\MhbBackend20\Auth\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;

/**
 * OAuthService
 *
 * Verantwortlich für die kryptografische Validierung von Microsoft ID-Tokens
 * und das Laden von Gruppen-Mitgliedschaften via Microsoft Graph API.
 *
 * Validierungsablauf (validateIdToken):
 *   1. Öffentlichen Schlüssel von Microsoft laden (mit APCu-Cache)
 *   2. JWT-Signatur prüfen (RS256)
 *   3. Standard-Claims validieren (Issuer, Audience)
 *   4. Gruppen laden — entweder aus dem Token oder via Graph API (Overage)
 *   5. Pflicht-Gruppen-Mitgliedschaft prüfen (Teacher Access Group)
 */
class OAuthService
{
    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Validiert ein Microsoft ID-Token vollständig.
     *
     * @param string      $idToken     Das JWT ID-Token aus dem OAuth-Callback
     * @param string|null $accessToken Access Token — wird benötigt wenn Gruppen
     *                                 nicht im ID-Token enthalten sind (Overage)
     * @return array Validierte und angereicherte User-Daten
     * @throws OAuthException Bei ungültigem Token, falschen Claims oder fehlender Gruppe
     */
    public function validateIdToken(string $idToken, ?string $accessToken = null): array
    {
        try {
            // 1. Öffentlichen Schlüssel laden und JWT-Signatur prüfen
            $publicKey = $this->getMicrosoftPublicKey($idToken);
            $decoded   = JWT::decode($idToken, new Key($publicKey, 'RS256'));
            $userData  = (array) $decoded;

            // 2. Pflicht-Claims validieren (Issuer + Audience)
            $this->validateClaims($userData);

            // 3. Gruppen-Fallback: Microsoft kürzt den Token bei >200 Gruppen (Overage).
            //    In diesem Fall fehlt 'groups' im Token und muss via Graph API nachgeladen werden.
            if (!isset($userData['groups']) && $accessToken) {
                error_log('OAuthService: Gruppen fehlen im Token (Overage) — lade via Graph API.');
                $userData['groups'] = $this->fetchUserGroupsFromGraph($accessToken);
            }

            // 4. Zugang zur Anwendung prüfen (Lehrer-Gruppe)
            $this->validateGroupMembership($userData);

            return $userData;

        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new OAuthException('Token abgelaufen: ' . $e->getMessage(), 401);
        } catch (OAuthException $e) {
            throw $e; // OAuthExceptions unverändert weiterwerfen
        } catch (\Exception $e) {
            error_log('OAuthService: Token-Validierung fehlgeschlagen: ' . $e->getMessage());
            throw new OAuthException('Token-Validierung fehlgeschlagen.', 401);
        }
    }

    /**
     * Holt alle Gruppen-Mitgliedschaften eines Users via Microsoft Graph API.
     *
     * Wird nur im Overage-Fall aufgerufen (Token enthält keine 'groups'-Claim).
     * Unterstützt Pagination — holt alle Seiten, auch wenn der User >100 Gruppen hat.
     *
     * @param string $accessToken Gültiger Microsoft Access Token
     * @return array Liste von Azure-Gruppen-IDs (GUIDs)
     */
    public function fetchUserGroupsFromGraph(string $accessToken): array
    {
        $groups = [];
        $url    = 'https://graph.microsoft.com/v1.0/me/memberOf?$select=id';

        // Pagination: Graph API gibt max. 100 Einträge pro Seite zurück.
        // @odata.nextLink enthält die URL zur nächsten Seite, falls vorhanden.
        while ($url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $accessToken,
                    'Accept: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                error_log('OAuthService: Graph API Fehler beim Laden der Gruppen. HTTP ' . $httpCode);
                break; // Sicher fehlschlagen — leere Gruppen-Liste führt zu 403 in validateGroupMembership
            }

            $data   = json_decode($response, true);
            $groups = array_merge($groups, array_map(fn($g) => $g['id'], $data['value'] ?? []));

            // Nächste Seite laden oder Schleife beenden
            $url = $data['@odata.nextLink'] ?? null;
        }

        return $groups;
    }

    // =========================================================================
    // Private Validation Helpers
    // =========================================================================

    /**
     * Prüft Issuer und Audience des JWT gegen die konfigurierten .env-Werte.
     *
     * Issuer muss die Tenant-ID enthalten (schützt vor Token-Confusion zwischen Tenants).
     * Audience muss exakt der Client-ID entsprechen (schützt vor Token-Hijacking).
     *
     * @throws OAuthException Bei ungültigem Issuer oder Audience
     */
    private function validateClaims(array $data): void
    {
        if (!isset($data['iss']) || strpos($data['iss'], $_ENV['MHB_BE_MSAL_TENANT_ID']) === false) {
            throw new OAuthException('Ungültiger Token-Issuer.', 401);
        }

        if (!isset($data['aud']) || $data['aud'] !== $_ENV['MHB_BE_MSAL_CLIENT_ID']) {
            throw new OAuthException('Ungültige Token-Audience.', 401);
        }
    }

    /**
     * Prüft ob der User Mitglied der konfigurierten Zugangsgruppe ist.
     *
     * Ist MHB_BE_MSAL_TEACHER_ACCESS_GROUP nicht gesetzt, wird die Prüfung
     * übersprungen (ermöglicht lokale Entwicklung ohne Gruppen-Konfiguration).
     *
     * @throws OAuthException Wenn der User nicht in der Pflichtgruppe ist (403)
     */
    private function validateGroupMembership(array $userData): void
    {
        $requiredGroup = $_ENV['MHB_BE_MSAL_TEACHER_ACCESS_GROUP'] ?? null;

        // Gruppe nicht konfiguriert → Prüfung überspringen (z.B. in der lokalen Entwicklung)
        if (!$requiredGroup) {
            return;
        }

        $userGroups = $userData['groups'] ?? [];

        if (!in_array($requiredGroup, $userGroups, strict: true)) {
            throw new OAuthException('Zugriff verweigert: Fehlende Gruppen-Mitgliedschaft.', 403);
        }
    }

    /**
     * Lädt den passenden öffentlichen Schlüssel von Microsofts JWKS-Endpoint.
     *
     * Ablauf:
     *   1. kid (Key ID) aus dem JWT-Header lesen (base64url-dekodiert)
     *   2. JWKS aus APCu-Cache laden, falls vorhanden (1 Stunde TTL)
     *   3. Falls kein Cache: JWKS per cURL mit Timeout laden und cachen
     *   4. Passenden Schlüssel anhand der kid suchen und als PEM zurückgeben
     *   5. Bei Cache-Miss nach erstem Fetch: Cache löschen für nächsten Request
     *
     * @param string $idToken Das JWT, dessen Header die kid enthält
     * @return string PEM-formatierter X.509-Zertifikatsstring
     * @throws \RuntimeException Bei Netzwerkfehlern, malformed JWT oder unbekannter kid
     */
    private function getMicrosoftPublicKey(string $idToken): string
    {
        $tenantId = $_ENV['MHB_BE_MSAL_TENANT_ID'];
        $keysUrl  = "https://login.microsoftonline.com/{$tenantId}/discovery/v2.0/keys";
        $cacheKey = 'ms_jwks_' . md5($tenantId);

        // 1. kid aus dem JWT-Header lesen (JWT verwendet base64url, nicht standard base64)
        $tokenParts = explode('.', $idToken);
        if (count($tokenParts) < 2) {
            throw new \RuntimeException('Malformed JWT: zu wenige Segmente.');
        }

        $headerJson  = base64_decode(
            strtr($tokenParts[0], '-_', '+/') .
            str_repeat('=', (4 - strlen($tokenParts[0]) % 4) % 4)
        );
        $tokenHeader = json_decode($headerJson, true);
        $kid         = $tokenHeader['kid'] ?? null;

        if (!$kid) {
            throw new \RuntimeException('Keine kid im JWT-Header gefunden.');
        }

        // 2. APCu-Cache prüfen — vermeidet HTTP-Request bei jedem Login
        $keys = null;
        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch($cacheKey, $success);
            if ($success) {
                $keys = $cached;
            }
        }

        // 3. JWKS von Microsoft laden, falls nicht im Cache
        if ($keys === null) {
            $ch = curl_init($keysUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FAILONERROR    => false,
            ]);

            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException('JWKS-Abruf fehlgeschlagen (cURL): ' . $curlError);
            }
            if ($httpCode !== 200) {
                throw new \RuntimeException("JWKS-Abruf fehlgeschlagen: HTTP {$httpCode}");
            }

            $keys = json_decode($response, true);

            if (!isset($keys['keys']) || !is_array($keys['keys'])) {
                throw new \RuntimeException('JWKS-Antwort ungültig oder leer.');
            }

            // 1 Stunde cachen — Microsoft rotiert Schlüssel sehr selten
            if (function_exists('apcu_store')) {
                apcu_store($cacheKey, $keys, 3600);
            }
        }

        // 4. Passenden Schlüssel anhand der kid suchen
        foreach ($keys['keys'] as $keyData) {
            if (($keyData['kid'] ?? '') === $kid) {
                if (empty($keyData['x5c'][0])) {
                    throw new \RuntimeException("Gefundener Schlüssel (kid={$kid}) enthält kein x5c-Zertifikat.");
                }

                return "-----BEGIN CERTIFICATE-----\n"
                    . chunk_split($keyData['x5c'][0], 64)
                    . "-----END CERTIFICATE-----";
            }
        }

        // 5. kid nicht gefunden — Cache könnte veraltet sein (seltene Schlüssel-Rotation).
        //    Cache löschen, damit der nächste Request frische Schlüssel lädt.
        if (function_exists('apcu_delete')) {
            apcu_delete($cacheKey);
        }

        throw new \RuntimeException(
            "Kein passender Schlüssel für kid={$kid} gefunden. Cache geleert — nächster Request lädt neu."
        );
    }
}