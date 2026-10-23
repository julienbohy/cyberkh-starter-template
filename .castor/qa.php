<?php

declare(strict_types=1);

// The quality checks, in the php container: the same as the CI runs.

namespace qa;

use Castor\Attribute\AsTask;

use function Castor\run;
use function stack\php;

#[AsTask(description: 'Run all the static checks')]
function lint(): void
{
    run(php('composer', 'lint'));
}

#[AsTask(description: 'Apply the coding standard fixes')]
function fix(): void
{
    run(php('composer', 'fix'));
}

#[AsTask(description: 'Run the test suite')]
function test(): void
{
    run(php('php', 'bin/phpunit'));
}
