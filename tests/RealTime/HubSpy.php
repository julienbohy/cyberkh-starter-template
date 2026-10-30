<?php

declare(strict_types=1);

namespace App\Tests\RealTime;

use Symfony\Component\Mercure\Exception\RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;

/**
 * The hub of the test env (config/services.yaml): it records the updates instead of publishing
 * them, as mercure_publish() only exists inside a FrankenPHP request. The subscriber side (public
 * URL, cookie, token factory) stays the real one, so the tests see the production configuration.
 *
 * The updates are static because the test client reboots the kernel, and so builds a new hub,
 * between two requests.
 */
final class HubSpy implements HubInterface
{
    /**
     * @var list<Update>
     */
    private static array $updates = [];

    private static bool $failNextPublish = false;

    public function __construct(
        private readonly HubInterface $inner,
    ) {}

    /**
     * @return list<Update>
     */
    public static function updates(): array
    {
        return self::$updates;
    }

    public static function reset(): void
    {
        self::$updates = [];
        self::$failNextPublish = false;
    }

    /**
     * The next publish() throws, as when the hub is unreachable.
     */
    public static function failNextPublish(): void
    {
        self::$failNextPublish = true;
    }

    public function publish(Update $update): string
    {
        if (self::$failNextPublish) {
            self::$failNextPublish = false;

            throw new RuntimeException('The hub is unreachable (HubSpy::failNextPublish()).');
        }

        self::$updates[] = $update;

        return 'urn:uuid:hub-spy-' . \count(self::$updates);
    }

    public function getPublicUrl(): string
    {
        return $this->inner->getPublicUrl();
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return $this->inner->getFactory();
    }

    public function getProtocolVersion(): ProtocolVersion
    {
        return $this->inner->getProtocolVersion();
    }

    public function getCookieName(): string
    {
        return $this->inner->getCookieName();
    }
}
