<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Security\Role;
use App\Validator\PasswordRequirements;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Creates a verified user: the first super admin, typically, who then invites the others.
 */
#[AsCommand(name: 'app:user:create', description: 'Create a verified user, asking for their password')]
final readonly class CreateUserCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The email address of the user')]
        string $email,
        #[Option(description: 'Grant the user ROLE_SUPER_ADMIN, who manages the users')]
        bool $superAdmin = false,
    ): int {
        $user = new User()
            ->setEmail($email)
            ->setIsVerified(true)
            ->setGrantedRoles($superAdmin ? [Role::SuperAdmin] : []);

        if (! $this->isValid($io, $this->validator->validate($user))) {
            return Command::INVALID;
        }

        $password = $io->askHidden('Password');
        $password = \is_string($password) ? $password : '';
        if (! $this->isValid($io, $this->validator->validate($password, new PasswordRequirements()))) {
            return Command::INVALID;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(\sprintf('The user %s is created%s.', $email, $superAdmin ? ', as a super admin' : ''));

        return Command::SUCCESS;
    }

    private function isValid(SymfonyStyle $io, ConstraintViolationListInterface $violations): bool
    {
        foreach ($violations as $violation) {
            $io->error((string) $violation->getMessage());
        }

        return \count($violations) === 0;
    }
}
