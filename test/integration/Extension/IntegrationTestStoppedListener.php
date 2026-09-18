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

namespace WebwareTestIntegration\Log\Extension;

use Override;
use PDOException;
use PHPUnit\Event\TestSuite\Finished;
use PHPUnit\Event\TestSuite\FinishedSubscriber;
use WebwareTestIntegration\Log\FixtureLoader\MysqlFixtureLoader;

final class IntegrationTestStoppedListener implements FinishedSubscriber
{
    /** @var list<MysqlFixtureLoader> */
    private array $fixtureLoaders = [];

    /**
     * @throws PDOException
     */
    #[Override]
    public function notify(Finished $event): void
    {
        if (
            $event->testSuite()->name() !== 'integration test'
            || empty($this->fixtureLoaders)
        ) {
            return;
        }

        print "\nIntegration test ended.\n";

        foreach ($this->fixtureLoaders as $fixtureLoader) {
            $fixtureLoader->dropDatabase();
        }
    }
}
