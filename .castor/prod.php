<?php

declare(strict_types=1);

// The production, on the server: the services of compose.yaml and compose.prod.yaml, and of
// compose.tunnel.yaml behind a Cloudflare Tunnel. Their configuration is in .env.prod.local.

namespace prod;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Symfony\Component\Dotenv\Dotenv;

use function Castor\io;
use function Castor\run;

/**
 * The configuration of the production, not committed and not in the image: the environment of the
 * host wins over it.
 */
function env_file(): string
{
    return '.env.prod.local';
}

#[AsTask(description: 'Set the domain, the secrets, the emails and the Cloudflare Tunnel of the production in .env.prod.local')]
function init(): void
{
    $values = env_values();

    $configuration = [
        'SERVER_NAME' => ask('Domain of the application', $values['SERVER_NAME'] ?? null),
        // Generated once: a new one would log out the users, or lose the database.
        'APP_SECRET' => $values['APP_SECRET'] ?? bin2hex(random_bytes(16)),
        'POSTGRES_PASSWORD' => $values['POSTGRES_PASSWORD'] ?? bin2hex(random_bytes(16)),
        'MAILER_DSN' => ask('Mailer DSN (e.g. smtp://user:password@smtp.example.com:587)', $values['MAILER_DSN'] ?? null),
        'MAILER_FROM' => ask('Sender of the emails', $values['MAILER_FROM'] ?? null),
        'CLOUDFLARE_TUNNEL_TOKEN' => '',
    ];

    $token = $values['CLOUDFLARE_TUNNEL_TOKEN'] ?? '';
    if (io()->confirm('Behind a Cloudflare Tunnel (no open port, HTTPS by Cloudflare)?', $token !== '')) {
        $answer = io()->askHidden('Token of the tunnel' . ($token !== '' ? ' (empty: keep the current one)' : ''), static function (mixed $answer) use ($token): string {
            $answer = \is_string($answer) ? trim($answer) : '';
            if ($answer === '' && $token === '') {
                throw new \InvalidArgumentException('The token is in the Zero Trust dashboard: Networks > Tunnels.');
            }

            return $answer;
        });
        $configuration['CLOUDFLARE_TUNNEL_TOKEN'] = \is_string($answer) && $answer !== '' ? $answer : $token;
    }

    write_env_file($configuration);
    io()->success(\sprintf('The configuration is in %s: run "castor prod:start".', env_file()));
    if ($configuration['CLOUDFLARE_TUNNEL_TOKEN'] !== '') {
        io()->note(\sprintf('In the Zero Trust dashboard, the public hostname %s of the tunnel goes to http://php:80.', $configuration['SERVER_NAME']));
    }
}

#[AsTask(description: 'Build the prod image, run the migrations, then start the services (behind the Cloudflare Tunnel when it has a token)')]
function start(): void
{
    // The migrations service runs them, before the application and the worker start.
    run(docker_compose('up', '--detach', '--wait', '--build'));

    $domain = env_values()['SERVER_NAME'] ?? 'localhost';
    io()->success(\sprintf('The application is running at https://%s%s.', $domain, is_tunnel() ? ', through the Cloudflare Tunnel' : ''));
}

#[AsTask(description: 'Stop the services of the production')]
function stop(): void
{
    run(docker_compose('stop'));
}

/**
 * @param list<string> $arguments
 */
#[AsTask(description: 'Run a command of the Symfony console in production, e.g. castor prod:console cache:pool:clear --all')]
function console(#[AsRawTokens] array $arguments = []): void
{
    $tty = stream_isatty(\STDIN) ? [] : ['-T'];
    run(docker_compose('exec', ...$tty, ...['php', 'bin/console'], ...$arguments));
}

/**
 * A Docker Compose command of the production. With .env.prod.local, Compose reads it instead of
 * .env, whose defaults are the ones of the development (e.g. MAILER_DSN=null://null).
 * Its own project, with its own containers and volumes: on a checkout that runs the development
 * too, the production would take its containers, and its database whose password is another one.
 *
 * @return list<string>
 */
function docker_compose(string ...$arguments): array
{
    // The name that Compose gives to the project of the directory, followed by -prod.
    $project = trim((string) preg_replace('/[^a-z0-9_-]/', '', strtolower(basename((string) getcwd()))), '_-') . '-prod';
    $command = ['docker', 'compose', '--project-name', $project, '-f', 'compose.yaml', '-f', 'compose.prod.yaml'];
    if (is_tunnel()) {
        $command[] = '-f';
        $command[] = 'compose.tunnel.yaml';
    }

    if (is_file(env_file())) {
        $command[] = '--env-file';
        $command[] = env_file();
    }

    return [...$command, ...array_values($arguments)];
}

/**
 * Whether the production is behind a Cloudflare Tunnel: it has the token of one.
 */
function is_tunnel(): bool
{
    $token = getenv('CLOUDFLARE_TUNNEL_TOKEN');

    return ($token !== false && $token !== '') || (env_values()['CLOUDFLARE_TUNNEL_TOKEN'] ?? '') !== '';
}

/**
 * The values of .env.prod.local.
 *
 * @return array<string, string>
 */
function env_values(): array
{
    if (! is_file(env_file())) {
        return [];
    }

    $values = [];
    foreach ((new Dotenv())->parse((string) file_get_contents(env_file()), env_file()) as $variable => $value) {
        $values[(string) $variable] = \is_scalar($value) ? (string) $value : '';
    }

    return $values;
}

function ask(string $question, ?string $default): string
{
    $answer = io()->ask($question, $default, static function (mixed $answer): string {
        $answer = \is_string($answer) ? trim($answer) : '';
        if ($answer === '') {
            throw new \InvalidArgumentException('A value is required.');
        }

        if (str_contains($answer, "'")) {
            throw new \InvalidArgumentException('A value cannot contain a single quote.');
        }

        return $answer;
    });

    return \is_string($answer) ? $answer : '';
}

/**
 * Sets $values in .env.prod.local, and keeps its other lines (e.g. the S3 storage).
 *
 * @param array<string, string> $values
 */
function write_env_file(array $values): void
{
    $content = is_file(env_file()) ? (string) file_get_contents(env_file()) : "# The configuration of the production, not committed (castor prod:init).\n";
    foreach ($values as $variable => $value) {
        // Quoted when needed, literally: Compose and Symfony read the single quotes the same way.
        $line = $variable . '=' . (preg_match('#^[\w.:/@%+-]*$#', $value) === 1 ? $value : "'" . $value . "'");
        $content = preg_match('/^' . $variable . '=.*$/m', $content) === 1
            ? (string) preg_replace_callback('/^' . $variable . '=.*$/m', static fn(): string => $line, $content)
            : rtrim($content, "\n") . "\n" . $line . "\n";
    }

    file_put_contents(env_file(), $content);
    chmod(env_file(), 0o600);
}
