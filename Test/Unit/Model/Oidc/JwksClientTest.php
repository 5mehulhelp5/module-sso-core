<?php
/**
 * Copyright © DMLab. All rights reserved.
 */
declare(strict_types=1);

namespace DmLab\SsoCore\Test\Unit\Model\Oidc;

use DmLab\SsoCore\Exception\IdTokenValidationException;
use DmLab\SsoCore\Model\Oidc\JwksClient;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\HTTP\ClientInterface;
use PHPUnit\Framework\TestCase;

class JwksClientTest extends TestCase
{
    private const URI = 'https://dev.okta.com/oauth2/v1/keys';

    /**
     * @var string
     */
    private string $jwksJson;

    protected function setUp(): void
    {
        $key = JWKFactory::createRSAKey(2048, ['alg' => 'RS256', 'use' => 'sig', 'kid' => 'k1'])->toPublic();
        $this->jwksJson = (string)json_encode(new JWKSet([$key]));
    }

    public function testFetchesParsesAndCachesOnMiss(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->expects(self::once())
            ->method('save')
            ->with($this->jwksJson, self::isString(), [], 3600);

        $http = $this->createMock(ClientInterface::class);
        $http->expects(self::once())->method('get')->with(self::URI);
        $http->method('getStatus')->willReturn(200);
        $http->method('getBody')->willReturn($this->jwksJson);

        $keySet = (new JwksClient($http, $cache))->getKeySet(self::URI);

        self::assertInstanceOf(JWKSet::class, $keySet);
        self::assertCount(1, $keySet);
    }

    public function testUsesCacheOnHitWithoutHttpCall(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('load')->willReturn($this->jwksJson);
        $cache->expects(self::never())->method('save');

        $http = $this->createMock(ClientInterface::class);
        $http->expects(self::never())->method('get');

        $keySet = (new JwksClient($http, $cache))->getKeySet(self::URI);

        self::assertCount(1, $keySet);
    }

    public function testThrowsOnHttpError(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('getStatus')->willReturn(500);

        $this->expectException(IdTokenValidationException::class);
        $this->expectExceptionMessage('HTTP 500');

        (new JwksClient($http, $this->coldCache()))->getKeySet(self::URI);
    }

    public function testThrowsOnMalformedJwks(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('getStatus')->willReturn(200);
        $http->method('getBody')->willReturn('not json');

        $this->expectException(IdTokenValidationException::class);
        $this->expectExceptionMessage('malformed');

        (new JwksClient($http, $this->coldCache()))->getKeySet(self::URI);
    }

    public function testThrowsWhenHttpClientFails(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('get')->willThrowException(new \RuntimeException('timeout'));

        $this->expectException(IdTokenValidationException::class);
        $this->expectExceptionMessage('timeout');

        (new JwksClient($http, $this->coldCache()))->getKeySet(self::URI);
    }

    private function coldCache(): FrontendInterface
    {
        $cache = $this->createStub(FrontendInterface::class);
        $cache->method('load')->willReturn(false);

        return $cache;
    }
}
