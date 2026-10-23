<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Factory\UserFactory;
use App\Security\Role;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class CreateUserCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testItCreatesAVerifiedSuperAdmin(): void
    {
        $command = $this->command();
        $command->setInputs(['Un-Mot-De-Passe-Solide-2026']);

        $command->execute([
            'email' => 'admin@example.com',
            '--super-admin' => true,
        ]);

        $command->assertCommandIsSuccessful();
        $user = UserFactory::repository()->findOneBy([
            'email' => 'admin@example.com',
        ]);
        self::assertNotNull($user);
        self::assertTrue($user->isVerified());
        self::assertSame([Role::SuperAdmin], $user->getGrantedRoles());
    }

    public function testItRefusesAWeakPassword(): void
    {
        $command = $this->command();
        $command->setInputs(['password']);

        self::assertSame(Command::INVALID, $command->execute([
            'email' => 'admin@example.com',
        ]));
        UserFactory::assert()->empty();
    }

    public function testItRefusesAnEmailInUse(): void
    {
        UserFactory::createOne([
            'email' => 'admin@example.com',
        ]);

        self::assertSame(Command::INVALID, $this->command()->execute([
            'email' => 'admin@example.com',
        ]));
    }

    private function command(): CommandTester
    {
        $kernel = self::bootKernel();

        return new CommandTester(new Application($kernel)->find('app:user:create'));
    }
}
