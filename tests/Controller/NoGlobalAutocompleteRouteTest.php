<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * The bundle's global `/autocomplete/{alias}` route is outside the access checks of each page: a
 * field declares its own protected route instead.
 */
final class NoGlobalAutocompleteRouteTest extends KernelTestCase
{
    public function testTheRouterHasNoGlobalAutocompleteRoute(): void
    {
        $routes = self::getContainer()->get(RouterInterface::class)->getRouteCollection();

        self::assertNull($routes->get('ux_autocomplete'));
        foreach ($routes as $name => $route) {
            self::assertStringStartsNotWith('/autocomplete', $route->getPath(), \sprintf('Route "%s" is a global autocomplete route.', $name));
        }
    }
}
