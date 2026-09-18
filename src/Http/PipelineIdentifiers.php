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

namespace Webware\Log\Http;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The class-string identifiers the PSR-15 pipeline emits log events under.
 *
 * The identifiers are PSR-15 interface names, so the Http boundary owns them: a listener
 * aggregate can subscribe to these emitters without taking a PSR-15 dependency of its own.
 *
 * @internal
 */
final class PipelineIdentifiers
{
    /** @var list<class-string> */
    public const array ALL = [
        MiddlewareInterface::class,
        RequestHandlerInterface::class,
    ];
}
