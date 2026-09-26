<?php
/**
 * Copyright © DMLab. All rights reserved.
 */
declare(strict_types=1);

namespace DmLab\SsoCore\Model\Data;

use DmLab\SsoCore\Api\Data\IdentityInterface;

/**
 * Immutable value object carrying a normalized identity.
 */
class Identity implements IdentityInterface
{
    /**
     * @var string[]
     */
    private array $groups;

    /**
     * Build the identity value object.
     *
     * @param string $subjectId
     * @param string|null $email
     * @param string|null $name
     * @param string[] $groups
     */
    public function __construct(
        private readonly string $subjectId,
        private readonly ?string $email = null,
        private readonly ?string $name = null,
        array $groups = []
    ) {
        $this->groups = array_values(array_map('strval', $groups));
    }

    /**
     * Get the subject id.
     */
    public function getSubjectId(): string
    {
        return $this->subjectId;
    }

    /**
     * Get the email address, if present.
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Get the display name, if present.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get the group memberships.
     *
     * @return string[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
}
