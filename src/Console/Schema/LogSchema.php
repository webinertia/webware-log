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

namespace Webware\Log\Console\Schema;

use PhpDb\Sql\Ddl\Column\Char;
use PhpDb\Sql\Ddl\Column\Integer;
use PhpDb\Sql\Ddl\Column\Varchar;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\Constraint\UniqueKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;
use PhpDb\Sql\Ddl\Index\Index;
use PhpDb\Sql\Literal;
use Webware\Log\Console\Ddl\Column\LongText;

/**
 * Builds the table {@see \Webware\Log\Handler\PhpDbHandler} writes to.
 *
 * The columns are exactly the ones the handler inserts: channel, level, uuid, message, time,
 * user_identifier and context. The table name is the caller's, because the handler takes it from
 * configuration and the two must agree.
 *
 * @internal
 */
final class LogSchema
{
    public function dropTable(string $table): DropTable
    {
        return new DropTable(table: $table)->ifExists();
    }

    public function logTable(string $table): CreateTable
    {
        $createTable = new CreateTable(table: $table)->ifNotExists();

        $createTable->addColumn(
            new Integer(
                name    : 'id',
                nullable: false,
            )->setOptions(options: ['unsigned' => true, 'autoincrement' => true]),
        );
        $createTable->addColumn(new Char(
            name    : 'uuid',
            length  : 36,
            nullable: true,
            default : null,
        ));
        $createTable->addColumn(new Varchar(
            name    : 'channel',
            length  : 255,
            nullable: false,
        ));
        $createTable->addColumn(new Varchar(
            name    : 'level',
            length  : 9,
            nullable: false,
        ));
        $createTable->addColumn(new Varchar(
            name    : 'user_identifier',
            length  : 320,
            nullable: true,
            default : null,
        ));
        $createTable->addColumn(new LongText(
            name    : 'message',
            nullable: false,
        ));
        $createTable->addColumn(
            new Integer(
                name    : 'time',
                nullable: false,
            )->setOptions(options: ['unsigned' => true]),
        );
        $createTable->addColumn(new LongText(
            name    : 'context',
            nullable: true,
            default : null,
        ));

        $createTable->addConstraint(new PrimaryKey(columns: 'id'));
        $createTable->addConstraint(new UniqueKey(
            columns: 'uuid',
            name   : 'uuid',
        ));
        $createTable->addConstraint(new Index(
            columns: 'channel',
            name   : 'ChannelIndex',
        ));

        $createTable->setOptions(options: [
            'engine'          => new Literal(literal: 'InnoDB'),
            'default charset' => new Literal(literal: 'utf8mb4'),
            'collate'         => new Literal(literal: 'utf8mb4_general_ci'),
        ]);

        return $createTable;
    }
}
