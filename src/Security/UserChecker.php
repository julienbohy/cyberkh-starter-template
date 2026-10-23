<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuses the login of a user until they have verified their email address.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void {}

    /**
     * After the password check: a wrong password must not reveal that the account exists.
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if ($user instanceof User && ! $user->isVerified()) {
            // Translated in the "security" domain, as the other authentication errors.
            throw new CustomUserMessageAccountStatusException('security.login.email_not_verified');
        }
    }
}
