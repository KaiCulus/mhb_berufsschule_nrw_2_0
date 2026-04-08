<?php

namespace Kai\MhbBackend20\Graph\Services;

use Kai\MhbBackend20\Graph\GraphClient;

/**
 * TicketMailService
 *
 * Versendet E-Mail-Benachrichtigungen über die Microsoft Graph API.
 *
 * Verwendet einen dedizierten "Tickethelper"-Account als Absender.
 * Die Zugangsdaten des Accounts werden nicht hier verwaltet — der GraphClient
 * übernimmt die Authentifizierung via Client Credentials.
 *
 * Alle E-Mails werden als HTML versendet. Der aufrufende Code ist dafür
 * verantwortlich, Nutzereingaben vor der Übergabe zu escapen.
 */
class TicketMailService
{
    private GraphClient $graphClient;
    private string $senderMail;

    public function __construct()
    {
        $this->graphClient = new GraphClient();
        $this->senderMail  = $_ENV['TICKETHELPER_ADDRESS'];
    }

    /**
     * Sendet eine HTML-E-Mail über Microsoft Graph.
     *
     * Validiert die Empfängeradresse vor dem Versand — verhindert, dass
     * versehentlich noch verschlüsselte Adressen aus der DB gesendet werden.
     *
     * Gibt true bei Erfolg zurück. Bei Fehlern wird geloggt und false zurückgegeben —
     * der Caller entscheidet ob ein fehlgeschlagener Mailversand den Request abbricht.
     *
     * @param string $to          Empfänger-E-Mail-Adresse (Klartext, nicht verschlüsselt)
     * @param string $subject     Betreff der E-Mail
     * @param string $htmlContent HTML-Inhalt (Nutzereingaben müssen bereits escaped sein)
     * @return bool               true bei Erfolg, false bei Fehler
     */
    public function sendNotification(string $to, string $subject, string $htmlContent): bool
    {
        // Empfängeradresse validieren — fängt ab wenn Cipher::decrypt vergessen wurde
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("TicketMailService: Ungültige Empfängeradresse — E-Mail nicht gesendet. Adresse: '{$to}'");
            return false;
        }

        $endpoint = "/users/{$this->senderMail}/sendMail";

        $body = [
            'message' => [
                'subject' => $subject,
                'body'    => [
                    'contentType' => 'HTML',
                    'content'     => $htmlContent,
                ],
                'toRecipients' => [
                    ['emailAddress' => ['address' => $to]],
                ],
            ],
        ];

        try {
            $this->graphClient->request('POST', $endpoint, ['json' => $body]);
            return true;
        } catch (\Exception $e) {
            error_log("TicketMailService: Versand an '{$to}' fehlgeschlagen: " . $e->getMessage());
            return false;
        }
    }
}