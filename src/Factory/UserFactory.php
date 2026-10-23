<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use App\Security\Role;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Verified users by default, whose plain password is DEFAULT_PASSWORD unless given.
 *
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public const string DEFAULT_PASSWORD = 'correct-horse-battery-staple';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    #[\Override]
    public static function class(): string
    {
        return User::class;
    }

    public function superAdmin(): self
    {
        return $this->with([
            'grantedRoles' => [Role::SuperAdmin],
        ]);
    }

    public function disabled(): self
    {
        return $this->with([
            'enabled' => false,
        ]);
    }

    public function unverified(): self
    {
        return $this->with([
            'isVerified' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'isVerified' => true,
            'password' => self::DEFAULT_PASSWORD,
            'roles' => [],
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        // The "password" attribute is the plain password: it is hashed here.
        return $this->afterInstantiate(function (User $user): void {
            $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));
        });
    }
}
