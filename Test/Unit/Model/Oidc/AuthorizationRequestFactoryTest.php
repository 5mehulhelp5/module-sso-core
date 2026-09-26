<?php
/**
 * Copyright © DMLab. All rights reserved.
 */
declare(strict_types=1);

namespace DmLab\SsoCore\Test\Unit\Model\Oidc;

use DmLab\SsoCore\Model\Oidc\AuthorizationRequest;
use DmLab\SsoCore\Model\Oidc\AuthorizationRequestFactory;
use Magento\Framework\Math\Random;
use PHPUnit\Framework\TestCase;

class AuthorizationRequestFactoryTest extends TestCase
{
    private const ENDPOINT = 'https://dev.okta.com/oauth2/v1/authorize';
    private const CLIENT_ID = 'client-123';
    private const REDIRECT = 'https://magento.loc/sso/callback';

    private function build(?Random $random = null): AuthorizationRequest
    {
        $factory = new AuthorizationRequestFactory($random ?? new Random());

        return $factory->create(
            self::ENDPOINT,
            self::CLIENT_ID,
            self::REDIRECT,
            ['openid', 'profile', 'email']
        );
    }

    /**
     * @return array<string, string>
     */
    private function queryParams(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str((string)$query, $params);

        /** @var array<string, string> $params */
        return $params;
    }

    public function testUrlKeepsEndpointAndCarriesRequiredParams(): void
    {
        $request = $this->build();

        self::assertStringStartsWith(self::ENDPOINT . '?', $request->getUrl());

        $params = $this->queryParams($request->getUrl());

        self::assertSame('code', $params['response_type']);
        self::assertSame(self::CLIENT_ID, $params['client_id']);
        self::assertSame(self::REDIRECT, $params['redirect_uri']);
        self::assertSame('openid profile email', $params['scope']);
        self::assertArrayHasKey('state', $params);
        self::assertArrayHasKey('nonce', $params);
    }

    public function testUsesPkceS256ChallengeDerivedFromVerifier(): void
    {
        $request = $this->build();
        $params = $this->queryParams($request->getUrl());

        self::assertSame('S256', $params['code_challenge_method']);

        $expectedChallenge = rtrim(
            strtr(base64_encode(hash('sha256', $request->getCodeVerifier(), true)), '+/', '-_'),
            '='
        );
        self::assertSame($expectedChallenge, $params['code_challenge']);
    }

    public function testVerifierIsUrlSafeAndWithinPkceLength(): void
    {
        $verifier = $this->build()->getCodeVerifier();

        self::assertGreaterThanOrEqual(43, strlen($verifier));
        self::assertLessThanOrEqual(128, strlen($verifier));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $verifier);
    }

    public function testStateNonceAndVerifierAreDistinctAndRandom(): void
    {
        $first = $this->build();
        $second = $this->build();

        // Distinct within one request.
        self::assertNotSame($first->getState(), $first->getNonce());
        self::assertNotSame($first->getState(), $first->getCodeVerifier());
        self::assertNotSame($first->getNonce(), $first->getCodeVerifier());

        // Distinct across requests (random per call).
        self::assertNotSame($first->getState(), $second->getState());
        self::assertNotSame($first->getNonce(), $second->getNonce());
        self::assertNotSame($first->getCodeVerifier(), $second->getCodeVerifier());
    }

    public function testReturnedStateMatchesUrlParams(): void
    {
        $request = $this->build();
        $params = $this->queryParams($request->getUrl());

        self::assertSame($request->getState(), $params['state']);
        self::assertSame($request->getNonce(), $params['nonce']);
    }

    public function testAppendsToEndpointThatAlreadyHasQuery(): void
    {
        $factory = new AuthorizationRequestFactory(new Random());
        $request = $factory->create(
            self::ENDPOINT . '?foo=bar',
            self::CLIENT_ID,
            self::REDIRECT,
            ['openid']
        );

        $params = $this->queryParams($request->getUrl());

        self::assertStringContainsString('?foo=bar&', $request->getUrl());
        self::assertSame('bar', $params['foo']);
        self::assertSame('openid', $params['scope']);
    }
}
