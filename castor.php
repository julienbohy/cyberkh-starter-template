<?php

declare(strict_types=1);

// The shared setup of the Castor tasks, which are in .castor/, one file per prefix: app, stack,
// env, db and qa. The application runs in Docker (compose.yaml): the tasks run its commands in
// the php container, so that the host needs neither PHP nor Composer.
define('CASTOR_USE_CHDIR', true);

use function Castor\import;

import(__DIR__ . '/.castor');
