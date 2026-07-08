<?php
/**
 * Copyright © MageDevGroup. All rights reserved.
 */
declare(strict_types=1);

namespace MageDevGroup\SsoCore\Test\Unit\Model\Oidc;

use MageDevGroup\SsoCore\Exception\DiscoveryException;
use MageDevGroup\SsoCore\Model\Oidc\DiscoveryClient;
use MageDevGroup\SsoCore\Model\Oidc\ProviderMetadata;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class DiscoveryClientTest extends TestCase
{
    private const URL = 'https://dev.okta.com/.well-known/openid-configuration';

    /**
     * @param array<string, mixed> $overrides
     */
    private function document(array $overrides = []): string
    {
        return (string)json_encode(array_merge([
            'issuer' => 'https://dev.okta.com',
            'authorization_endpoint' => 'https://dev.okta.com/oauth2/v1/authorize',
            'token_endpoint' => 'https://dev.okta.com/oauth2/v1/token',
            'jwks_uri' => 'https://dev.okta.com/oauth2/v1/keys',
        ], $overrides));
    }

    private function client(ClientInterface $http, FrontendInterface $cache): DiscoveryClient
    {
        return new DiscoveryClient($http, $cache, new Json());
    }

    public function testFetchesParsesAndCachesOnMiss(): void
    {
        $body = $this->document();

        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->expects(self::once())
            ->method('save')
            ->with($body, self::isString(), [], 3600);

        $http = $this->createMock(ClientInterface::class);
        $http->expects(self::once())->method('get')->with(self::URL);
        $http->method('getStatus')->willReturn(200);
        $http->method('getBody')->willReturn($body);

        $metadata = $this->client($http, $cache)->discover(self::URL);

        self::assertInstanceOf(ProviderMetadata::class, $metadata);
        self::assertSame('https://dev.okta.com', $metadata->getIssuer());
        self::assertSame('https://dev.okta.com/oauth2/v1/authorize', $metadata->getAuthorizationEndpoint());
        self::assertSame('https://dev.okta.com/oauth2/v1/token', $metadata->getTokenEndpoint());
        self::assertSame('https://dev.okta.com/oauth2/v1/keys', $metadata->getJwksUri());
    }

    public function testUsesCacheOnHitWithoutHttpCall(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('load')->willReturn($this->document());
        $cache->expects(self::never())->method('save');

        $http = $this->createMock(ClientInterface::class);
        $http->expects(self::never())->method('get');

        $metadata = $this->client($http, $cache)->discover(self::URL);

        self::assertSame('https://dev.okta.com', $metadata->getIssuer());
        self::assertSame('https://dev.okta.com/oauth2/v1/keys', $metadata->getJwksUri());
    }

    public function testThrowsOnMissingEndpoint(): void
    {
        $http = $this->httpReturning($this->document(['token_endpoint' => '']));

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionMessage('token_endpoint');

        $this->client($http, $this->coldCache())->discover(self::URL);
    }

    public function testThrowsOnNonHttpsDiscoveryUrl(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects(self::never())->method('get');

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionMessage('must use HTTPS');

        $this->client($http, $this->coldCache())
            ->discover('http://dev.okta.com/.well-known/openid-configuration');
    }

    public function testThrowsOnNonHttpsEndpoint(): void
    {
        $http = $this->httpReturning($this->document([
            'token_endpoint' => 'http://dev.okta.com/oauth2/v1/token',
        ]));

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionMessage('token_endpoint must use HTTPS');

        $this->client($http, $this->coldCache())->discover(self::URL);
    }

    public function testThrowsOnInvalidJson(): void
    {
        $http = $this->httpReturning('<html>not json</html>');

        $this->expectException(DiscoveryException::class);

        $this->client($http, $this->coldCache())->discover(self::URL);
    }

    public function testThrowsOnNonJsonObject(): void
    {
        $http = $this->httpReturning('"a string"');

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionMessage('JSON object');

        $this->client($http, $this->coldCache())->discover(self::URL);
    }

    public function testThrowsOnHttpError(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('getStatus')->willReturn(404);

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionMessage('HTTP 404');

        $this->client($http, $this->coldCache())->discover(self::URL);
    }

    public function testThrowsWhenHttpClientFails(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('get')->willThrowException(new \RuntimeException('connection refused'));

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionMessage('connection refused');

        $this->client($http, $this->coldCache())->discover(self::URL);
    }

    private function httpReturning(string $body): ClientInterface
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('getStatus')->willReturn(200);
        $http->method('getBody')->willReturn($body);

        return $http;
    }

    private function coldCache(): FrontendInterface
    {
        $cache = $this->createStub(FrontendInterface::class);
        $cache->method('load')->willReturn(false);

        return $cache;
    }
}
