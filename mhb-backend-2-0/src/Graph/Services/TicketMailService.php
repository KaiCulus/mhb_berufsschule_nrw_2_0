<?php

namespace Kai\MhbBackend20\Graph\Services;

use Kai\MhbBackend20\Graph\GraphClient;

class TicketMailService {
    private GraphClient $graphClient;
    private string $senderMail;

    public function __construct() {
        $this->graphClient = new GraphClient();
        // Die Mail des Tickethelpers (Sende-Account)
        $this->senderMail = $_ENV['TICKETHELPER_ADDRESS'];
    }

    /**
     * Sendet eine E-Mail über den Microsoft Graph
     */
    public function sendNotification(string $to, string $subject, string $htmlContent): bool {
        if (str_contains($to, '=') && strlen($to) > 40) {
             error_log("TicketMailService: Empfängeradresse scheint noch verschlüsselt zu sein!");
             // Optional: Hier Cipher::decrypt aufrufen, falls nötig
        }
        
        $endpoint = "/users/{$this->senderMail}/sendMail";
        
        $body = [
            "message" => [
                "subject" => $subject,
                "body" => [
                    "contentType" => "HTML",
                    "content" => $htmlContent
                ],
                "toRecipients" => [
                    [
                        "emailAddress" => ["address" => $to]
                    ]
                ]
            ]
        ];

        try {
            $this->graphClient->request('POST', $endpoint, ['json' => $body]);
            return true;
        } catch (\Exception $e) {
            error_log("TicketMailService Fehler: " . $e->getMessage());
            return false;
        }
    }
}