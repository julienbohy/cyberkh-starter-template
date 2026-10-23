<?php

declare(strict_types=1);

// The shared setup of the Castor tasks, which are in .castor/, one file per prefix: app, stack,
// env, db and qa. Every command goes through the Symfony CLI, so that it uses the PHP of
// .php-version and the environment variables of the Docker services (DATABASE_URL, MAILER_DSN...).
define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsContext;
use Castor\Context;
use Symfony\Component\Process\Process;

use function Castor\import;

import(__DIR__ . '/.castor');

#[AsContext(default: true)]
function default_context(): Context
{
    return new Context(environment: docker_host());
}

/**
 * The socket of the active Docker context (Docker Desktop, OrbStack, Colima...), unless DOCKER_HOST
 * is set: the Symfony CLI only looks at the default socket, and without Docker it does not expose
 * the variables of the services.
 *
 * @return array<string, string>
 */
function docker_host(): array
{
    if (getenv('DOCKER_HOST') !== false) {
        return [];
    }

    $process = new Process(['docker', 'context', 'inspect', '--format', '{{.Endpoints.docker.Host}}']);
    $process->run();
    $host = trim($process->getOutput());

    return $process->isSuccessful() && $host !== '' ? [
        'DOCKER_HOST' => $host,
    ] : [];
}
