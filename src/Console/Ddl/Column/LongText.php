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

namespace Webware\Log\Console\Ddl\Column;

use PhpDb\Sql\Ddl\Column\Text;

/**
 * phpdb ships TEXT but no LONGTEXT; the log message and context can exceed 64 KB.
 *
 * @internal
 */
final class LongText extends Text
{
    protected string $type = 'LONGTEXT';
}
