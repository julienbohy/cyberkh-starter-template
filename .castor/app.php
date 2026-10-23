<?php

declare(strict_types=1);

// The application as a whole: its installation, and its console and Composer, in the php container.

namespace app;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\run;
use function stack\php;

#[AsTask(description: 'Choose the ports, start the stack, install the dependencies and run the migrations')]
function install(): void
{
    // The first install chooses the ports, before the containers take them.
    if (! is_file('.env.local')) {
        \env\init();
    }

    \stack\start();
    run(php('composer', 'install', '--no-interaction'));
    run(php('php', 'bin/console', 'doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration'));
}

/**
 * @param list<string> $arguments
 */
#[AsTask(description: 'Run a command of the Symfony console, e.g. castor app:console debug:router')]
function console(#[AsRawTokens] array $arguments = []): void
{
    run(php('php', 'bin/console', ...$arguments));
}

/**
 * @param list<string> $arguments
 */
#[AsTask(description: 'Run Composer, e.g. castor app:composer require symfony/lock')]
function composer(#[AsRawTokens] array $arguments = []): void
{
    run(php('composer', ...$arguments));
}
