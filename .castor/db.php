<?php

declare(strict_types=1);

// The database of an environment: dev by default.

namespace db;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Empty and migrate the database, then load the fixtures')]
function reset(string $env = 'dev'): void
{
    $console = ['symfony', 'console', '--env=' . $env];

    run([...$console, 'doctrine:database:create', '--if-not-exists']);
    // Emptied rather than dropped: PostgreSQL refuses to drop a database that a worker is connected to.
    run([...$console, 'doctrine:schema:drop', '--full-database', '--force']);
    run([...$console, 'doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration']);
    // Appended: Foundry would build the schema again from the mapping, without the migrations.
    run([...$console, 'foundry:load-fixtures', '--append', '--no-interaction']);
}
