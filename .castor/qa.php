<?php

declare(strict_types=1);

// The quality checks: the same as the CI and the Git hooks run.

namespace qa;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Run all the static checks')]
function lint(): void
{
    run(['symfony', 'composer', 'lint']);
}

#[AsTask(description: 'Apply the coding standard fixes')]
function fix(): void
{
    run(['symfony', 'composer', 'fix']);
}

#[AsTask(description: 'Run the test suite')]
function test(): void
{
    run(['symfony', 'php', 'bin/phpunit']);
}
