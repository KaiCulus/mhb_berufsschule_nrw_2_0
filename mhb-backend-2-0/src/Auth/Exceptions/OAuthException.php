<?php
namespace Kai\MhbBackend20\Auth\Exceptions;

class OAuthException extends \Exception {
    public function __construct(string $message, int $code = 401) {
        parent::__construct($message, $code);
    }
}
