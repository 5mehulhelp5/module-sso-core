<?php
/**
 * Copyright © MageDevGroup. All rights reserved.
 */
declare(strict_types=1);

namespace MageDevGroup\SsoCore\Model\Oidc;

use Magento\Framework\Math\Random;

/**
 * Builds an authorization-code + PKCE authorization request: generates `state`,
 * `nonce`, and the PKCE verifier/challenge (`S256`), then assembles the redirect
 * URL from the discovered authorization endpoint and the preset's scopes.
 */
class AuthorizationRequestFactory
{
    private const RESPONSE_TYPE = 'code';
    private const CODE_CHALLENGE_METHOD = 'S256';

    /**
     * 32 random bytes → 43-char base64url string, within the PKCE verifier range
     * (43–128 chars) and giving ample entropy for `state`/`nonce`.
     */
    private const RANDOM_BYTES = 32;

    /**
     * Inject the secure random generator.
     *
     * @param Random $random
     */
    public function __construct(
        private readonly Random $random
    ) {
    }

    /**
     * Build an authorization-code + PKCE authorization request.
     *
     * @param string $authorizationEndpoint
     * @param string $clientId
     * @param string $redirectUri
     * @param string[] $scopes
     */
    public function create(
        string $authorizationEndpoint,
        string $clientId,
        string $redirectUri,
        array $scopes
    ): AuthorizationRequest {
        $state = $this->randomToken();
        $nonce = $this->randomToken();
        $codeVerifier = $this->randomToken();

        $params = [
            'response_type' => self::RESPONSE_TYPE,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $this->deriveChallenge($codeVerifier),
            'code_challenge_method' => self::CODE_CHALLENGE_METHOD,
        ];

        $separator = str_contains($authorizationEndpoint, '?') ? '&' : '?';
        $url = $authorizationEndpoint . $separator
            . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return new AuthorizationRequest($url, $state, $nonce, $codeVerifier);
    }

    /**
     * Derive the S256 PKCE code challenge from the verifier.
     *
     * @param string $codeVerifier
     */
    private function deriveChallenge(string $codeVerifier): string
    {
        return $this->base64UrlEncode(hash('sha256', $codeVerifier, true));
    }

    /**
     * Generate a base64url random token.
     */
    private function randomToken(): string
    {
        return $this->base64UrlEncode($this->random->getRandomBytes(self::RANDOM_BYTES));
    }

    /**
     * Base64url-encode the given bytes without padding.
     *
     * @param string $bytes
     */
    private function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
