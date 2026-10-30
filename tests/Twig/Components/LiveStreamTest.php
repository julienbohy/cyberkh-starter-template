<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Twig\Components\LiveStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class LiveStreamTest extends TestCase
{
    public function testASuperAdminFollowsTheDeclaredTopic(): void
    {
        $stream = $this->stream(true);
        $stream->mount(['admin/users']);

        self::assertSame(['/admin/users'], $stream->topics());
        self::assertSame(['/admin/users'], $stream->grants());
    }

    public function testTheCookieGrantsATopicThePageDoesNotFollow(): void
    {
        $stream = $this->stream(true);
        $stream->mount();

        self::assertSame([], $stream->topics());
        self::assertSame(['/admin/users'], $stream->grants());
    }

    public function testAnotherUserFollowsNothing(): void
    {
        $stream = $this->stream(false);
        $stream->mount(['admin/users']);

        self::assertSame([], $stream->topics());
        self::assertSame([], $stream->grants());
    }

    private function stream(bool $granted): LiveStream
    {
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn($granted);

        return new LiveStream($checker);
    }
}
