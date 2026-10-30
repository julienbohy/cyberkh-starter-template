<?php

declare(strict_types=1);

namespace App\RealTime\DependencyInjection;

use Symfony\Bundle\MercureBundle\HubFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Mercure\FrankenPhpHub;

/**
 * Builds the default hub as a FrankenPhpHub directly. The bundle's HubFactory refuses to build a
 * hub without "url" outside a FrankenPHP request, and Twig's MercureExtension builds the hub
 * eagerly: every CLI use of Twig (lint:twig, the emails of the Messenger worker) would then fail.
 * FrankenPhpHub itself only needs mercure_publish() in publish(), which throws the Mercure
 * RuntimeException under the CLI.
 */
final class FrankenPhpHubPass implements CompilerPassInterface
{
    private const string HUB = 'mercure.hub.default';

    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasDefinition(self::HUB)) {
            return;
        }

        $definition = $container->getDefinition(self::HUB);
        // The arguments of HubFactory::create(): url, token provider, token factory, public URL,
        // HTTP client, cookie name, protocol version (MercureExtension::load()).
        $arguments = $definition->getArguments();
        if ($definition->getFactory() !== [HubFactory::class, 'create'] || \count($arguments) !== 7 || ! \in_array($arguments[0], [null, ''], true)) {
            throw new \LogicException(\sprintf('The definition of "%s" is not the one of symfony/mercure-bundle 0.6 for a hub without "url": update %s.', self::HUB, self::class));
        }

        $definition
            ->setFactory(null)
            ->setClass(FrankenPhpHub::class)
            ->setArguments([$arguments[3], $arguments[2], $arguments[5], $arguments[6]]);
    }
}
