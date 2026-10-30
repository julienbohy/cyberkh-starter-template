<?php

declare(strict_types=1);

namespace App\Tests\RealTime;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\ProtocolVersion;

/**
 * The hub built into FrankenPHP only accepts the subscriber tokens of its trusted issuer, and the
 * browser only sends the cookie whose name the hub expects: a mismatch would silently stop every
 * live update.
 */
final class MercureConfigurationTest extends KernelTestCase
{
    public function testHubSpeaksProtocolOneThroughFrankenPhp(): void
    {
        $hub = $this->hub();

        self::assertInstanceOf(HubSpy::class, $hub);
        self::assertSame(ProtocolVersion::V1, $hub->getProtocolVersion());
        self::assertStringEndsWith('/.well-known/mercure', $hub->getPublicUrl());
        self::assertSame('__Secure-mercure_access_token', $hub->getCookieName());
    }

    public function testSubscriberTokenCarriesTheTrustedIssuer(): void
    {
        $factory = $this->hub()->getFactory();
        self::assertNotNull($factory);

        $jwt = $factory->create([new Grant([Grant::ACTION_SUBSCRIBE], ['/admin/users'])]);
        $segments = explode('.', $jwt);
        self::assertCount(3, $segments);

        $json = base64_decode(strtr($segments[1], '-_', '+/'), true);
        self::assertIsString($json);
        $payload = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame($_SERVER['DEFAULT_URI'] ?? null, $payload['iss'] ?? null);
        self::assertSame('app', $payload['sub'] ?? null);
        self::assertSame('app', $payload['client_id'] ?? null);
    }

    /**
     * Through the registry, the way Twig and the Authorization reach it.
     */
    private function hub(): HubInterface
    {
        $registry = static::getContainer()->get(HubRegistry::class);

        return $registry->getHub();
    }
}
