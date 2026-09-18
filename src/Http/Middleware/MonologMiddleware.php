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

class MonologMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LoggerInterface&Logger $logger,
        private readonly string $authAttribute = UserInterface::class,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var UserInterface|null */
        $userInterface = $request->getAttribute($this->authAttribute);

        if ($userInterface instanceof UserInterface) {
            $this->logger->pushProcessor(static function (LogRecord $record) use ($userInterface): LogRecord {
                $extra          = $record->extra;
                $extra['email'] = $userInterface->getIdentity();

                return $record->with(extra: $extra);
            });
        }

        // attach the logger to the request
        $request = $request->withAttribute(LoggerInterface::class, $this->logger);

        return $handler->handle($request);
    }
}
