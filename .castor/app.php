<?php

declare(strict_types=1);

// The application as a whole: its installation.

namespace app;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Install the dependencies and the Git hooks, start the stack and run the migrations')]
function install(): void
{
    run(['symfony', 'composer', 'install']);
    // Composer installs the hooks only with GrumPHP itself: not in a project created before its Git repository.
    run(['symfony', 'php', 'vendor/bin/grumphp', 'git:init']);

    // The first install chooses the ports, before the containers take them.
    if (! is_file('.env.local')) {
        \env\init();
    }

    \stack\start();
    run(['symfony', 'console', 'doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration']);
}
