<?php
/**
 * Copyright © DMLab. All rights reserved.
 */
declare(strict_types=1);

namespace DmLab\SsoCore\Test\Unit\Model\Oidc;

use DmLab\SsoCore\Api\ProviderPresetInterface;
use DmLab\SsoCore\Model\Oidc\IdentityFactory;
use PHPUnit\Framework\TestCase;

class IdentityFactoryTest extends TestCase
{
    /**
     * @var IdentityFactory
     */
    private IdentityFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new IdentityFactory();
    }

    private function preset(?string $groupsClaim = 'groups'): ProviderPresetInterface
    {
        return new class ($groupsClaim) implements ProviderPresetInterface {
            public function __construct(private readonly ?string $groupsClaim)
            {
            }

            public function getCode(): string
            {
                return 'okta';
            }

            public function getLabel(): string
            {
                return 'Okta';
            }

            public function buildDiscoveryUrl(array $config): string
            {
                return 'https://example.test/.well-known/openid-configuration';
            }

            public function getDefaultScopes(): array
            {
                return ['openid', 'profile', 'email'];
            }

            public function getGroupsClaim(): ?string
            {
                return $this->groupsClaim;
            }

            public function getButtonLabel(): string
            {
                return 'Sign in with Okta';
            }

            public function getButtonIconUrl(): ?string
            {
                return null;
            }
        };
    }

    public function testMapsAllClaims(): void
    {
        $identity = $this->factory->create(
            [
                'sub' => 'okta|123',
                'email' => 'jane@example.com',
                'name' => 'Jane Doe',
                'groups' => ['admins', 'staff'],
            ],
            $this->preset()
        );

        self::assertSame('okta|123', $identity->getSubjectId());
        self::assertSame('jane@example.com', $identity->getEmail());
        self::assertSame('Jane Doe', $identity->getName());
        self::assertSame(['admins', 'staff'], $identity->getGroups());
    }

    public function testEmailNameAndGroupsDefaultWhenAbsent(): void
    {
        $identity = $this->factory->create(['sub' => 'okta|123'], $this->preset());

        self::assertSame('okta|123', $identity->getSubjectId());
        self::assertNull($identity->getEmail());
        self::assertNull($identity->getName());
        self::assertSame([], $identity->getGroups());
    }

    public function testMissingSubjectIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->create(['email' => 'jane@example.com'], $this->preset());
    }

    public function testEmptySubjectIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->create(['sub' => ''], $this->preset());
    }

    public function testScalarGroupIsCoercedToStringArray(): void
    {
        $identity = $this->factory->create(
            ['sub' => 's', 'groups' => 'admins'],
            $this->preset()
        );

        self::assertSame(['admins'], $identity->getGroups());
    }

    public function testGroupValuesAreStringifiedAndReindexed(): void
    {
        $identity = $this->factory->create(
            ['sub' => 's', 'groups' => [3 => 42, 7 => 'staff']],
            $this->preset()
        );

        self::assertSame(['42', 'staff'], $identity->getGroups());
    }

    public function testNestedArrayGroupMembersAreSkipped(): void
    {
        $identity = $this->factory->create(
            ['sub' => 's', 'groups' => ['admins', ['nested'], 'staff']],
            $this->preset()
        );

        self::assertSame(['admins', 'staff'], $identity->getGroups());
    }

    public function testNullGroupsClaimYieldsEmptyGroups(): void
    {
        $identity = $this->factory->create(
            ['sub' => 's', 'groups' => ['admins']],
            $this->preset(null)
        );

        self::assertSame([], $identity->getGroups());
    }

    public function testGroupsClaimPointingToNullValueYieldsEmptyGroups(): void
    {
        $identity = $this->factory->create(
            ['sub' => 's', 'groups' => null],
            $this->preset()
        );

        self::assertSame([], $identity->getGroups());
    }

    public function testCustomGroupsClaimName(): void
    {
        $identity = $this->factory->create(
            ['sub' => 's', 'roles' => ['a', 'b'], 'groups' => ['ignored']],
            $this->preset('roles')
        );

        self::assertSame(['a', 'b'], $identity->getGroups());
    }
}
