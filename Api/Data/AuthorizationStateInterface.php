<?php
/**
 * Copyright © MageDevGroup. All rights reserved.
 */
declare(strict_types=1);

namespace MageDevGroup\SsoCore\Api\Data;

/**
 * One-time authorization state the consumer must persist between the redirect to
 * the IdP and the callback: `state` (CSRF), `nonce` (replay), and the PKCE
 * `code_verifier` needed to redeem the authorization code at the token endpoint.
 */
interface AuthorizationStateInterface
{
    /**
     * Opaque anti-CSRF token echoed back by the IdP on the callback.
     */
    public function getState(): string;

    /**
     * Anti-replay value bound into the ID token and checked on the callback.
     */
    public function getNonce(): string;

    /**
     * PKCE `code_verifier` proving the client that started the flow completes it.
     */
    public function getCodeVerifier(): string;
}
