<?php
/**
 * Copyright © MageDevGroup. All rights reserved.
 */
declare(strict_types=1);

namespace MageDevGroup\SsoCore\Api\Data;

/**
 * Normalized identity produced by the OIDC engine from validated IdP claims.
 *
 * Provider-agnostic: presets map their claim shape onto this contract so every
 * SSO product consumes the same identity regardless of IdP.
 */
interface IdentityInterface
{
    /**
     * Stable, unique subject identifier from the IdP (the `sub` claim).
     */
    public function getSubjectId(): string;

    /**
     * Email address, or null when the IdP did not release it.
     */
    public function getEmail(): ?string;

    /**
     * Display name, or null when the IdP did not release it.
     */
    public function getName(): ?string;

    /**
     * Group memberships from the IdP; empty array when none.
     *
     * @return string[]
     */
    public function getGroups(): array;
}
