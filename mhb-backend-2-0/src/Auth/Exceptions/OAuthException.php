<?php

namespace Kai\MhbBackend20\Auth\Exceptions;

/**
 * OAuthException
 *
 * Spezialisierte Exception für Fehler im OAuth-Authentifizierungsfluss.
 *
 * Vorteile gegenüber einer generischen \Exception:
 *   - Catch-Blöcke können gezielt auf Auth-Fehler reagieren (catch OAuthException)
 *   - Der HTTP-Statuscode wird direkt mitgeführt (Standard: 401)
 *   - Klar unterscheidbar von technischen Fehlern (z.B. DB-Fehler, Netzwerkfehler)
 *
 * Verwendete HTTP-Codes:
 *   401 — Nicht authentifiziert (abgelaufenes Token, ungültige Signatur)
 *   403 — Authentifiziert, aber keine Berechtigung (fehlende Gruppe)
 *
 * Verwendung:
 *   throw new OAuthException('Token abgelaufen.', 401);
 *   throw new OAuthException('Fehlende Gruppen-Mitgliedschaft.', 403);
 */
class OAuthException extends \Exception
{
    public function __construct(string $message, int $code = 401)
    {
        parent::__construct($message, $code);
    }
}