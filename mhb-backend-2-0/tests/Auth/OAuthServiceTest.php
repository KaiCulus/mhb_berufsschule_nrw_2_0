<?php
namespace Kai\MhbBackend20\Tests\Auth;

use PHPUnit\Framework\TestCase;
use Kai\MhbBackend20\Auth\Services\OAuthService;
use Kai\MhbBackend20\Auth\Exceptions\OAuthException;

class OAuthServiceTest extends TestCase
{
    //################################Hilfsmethoden#########################################
    protected function setUp(): void
    {
        // ✅ Mock für $_ENV
        $_ENV = [
            'MHB_BE_MSAL_TENANT_ID' => getenv('MHB_BE_MSAL_TENANT_ID'),
            'MHB_BE_MSAL_CLIENT_ID' => getenv('MHB_BE_MSAL_CLIENT_ID'),
            'MHB_BE_MSAL_CLIENT_SECRET_VALUE' => 'test-secret',
            'MHB_BE_MSAL_REDIRECT_URI' => 'http://localhost:8000/oauth/callback',
        ];
    }
    private function mockFileGetContents(): void
    {
        $mock = \Mockery::mock('alias:file_get_contents');
        $mock->shouldReceive('file_get_contents')
            ->with('https://login.microsoftonline.com/'['MHB_BE_MSAL_TENANT_ID'].'/discovery/v2.0/keys')
            ->andReturn(json_encode([
                'keys' => [
                    [
                        'x5c' => ['MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...']
                    ]
                ]
            ]));
    }

        private function mockJwtDecode($tokenData): void
        {
            $mock = \Mockery::mock('alias:Firebase\JWT\JWT');
            $mock->shouldReceive('decode')
                ->once()
                ->andReturn((object)$tokenData);
        }

    //#######################################Tests###########################################

    public function testValidateIdTokenThrowsOnInvalidToken()
    {
        // ✅ Mock für JWT::decode()
        $mockJwt = \Mockery::mock('alias:Firebase\JWT\JWT');
        $mockJwt->shouldReceive('decode')
            ->once()
            ->andThrow(new \Firebase\JWT\ExpiredException('Token abgelaufen'));

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('Invalid token claims: Token expired');
        $service = new OAuthService();
        $service->validateIdToken('invalid.token.here');
    }

     public function testValidateIdTokenReturnsUserDataForValidToken()
        {
            // ✅ Mock für file_get_contents()
            $mockFile = \Mockery::mock('alias:file_get_contents');
            $mockFile->shouldReceive('file_get_contents')
                ->with('https://login.microsoftonline.com/'.$_ENV['MHB_BE_MSAL_TENANT_ID'].'/discovery/v2.0/keys')
                ->andReturn(json_encode([
                    'keys' => [
                        [
                            'x5c' => ['MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...']
                        ]
                    ]
                ]));

            // ✅ Mock für JWT::decode()
            $mockJwt = \Mockery::mock('alias:Firebase\JWT\JWT');
            $mockJwt->shouldReceive('decode')
                ->once()
                ->with('valid.token.here', \Mockery::any())
                ->andReturn((object)[
                    'iss' => 'https://login.microsoftonline.com/'.$_ENV['MHB_BE_MSAL_TENANT_ID'].'/v2.0',
                    'aud' => $_ENV['MHB_BE_MSAL_CLIENT_ID'],
                    'exp' => time() + 3600, // ✅ Gültiges Token
                    'name' => 'Test Nutzer',
                    'email' => 'test@example.com'
                ]);

            $service = new OAuthService();
            $userData = $service->validateIdToken('valid.token.here');

            $this->assertEquals('Test Nutzer', $userData['name']);
            $this->assertEquals('test@example.com', $userData['email']);
        }


    public function testValidateIdTokenCallsGetMicrosoftPublicKey()
    {
        
        // ✅ Mock für file_get_contents()
        $mockFile = \Mockery::mock('alias:file_get_contents');
        $mockFile->shouldReceive('file_get_contents')
            ->with('https://login.microsoftonline.com/'.$_ENV['MHB_BE_MSAL_TENANT_ID'].'/discovery/v2.0/keys')
            ->andReturn(json_encode([
                'keys' => [
                    [
                        'x5c' => ['MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...']
                    ]
                ]
            ]));

        // ✅ Mock für JWT::decode()
        $mockJwt = \Mockery::mock('alias:Firebase\JWT\JWT');
        $mockJwt->shouldReceive('decode')
            ->once()
            ->with('valid.token.here', \Mockery::any())
            ->andReturn((object)[
                'iss' => 'https://login.microsoftonline.com/'.$_ENV['MHB_BE_MSAL_TENANT_ID'].'/v2.0',
                'aud' => $_ENV['MHB_BE_MSAL_CLIENT_ID'],
                'exp' => time() + 3600,
                'name' => 'Test Nutzer',
            ]);

        $service = new OAuthService();
        $userData = $service->validateIdToken('valid.token.here');
        $this->assertEquals('Test Nutzer', $userData['name']);
        
    }


    public function testValidateIdTokenThrowsOnExpiredToken()
    {
        $this->mockFileGetContents();
        // 1. Mock für JWT::decode() erstellen
        $mock = \Mockery::mock('alias:Firebase\JWT\JWT');
        $mock->shouldReceive('decode')
            ->once()
            ->andReturn((object)[
                'iss' => 'https://login.microsoftonline.com/' . $_ENV['MHB_BE_MSAL_TENANT_ID'] . '/v2.0',
                'aud' => $_ENV['MHB_BE_MSAL_CLIENT_ID'],
                'exp' => time() - 3600, // Token ist seit 1 Stunde abgelaufen!
                'name' => 'Test Nutzer',
                'email' => 'test@example.com'
            ]);

        // 2. Erwarte eine OAuthException
        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('Invalid token claims');

        // 3. Service aufrufen
        $service = new OAuthService();
        $service->validateIdToken('expired.token.here');
    }

    public function testValidateIdTokenThrowsOnInvalidIssuer()
    {
        $this->mockFileGetContents();
        // 1. Mock für JWT::decode() erstellen (falscher Issuer)
        $mock = \Mockery::mock('alias:Firebase\JWT\JWT');
        $mock->shouldReceive('decode')
            ->once()
            ->andReturn((object)[
                'iss' => 'https://fake-login.com', // ❌ Falscher Issuer!
                'aud' => $_ENV['MHB_BE_MSAL_CLIENT_ID'],
                'exp' => time() + 3600,
                'name' => 'Test Nutzer',
                'email' => 'test@example.com'
            ]);

        // 2. Erwarte eine OAuthException
        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('Invalid token claims');

        // 3. Service aufrufen
        $service = new OAuthService();
        $service->validateIdToken('invalid.issuer.token');
    }

    public function testHandleCallbackThrowsOnMissingCode()
    {
        $_GET['state'] = 'test_state';
        $_SESSION['oauth2state'] = 'test_state';
        // ✅ Kein $_GET['code'] gesetzt

        $controller = new OAuthController();
        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('Authorization code missing');
        $this->expectExceptionCode(400);
        $controller->handleCallback();
    }

    public function testHandleCallbackWithValidState()
    {
        $_GET['state'] = 'test_state';
        $_GET['code'] = 'test_code';
        $_SESSION['oauth2state'] = 'test_state';

        $mockProvider = $this->createMock(GenericProvider::class);
        $mockAccessToken = $this->createMock(\League\OAuth2\Client\Token\AccessToken::class);
        $mockAccessToken->method('getToken')->willReturn('access_token_123');
        $mockAccessToken->method('getRefreshToken')->willReturn(null); // ✅ Null-sicher
        $mockAccessToken->method('getValues')->willReturn(['id_token' => 'id_token_789']);

        $mockProvider->expects($this->once())
            ->method('getAccessToken')
            ->with('authorization_code', ['code' => 'test_code'])
            ->willReturn($mockAccessToken);

        $mockService = $this->createMock(OAuthService::class);
        $mockService->expects($this->once())
            ->method('validateIdToken')
            ->with('id_token_789')
            ->willReturn(['name' => 'Test Nutzer', 'email' => 'test@example.com']);

        $controller = new OAuthController($mockProvider, $mockService);
        $result = $controller->handleCallback();

        $this->assertEquals([
            'access_token' => 'access_token_123',
            'refresh_token' => null, // ✅ Null-sicher
            'user' => ['name' => 'Test Nutzer', 'email' => 'test@example.com']
        ], $result);
    }


}
