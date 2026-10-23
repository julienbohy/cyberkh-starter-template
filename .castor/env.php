<?php

declare(strict_types=1);

// The local configuration of the checkout: .env.local, which is not committed.

namespace env;

use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\context;
use function Castor\io;
use function stack\docker_compose;

/**
 * The host ports of the Docker services (compose.override.yaml): variable => service.
 *
 * @return array<string, string>
 */
function ports(): array
{
    return [
        'HTTPS_PORT' => 'the application (HTTPS)',
        'DATABASE_PORT' => 'PostgreSQL',
        'MAILER_WEB_PORT' => 'Mailpit',
    ];
}

#[AsTask(description: 'Set the ports of the Docker services in .env.local, proposing free ones')]
function init(): void
{
    $own = published_ports(docker_compose('ps', '--format', '{{.Ports}}'));
    $published = published_ports(['docker', 'ps', '--format', '{{.Ports}}']);

    $chosen = [];
    foreach (ports() as $variable => $service) {
        // The port in use, unless another project or program has it: then the next free one.
        $port = current_port($variable);
        while (\in_array($port, $chosen, true) || (! \in_array($port, $own, true) && is_taken($port, $published))) {
            ++$port;
        }

        $answer = io()->ask(\sprintf('Port of %s (%s)', $service, $variable), (string) $port, validate_port(...));
        $chosen[$variable] = \is_int($answer) ? $answer : $port;
    }

    write_env_local($chosen);
    io()->success('The ports are in .env.local: run "castor stack:start" to apply them.');
}

/**
 * The value of $variable in .env.local, or else in .env.
 */
function current_port(string $variable): int
{
    $port = 0;
    foreach (['.env', '.env.local'] as $file) {
        if (is_file($file) && preg_match('/^' . $variable . '=(\d+)$/m', (string) file_get_contents($file), $matches) === 1) {
            $port = (int) $matches[1];
        }
    }

    return $port;
}

/**
 * The host ports published by the containers that $command lists ("docker ps"-like output).
 *
 * @param list<string> $command
 *
 * @return list<int>
 */
function published_ports(array $command): array
{
    $output = capture($command, context: context()->withAllowFailure()->withQuiet());
    preg_match_all('/:(\d+)->/', $output, $matches);

    return array_values(array_unique(array_map(\intval(...), $matches[1])));
}

/**
 * Whether a container publishes $port, or a program answers on it. Binding it proves nothing: some
 * Docker engines publish the ports without holding them on the host.
 *
 * @param list<int> $published
 */
function is_taken(int $port, array $published): bool
{
    if (\in_array($port, $published, true)) {
        return true;
    }

    $connection = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.2);
    if ($connection === false) {
        return false;
    }

    fclose($connection);

    return true;
}

function validate_port(mixed $answer): int
{
    $port = filter_var($answer, \FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
            'max_range' => 65535,
        ],
    ]);
    if ($port === false) {
        throw new \InvalidArgumentException('A port is a number from 1 to 65535.');
    }

    return $port;
}

/**
 * Sets the ports in .env.local, and keeps its other lines.
 *
 * @param array<string, int> $ports
 */
function write_env_local(array $ports): void
{
    $content = is_file('.env.local') ? (string) file_get_contents('.env.local') : "# The local configuration of this checkout, not committed.\n";
    foreach ($ports as $variable => $port) {
        $line = $variable . '=' . $port;
        $content = preg_match('/^' . $variable . '=.*$/m', $content) === 1
            ? (string) preg_replace('/^' . $variable . '=.*$/m', $line, $content)
            : rtrim($content, "\n") . "\n" . $line . "\n";
    }

    file_put_contents('.env.local', $content);
}
