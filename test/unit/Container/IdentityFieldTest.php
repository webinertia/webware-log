<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Log package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WebwareTest\Log\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Log\Container\IdentityField;

#[CoversClass(IdentityField::class)]
#[CoversMethod(IdentityField::class, 'resolve')]
final class IdentityFieldTest extends TestCase
{
    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function resolveDefaultsToEmailWhenAuthenticationIsAbsent(): void
    {
        self::assertSame('email', IdentityField::resolve([]));
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function resolveDefaultsToEmailWhenAuthenticationIsNotAnArray(): void
    {
        self::assertSame('email', IdentityField::resolve(['authentication' => 'nope']));
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function resolveDefaultsToEmailWhenUsernameIsEmpty(): void
    {
        self::assertSame('email', IdentityField::resolve(['authentication' => ['username' => '']]));
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function resolveDefaultsToEmailWhenUsernameIsMissing(): void
    {
        self::assertSame('email', IdentityField::resolve(['authentication' => ['redirect' => '/login']]));
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function resolveDefaultsToEmailWhenUsernameIsNotAString(): void
    {
        self::assertSame('email', IdentityField::resolve(['authentication' => ['username' => 42]]));
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function resolveReturnsConfiguredUsername(): void
    {
        self::assertSame('user_name', IdentityField::resolve(['authentication' => ['username' => 'user_name']]));
    }
}
