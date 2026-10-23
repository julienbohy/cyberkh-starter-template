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
        // Log in with these emails and UserFactory::DEFAULT_PASSWORD.
        UserFactory::new()->superAdmin()->create([
            'email' => 'admin@example.com',
        ]);
        UserFactory::createOne([
            'email' => 'user@example.com',
        ]);
        UserFactory::new()->unverified()->many(3)->create();
        UserFactory::new()->disabled()->create();
    }
}
