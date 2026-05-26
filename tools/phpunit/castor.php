<?php

declare(strict_types=1);

namespace Darkwood\IaExceptionBundle\Tools;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Launch PHPUnit test suite', aliases: ['phpunit'])]
function phpunit(): int
{
    return run(
        [__DIR__ . '/vendor/bin/phpunit', '--configuration=' . __DIR__ . '/phpunit.xml'],
        context()->withAllowFailure(),
    )->getExitCode();
}
