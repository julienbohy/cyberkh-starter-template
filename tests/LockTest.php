<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\LockFactory;

final class LockTest extends KernelTestCase
{
    public function testALockIsExclusiveUntilReleased(): void
    {
        $factory = self::getContainer()->get(LockFactory::class);
        $first = $factory->createLock('lock-test');
        $second = $factory->createLock('lock-test');

        self::assertTrue($first->acquire());
        self::assertFalse($second->acquire());

        $first->release();

        self::assertTrue($second->acquire());
        $second->release();
    }
}
