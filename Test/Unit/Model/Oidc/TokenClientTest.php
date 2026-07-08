<?php
/**
 * Copyright © MageDevGroup. All rights reserved.
 */
declare(strict_types=1);

namespace MageDevGroup\SsoCore\Test\Unit\Model\Oidc;

use MageDevGroup\SsoCore\Exception\TokenException;
use MageDevGroup\SsoCore\Model\Oidc\TokenClient;
use MageDevGroup\SsoCore\Model\Oidc\TokenResponse;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class TokenClientTest extends TestCase
{
    private const ENDPOINT = 'https://dev.okta.com/oauth2/v1/token';
    private const REDIRECT = 'https://magento.loc/sso/callback';
    private const CLIENT_ID = 'client-abc';
    private const CODE = 'auth-code-123';
    private const VERIFIER = 'pkce-verifier-456';

    private function client(ClientInterface $http): TokenClient
    {
        return new TokenClient($http, new Json());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function body(array $overrides = []): string
    {
        return (string)json_encode(array_merge([
            'id_token' => 'header.payload.signature',
            'access_token' => 'at-789',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'scope' => 'openid profile',
        ], $overrides));
    }

    private function exchange(TokenClient $client, ?string $secret = null): TokenResponse
    {
        return $client->exchangeCode(
            self::ENDPOINT,
            self::CODE,
            self::REDIRECT,
            self::CLIENT_ID,
            self::VERIFIER,
            $secret
        );
    }

    public function testExchangesCodeAndParsesResponse(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects(self::once())
            ->method('post')
            ->with(self::ENDPOINT, self::callback(function (array $params): bool {
                return $params['grant_type'] === 'authorization_code'
                    && $params['code'] === self::CODE
                    && $params['redirect_uri'] === self::REDIRECT
                    && $params['client_id'] === self::CLIENT_ID
                    && $params['code_verifier'] === self::VERIFIER
                    && !array_key_exists('client_secret', $params);
            }));
        $http->method('getStatus')->willReturn(200);
        $http->method('getBody')->willReturn($this->body());

        $response = $this->exchange($this->client($http));

        self::assertSame('header.payload.signature', $response->getIdToken());
        self::assertSame('at-789', $response->getAccessToken());
        self::assertSame('Bearer', $response->getTokenType());
        self::assertSame(3600, $response->getExpiresIn());
        self::assertSame('openid profile', $response->getScope());
        self::assertNull($response->getRefreshToken());
    }

    public function testIncludesClientSecretWhenProvided(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects(self::once())
            ->method('post')
            ->with(self::ENDPOINT, self::callback(
                static fn(array $params): bool => ($params['client_secret'] ?? null) === 'shh'
            ));
        $http->method('getStatus')->willReturn(200);
        $http->method('getBody')->willReturn($this->body());

        $this->exchange($this->client($http), 'shh');
    }

    public function testThrowsWhenIdTokenMissing(): void
    {
        $http = $this->httpReturning(200, $this->body(['id_token' => '']));

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('id_token');

        $this->exchange($this->client($http));
    }

    public function testThrowsOnErrorStatusWithDescription(): void
    {
        $http = $this->httpReturning(400, (string)json_encode([
            'error' => 'invalid_grant',
            'error_description' => 'code already used',
        ]));

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('invalid_grant');

        $this->exchange($this->client($http));
    }

    public function testThrowsOnInvalidJson(): void
    {
        $http = $this->httpReturning(200, '<html>error</html>');

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('valid JSON');

        $this->exchange($this->client($http));
    }

    public function testThrowsWhenHttpClientFails(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('post')->willThrowException(new \RuntimeException('connection refused'));

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('connection refused');

        $this->exchange($this->client($http));
    }

    private function httpReturning(int $status, string $body): ClientInterface
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('getStatus')->willReturn($status);
        $http->method('getBody')->willReturn($body);

        return $http;
    }
}
