<?php

namespace Kai\MhbBackend20\Database\Services\Tickets;

/**
 * ImageUploadException
 *
 * Trägt einen HTTP-Statuscode mit, damit der Controller die Exception
 * direkt in eine passende Fehlerantwort übersetzen kann, ohne dass der
 * Service selbst HTTP-Wissen braucht.
 */
class ImageUploadException extends \RuntimeException
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode = 400)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}