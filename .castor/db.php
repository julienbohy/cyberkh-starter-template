<?php

declare(strict_types=1);

// The database of an environment: dev by default.

namespace db;

use Castor\Attribute\AsTask;

use function Castor\run;
use function stack\php;

#[AsTask(description: 'Empty and migrate the database, then load the fixtures')]
function reset(string $env = 'dev'): void
{
    $console = ['php', 'bin/console', '--env=' . $env];

    run(php(...$console, ...['doctrine:database:create', '--if-not-exists']));
    // Emptied rather than dropped: PostgreSQL refuses to drop a database that a worker is connected to.
    run(php(...$console, ...['doctrine:schema:drop', '--full-database', '--force']));
    run(php(...$console, ...['doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration']));
    // Appended: Foundry would build the schema again from the mapping, without the migrations.
    run(php(...$console, ...['foundry:load-fixtures', '--append', '--no-interaction']));
}
