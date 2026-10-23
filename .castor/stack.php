<?php

declare(strict_types=1);

// The running stack: the Docker services (compose.yaml) and the web server of the Symfony CLI.

namespace stack;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\run;

#[AsTask(description: 'Start the Docker services and the web server')]
function start(): void
{
    run(docker_compose('up', '--detach', '--wait'));
    run(['symfony', 'server:start', '--daemon']);
    io()->success('The application is running: run "symfony open:local" to open it.');
}

#[AsTask(description: 'Stop the web server and the Docker services')]
function stop(): void
{
    run(['symfony', 'server:stop']);
    run(docker_compose('stop'));
}

/**
 * A Docker Compose command that reads the .env files of the dev environment in the order of
 * Symfony, the last one winning: Compose alone reads .env only, not .env.local.
 *
 * @return list<string>
 */
function docker_compose(string ...$arguments): array
{
    $command = ['docker', 'compose'];
    foreach (['.env', '.env.local', '.env.dev', '.env.dev.local'] as $file) {
        if (is_file($file)) {
            $command[] = '--env-file';
            $command[] = $file;
        }
    }

    return [...$command, ...array_values($arguments)];
}
