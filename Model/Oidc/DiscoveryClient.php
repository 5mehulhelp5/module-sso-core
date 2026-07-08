<?php
/**
 * Copyright © MageDevGroup. All rights reserved.
 */
declare(strict_types=1);

namespace MageDevGroup\SsoCore\Model\Oidc;

use MageDevGroup\SsoCore\Exception\DiscoveryException;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Fetches and caches an IdP's `.well-known/openid-configuration` document and
 * exposes the endpoints the engine needs (issuer, authorization, token, JWKS).
 */
class DiscoveryClient
{
    private const CACHE_PREFIX = 'magedevgroup_ssocore_oidc_discovery_';

    /**
     * Discovery keys required to drive the OIDC flow.
     */
    private const REQUIRED_KEYS = [
        'issuer',
        'authorization_endpoint',
        'token_endpoint',
        'jwks_uri',
    ];

    /**
     * Endpoints the engine dereferences over HTTP; all must be HTTPS to prevent
     * SSRF and client-secret leakage from a spoofed or misconfigured document.
     */
    private const HTTPS_ENDPOINT_KEYS = [
        'authorization_endpoint',
        'token_endpoint',
        'jwks_uri',
    ];

    /**
     * Inject HTTP client, cache, serializer, and cache lifetime.
     *
     * @param ClientInterface $httpClient
     * @param FrontendInterface $cache
     * @param SerializerInterface $serializer
     * @param int $cacheLifetime
     */
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly FrontendInterface $cache,
        private readonly SerializerInterface $serializer,
        private readonly int $cacheLifetime = 3600
    ) {
    }

    /**
     * Resolve provider metadata for the given discovery URL, using the cache when warm.
     *
     * @param string $discoveryUrl
     * @throws DiscoveryException
     */
    public function discover(string $discoveryUrl): ProviderMetadata
    {
        $this->assertHttps($discoveryUrl, 'discovery URL');

        $cacheId = self::CACHE_PREFIX . hash('sha256', $discoveryUrl);

        $cached = $this->cache->load($cacheId);
        if (is_string($cached) && $cached !== '') {
            return $this->parse($cached);
        }

        $body = $this->fetch($discoveryUrl);
        $metadata = $this->parse($body);
        $this->cache->save($body, $cacheId, [], $this->cacheLifetime);

        return $metadata;
    }

    /**
     * Fetch the raw discovery document over HTTP.
     *
     * @param string $discoveryUrl
     * @throws DiscoveryException
     */
    private function fetch(string $discoveryUrl): string
    {
        try {
            $this->httpClient->get($discoveryUrl);
        } catch (\Throwable $e) {
            throw new DiscoveryException(
                'Failed to fetch OIDC discovery document: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $status = (int)$this->httpClient->getStatus();
        if ($status !== 200) {
            throw new DiscoveryException(
                sprintf('OIDC discovery request returned HTTP %d for "%s".', $status, $discoveryUrl)
            );
        }

        return (string)$this->httpClient->getBody();
    }

    /**
     * Parse and validate the discovery document into provider metadata.
     *
     * @param string $body
     * @throws DiscoveryException
     */
    private function parse(string $body): ProviderMetadata
    {
        try {
            $data = $this->serializer->unserialize($body);
        } catch (\Throwable $e) {
            throw new DiscoveryException('OIDC discovery document is not valid JSON.', 0, $e);
        }

        if (!is_array($data)) {
            throw new DiscoveryException('OIDC discovery document is not a JSON object.');
        }

        foreach (self::REQUIRED_KEYS as $key) {
            if (!isset($data[$key]) || !is_string($data[$key]) || $data[$key] === '') {
                throw new DiscoveryException(
                    sprintf('OIDC discovery document is missing "%s".', $key)
                );
            }
        }

        foreach (self::HTTPS_ENDPOINT_KEYS as $key) {
            $this->assertHttps($data[$key], $key);
        }

        return new ProviderMetadata(
            $data['issuer'],
            $data['authorization_endpoint'],
            $data['token_endpoint'],
            $data['jwks_uri']
        );
    }

    /**
     * Assert a URL uses the HTTPS scheme before it is dereferenced.
     *
     * @param string $url
     * @param string $label
     * @throws DiscoveryException
     */
    private function assertHttps(string $url, string $label): void
    {
        if (!str_starts_with(strtolower($url), 'https://')) {
            throw new DiscoveryException(
                sprintf('OIDC %s must use HTTPS: "%s".', $label, $url)
            );
        }
    }
}
