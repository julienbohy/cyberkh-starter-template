<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/bin',
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withRootFiles()
    // Generated and kept up to date by Symfony Flex, the console and Castor.
    ->withSkip([
        __DIR__ . '/.castor.stub.php',
        __DIR__ . '/config/reference.php',
        __DIR__ . '/config/bundles.php',
        __DIR__ . '/importmap.php',
    ])
    ->withPreparedSets(perCs: true, common: true, cleanCode: true);
