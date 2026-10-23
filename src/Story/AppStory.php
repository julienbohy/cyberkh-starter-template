<?php

declare(strict_types=1);

namespace App\Story;

use App\Factory\UserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * The development data, loaded by `castor db:reset`.
 */
#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function build(): void
    {
        // Log in with this email and UserFactory::DEFAULT_PASSWORD.
        UserFactory::createOne([
            'email' => 'user@example.com',
        ]);
    }
}
