<?php
/**
 * Copyright © MageDevGroup. All rights reserved.
 */
declare(strict_types=1);

namespace MageDevGroup\SsoCore\Model\Oidc;

use MageDevGroup\SsoCore\Exception\IdTokenValidationException;
use Jose\Component\Core\JWKSet;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\HTTP\ClientInterface;

/**
 * Fetches and caches an IdP's JWKS document (`jwks_uri`) and returns it as a
 * `JWKSet` for signature verification.
 */
class JwksClient
{
    private const CACHE_PREFIX = 'magedevgroup_ssocore_oidc_jwks_';

    /**
     * Inject the HTTP client, cache, and cache lifetime.
     *
     * @param ClientInterface $httpClient
     * @param FrontendInterface $cache
     * @param int $cacheLifetime
     */
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly FrontendInterface $cache,
        private readonly int $cacheLifetime = 3600
    ) {
    }

    /**
     * Resolve the IdP JWKS as a key set, using the cache when warm.
     *
     * @param string $jwksUri
     * @throws IdTokenValidationException
     */
    public function getKeySet(string $jwksUri): JWKSet
    {
        $cacheId = self::CACHE_PREFIX . hash('sha256', $jwksUri);

        $cached = $this->cache->load($cacheId);
        if (is_string($cached) && $cached !== '') {
            return $this->parse($cached);
        }

        $body = $this->fetch($jwksUri);
        $keySet = $this->parse($body);
        $this->cache->save($body, $cacheId, [], $this->cacheLifetime);

        return $keySet;
    }

    /**
     * Fetch the raw JWKS document over HTTP.
     *
     * @param string $jwksUri
     * @throws IdTokenValidationException
     */
    private function fetch(string $jwksUri): string
    {
        try {
            $this->httpClient->get($jwksUri);
        } catch (\Throwable $e) {
            throw new IdTokenValidationException('Failed to fetch JWKS document: ' . $e->getMessage(), 0, $e);
        }

        $status = (int)$this->httpClient->getStatus();
        if ($status !== 200) {
            throw new IdTokenValidationException(
                sprintf('JWKS request returned HTTP %d for "%s".', $status, $jwksUri)
            );
        }

        return (string)$this->httpClient->getBody();
    }

    /**
     * Parse the JWKS document into a key set.
     *
     * @param string $body
     * @throws IdTokenValidationException
     */
    private function parse(string $body): JWKSet
    {
        try {
            return JWKSet::createFromJson($body);
        } catch (\Throwable $e) {
            throw new IdTokenValidationException('JWKS document is malformed.', 0, $e);
        }
    }
}
