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

namespace Webware\Log\Http\Middleware;

use Mezzio\Authentication\UserInterface;
use Monolog\Logger;
use Monolog\LogRecord;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Webware\Core\UserInterface as WebwareUserInterface;
use Webware\Log\Container\IdentityField;

class MonologMiddleware implements MiddlewareInterface
{
    /**
     * The request attribute keys the authenticated user is read from, most
     * preferred first.
     *
     * webware-core is a dev requirement rather than a runtime one: a consumer
     * that uses no other webware component never installs it, and the constant
     * resolves to the attribute key without loading the interface. A host maps
     * its own implementation onto one of these two contracts and attaches it
     * under that contract's name, so webware's wins when a request carries both.
     */
    private const array IDENTITY_ATTRIBUTES = [
        WebwareUserInterface::class,
        UserInterface::class,
    ];

    public function __construct(
        private LoggerInterface&Logger $logger,
        private readonly string $identityField = IdentityField::DEFAULT,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identity = $this->resolveIdentity($request);

        if (null !== $identity) {
            $field = $this->identityField;

            $this->logger->pushProcessor(static function (LogRecord $record) use ($identity, $field): LogRecord {
                $extra         = $record->extra;
                $extra[$field] = $identity;

                return $record->with(extra: $extra);
            });
        }

        // attach the logger to the request
        $request = $request->withAttribute(LoggerInterface::class, $this->logger);

        return $handler->handle($request);
    }

    /**
     * Resolve the authenticated user's identity string, preferring webware's
     * contract over mezzio's when a host has attached both.
     */
    private function resolveIdentity(ServerRequestInterface $request): ?string
    {
        foreach (self::IDENTITY_ATTRIBUTES as $attribute) {
            /** @var UserInterface|null $user */
            $user = $request->getAttribute($attribute);

            if ($user instanceof UserInterface) {
                return $user->getIdentity();
            }
        }

        return null;
    }
}
