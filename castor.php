<?php

declare(strict_types=1);

// The daily tasks of the project. Every command goes through the Symfony CLI,
// so that it uses the PHP of .php-version and the environment variables of
// the Docker services (DATABASE_URL, MAILER_DSN...).
define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Castor\Context;
use Symfony\Component\Process\Process;

use function Castor\io;
use function Castor\run;

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

#[AsTask(description: 'Install the dependencies and the Git hooks, start the stack and run the migrations')]
function install(): void
{
    run(['symfony', 'composer', 'install']);
    // Composer installs the hooks only with GrumPHP itself: not in a project created before its Git repository.
    run(['symfony', 'php', 'vendor/bin/grumphp', 'git:init']);
    start();
    run(['symfony', 'console', 'doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration']);
}

#[AsTask(description: 'Start the Docker services and the web server')]
function start(): void
{
    run(['docker', 'compose', 'up', '--detach', '--wait']);
    run(['symfony', 'server:start', '--daemon']);
    io()->success('The application is running: run "symfony open:local" to open it.');
}

#[AsTask(description: 'Stop the web server and the Docker services')]
function stop(): void
{
    run(['symfony', 'server:stop']);
    run(['docker', 'compose', 'stop']);
}

#[AsTask(name: 'reset', namespace: 'db', description: 'Empty and migrate the database, then load the fixtures')]
function db_reset(string $env = 'dev'): void
{
    $console = ['symfony', 'console', '--env=' . $env];

    run([...$console, 'doctrine:database:create', '--if-not-exists']);
    // Emptied rather than dropped: PostgreSQL refuses to drop a database that a worker is connected to.
    run([...$console, 'doctrine:schema:drop', '--full-database', '--force']);
    run([...$console, 'doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration']);
    // Appended: Foundry would build the schema again from the mapping, without the migrations.
    run([...$console, 'foundry:load-fixtures', '--append', '--no-interaction']);
}

#[AsTask(namespace: 'qa', description: 'Run all the static checks')]
function lint(): void
{
    run(['symfony', 'composer', 'lint']);
}

#[AsTask(namespace: 'qa', description: 'Apply the coding standard fixes')]
function fix(): void
{
    run(['symfony', 'composer', 'fix']);
}

#[AsTask(namespace: 'qa', description: 'Run the test suite')]
function test(): void
{
    run(['symfony', 'php', 'bin/phpunit']);
}
