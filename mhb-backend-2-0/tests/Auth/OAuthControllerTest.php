<?php
namespace Kai\MhbBackend20\Tests\Auth;

use PHPUnit\Framework\TestCase;
use Kai\MhbBackend20\Auth\Controllers\OAuthController;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;
use Kai\MhbBackend20\Auth\Services\OAuthService;
use League\OAuth2\Client\Provider\GenericProvider;

class OAuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        //Setup Mocks
        $_SESSION = [];
        $_GET = [];
        $_ENV = [
            'MHB_BE_MSAL_TENANT_ID' => 'test-tenant-id',
            'MHB_BE_MSAL_CLIENT_ID' => 'test-client-id',
            'MHB_BE_MSAL_CLIENT_SECRET_VALUE' => 'test-secret', // ✅ Fehlte in deinen Mocks!
            'MHB_BE_MSAL_REDIRECT_URI' => 'http://localhost:8000/oauth/callback',
        ];

        
    }


    public function testRedirectToOAuthGeneratesValidUrl()
    {
        $mockProvider = $this->createMock(GenericProvider::class);
        $mockProvider->expects($this->once())
            ->method('getAuthorizationUrl')
            ->with(['scope' => ['openid', 'profile', 'email', 'User.Read']])
            ->willReturn('https://login.microsoftonline.com/tenant/oauth2/v2.0/authorize?test=123');

        $mockProvider->expects($this->once())
            ->method('getState')
            ->willReturn('test_state');

        $controller = new OAuthController($mockProvider, $this->createMock(OAuthService::class));
        $authUrl = $controller->redirectToOAuth(); // ✅ Kein header() mehr!

        $this->assertEquals('https://login.microsoftonline.com/tenant/oauth2/v2.0/authorize?test=123', $authUrl);
        $this->assertEquals('test_state', $_SESSION['oauth2state']);
    }

    public function testHandleCallbackWithValidState()
    {
        $mockProvider = $this->createMock(GenericProvider::class);
        $mockAccessToken = $this->createMock(\League\OAuth2\Client\Token\AccessToken::class);
        $mockAccessToken->method('getToken')->willReturn('access_token_123');
        $mockAccessToken->method('getRefreshToken')->willReturn('refresh_token_456');
        $mockAccessToken->method('getValues')->willReturn(['id_token' => 'id_token_789']);

        $mockProvider->expects($this->once())
            ->method('getAccessToken')
            ->with('authorization_code', ['code' => 'test_code'])
            ->willReturn($mockAccessToken);

        $mockService = $this->createMock(\Kai\MhbBackend20\Auth\Services\OAuthService::class);
        $mockService->expects($this->once())
            ->method('validateIdToken')
            ->with('id_token_789')
            ->willReturn(['name' => 'Test Nutzer', 'email' => 'test@example.com']);

        $_GET['state'] = 'test_state';
        $_GET['code'] = 'test_code';
        $_SESSION['oauth2state'] = 'test_state';

        $controller = new OAuthController($mockProvider, $mockService);
        $result = $controller->handleCallback();
        $this->assertEquals([
            'access_token' => 'access_token_123',
            'refresh_token' => 'refresh_token_456',
            'user' => ['name' => 'Test Nutzer', 'email' => 'test@example.com']
        ], $result);
    }

    public function testHandleCallbackThrowsOnInvalidState()
    {
        $_GET['state'] = 'fake_state';
        $_SESSION['oauth2state'] = 'real_state';
        $controller = new OAuthController();
        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('Invalid state parameter (possible CSRF attack)');
        $controller->handleCallback();
    }

    public function testHandleCallbackThrowsOnMissingCode()
    {
        $_GET['state'] = 'test_state';
        $_SESSION['oauth2state'] = 'test_state';

        $mockProvider = $this->createMock(GenericProvider::class);
        $mockProvider->expects($this->once())
            ->method('getAccessToken')
            ->with('authorization_code', ['code' => null]) // ✅ Erwartet null
            ->willThrowException(new \League\OAuth2\Client\Provider\Exception\IdentityProviderException
            ('Missing code',
             400,
             new \Exception('Previous exception message')
            ));

        $controller = new OAuthController(
            $mockProvider,
            $this->createMock(OAuthService::class)
        );
        $this->expectException(\League\OAuth2\Client\Provider\Exception\IdentityProviderException::class);
        $controller->handleCallback();
    }
}
