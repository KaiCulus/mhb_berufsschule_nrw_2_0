<?php
namespace Kai\MhbBackend20\Tests\Auth;

use PHPUnit\Framework\TestCase;
use Kai\MhbBackend20\Auth\Controllers\OAuthController;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;

class OAuthControllerTest extends TestCase {
    public function testHandleCallbackThrowsOnInvalidState() {
        $_GET['state'] = 'fake_state';
        $_SESSION['oauth2state'] = 'real_state';
        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('Invalid state parameter');
        $controller = new OAuthController();
        $controller->handleCallback();
    }
}
