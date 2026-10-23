<?php

declare(strict_types=1);

// The running stack: the Docker services of compose.yaml and compose.override.yaml.

namespace stack;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\run;
use function env\current_port;

#[AsTask(description: 'Build the images if needed, then start the Docker services')]
function start(): void
{
    run(docker_compose('up', '--detach', '--wait', '--build'));
    io()->success(\sprintf('The application is running at https://localhost:%d (Mailpit: http://localhost:%d).', current_port('HTTPS_PORT'), current_port('MAILER_WEB_PORT')));
}

#[AsTask(description: 'Stop the Docker services')]
function stop(): void
{
    run(docker_compose('stop'));
}

#[AsTask(description: 'Make macOS trust the local HTTPS certificates of Caddy (asks for your password)')]
function trust(): void
{
    $certificate = 'var/caddy-root.crt';
    run(docker_compose('cp', 'php:/data/caddy/pki/authorities/local/root.crt', $certificate));
    run(['sudo', 'security', 'add-trusted-cert', '-d', '-r', 'trustRoot', '-k', '/Library/Keychains/System.keychain', $certificate]);
    io()->success('macOS trusts the certificates of https://localhost now: restart your browser.');
}

/**
 * A command run in the php container, where the application lives.
 *
 * @return list<string>
 */
function php(string ...$command): array
{
    // Without a terminal (a script, the CI), Docker must not allocate one.
    $tty = stream_isatty(\STDIN) ? [] : ['-T'];

    return docker_compose('exec', ...$tty, ...['php'], ...array_values($command));
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
