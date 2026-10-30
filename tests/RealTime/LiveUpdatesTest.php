<?php

declare(strict_types=1);

namespace App\Tests\RealTime;

use App\RealTime\LiveTopic;
use App\RealTime\LiveUpdates;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\HubInterface;

/**
 * The publisher sends private updates on the topic, and never throws.
 */
final class LiveUpdatesTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HubSpy::reset();
    }

    public function testRefreshPublishesAPrivateRefreshOnTheTopic(): void
    {
        self::getContainer()->get(LiveUpdates::class)->refresh(LiveTopic::Users);

        $updates = HubSpy::updates();
        self::assertCount(1, $updates);
        self::assertSame(['/admin/users'], $updates[0]->getTopics());
        self::assertTrue($updates[0]->isPrivate());
        self::assertStringContainsString('action="refresh"', $updates[0]->getData());
    }

    public function testRefreshCarriesTheTurboRequestIdOfTheActingRequest(): void
    {
        $request = new Request();
        $request->headers->set('X-Turbo-Request-Id', 'abc');
        self::getContainer()->get(RequestStack::class)->push($request);

        self::getContainer()->get(LiveUpdates::class)->refresh(LiveTopic::Users);

        self::assertStringContainsString('request-id="abc"', HubSpy::updates()[0]->getData());
    }

    public function testAFailingHubIsLoggedNotThrown(): void
    {
        HubSpy::failNextPublish();

        // Built by hand to record the logs: the test container has no log recorder to read back.
        $logger = new class() extends AbstractLogger {
            /**
             * @var list<array{level: mixed, message: string}>
             */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string) $message,
                ];
            }
        };
        $container = self::getContainer();
        $liveUpdates = new LiveUpdates(
            $container->get(HubInterface::class),
            $container->get(RequestStack::class),
            $logger,
        );

        $liveUpdates->refresh(LiveTopic::Users);

        self::assertSame([], HubSpy::updates());
        self::assertSame([
            [
                'level' => LogLevel::WARNING,
                'message' => 'Live update not published.',
            ],
        ], $logger->records);
    }
}
