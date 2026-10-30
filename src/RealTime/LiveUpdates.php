<?php

declare(strict_types=1);

namespace App\RealTime;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\UX\Turbo\Helper\TurboStream;

/**
 * Publishes the live updates, after the action flushed. A failing hub never throws: the database
 * change is already committed, so an unreachable hub must not turn it into a 500. The plain
 * RuntimeException, as mercure_publish() throws its own errors, not the one of the Mercure component.
 */
final readonly class LiveUpdates
{
    public function __construct(
        private HubInterface $hub,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {}

    /**
     * A payload-free refresh: every subscribed tab re-fetches its own page, so per-viewer rendering
     * and authorization stay server-side. The acting tab is skipped through its Turbo request id.
     */
    public function refresh(LiveTopic $topic): void
    {
        $requestId = $this->requestStack->getMainRequest()?->headers->get('X-Turbo-Request-Id');

        try {
            $this->hub->publish(new Update($topic->uri(), TurboStream::refresh($requestId), true));
        } catch (\RuntimeException|ExceptionInterface $e) {
            $this->logger->warning('Live update not published.', [
                'topic' => $topic->uri(),
                'exception' => $e,
            ]);
        }
    }
}
