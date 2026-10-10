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

namespace Webware\Log\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Override;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Exception\ExceptionInterface;
use PhpDb\Sql\Sql;

use function array_diff_key;
use function array_filter;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class PhpDbHandler extends AbstractProcessingHandler
{
    private readonly Sql $sql;

    public function __construct(
        AdapterInterface $adapter,
        private readonly string $table,
        private readonly string $extraAuthIdentifier = 'email',
        protected bool $bubble = true,
    ) {
        parent::__construct();
        $this->sql = new Sql($adapter, $this->table);
    }

    /**
     * @throws ExceptionInterface
     */
    #[Override]
    protected function write(LogRecord $record): void
    {
        $context = array_filter([
            'context' => $record->context,
            'extra'   => array_diff_key($record->extra, ['uuid' => true, $this->extraAuthIdentifier => true]),
        ]);

        $insert = $this->sql->insert()
            ->values([
                'channel'         => $record->channel,
                'level'           => $record->level->getName(),
                'uuid'            => $record->extra['uuid'] ?? null,
                'message'         => $record->message,
                'time'            => $record->datetime->format('U'),
                'user_identifier' => $record->extra[$this->extraAuthIdentifier] ?? null,
                'context'         => [] !== $context
                    ? json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
            ]);
        $this->sql->prepareStatementForSqlObject($insert)->execute();
    }
}
