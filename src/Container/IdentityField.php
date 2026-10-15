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

namespace Webware\Log\Container;

use function is_array;
use function is_string;

/**
 * The name of the field carrying the authenticated user's identity.
 *
 * The producer and the consumer of that name have to agree: MonologMiddleware
 * writes the identity into a log record's `extra` under this name, and
 * PhpDbHandler reads it back out of `extra` under the same one to fill the
 * `user_identifier` column. Both resolve it here, so a host that renames its
 * identity column cannot leave the two halves disagreeing.
 *
 * The value is read from Mezzio's authentication configuration
 * (`authentication.username`), which is also the key a host points at its
 * identity column, so changing the column changes both at once.
 */
final class IdentityField
{
    /**
     * Used when the host configures no identity field. A host with no
     * authentication configuration therefore logs exactly as it always has.
     */
    public const string DEFAULT = 'email';

    private const string AUTHENTICATION_KEY = 'authentication';

    private const string USERNAME_KEY = 'username';

    /**
     * @param array<array-key, mixed> $config the merged application config
     */
    public static function resolve(array $config): string
    {
        if (! is_array($config[self::AUTHENTICATION_KEY] ?? null)) {
            return self::DEFAULT;
        }

        return self::fromAuthentication($config[self::AUTHENTICATION_KEY]);
    }

    /**
     * @param array<array-key, mixed> $authentication
     */
    private static function fromAuthentication(array $authentication): string
    {
        /** @var mixed $value */
        $value = $authentication[self::USERNAME_KEY] ?? null;

        return is_string($value) && '' !== $value ? $value : self::DEFAULT;
    }
}
