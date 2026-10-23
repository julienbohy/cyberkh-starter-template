<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

/**
 * The rules of a new password, the same at the registration and at the password reset.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class PasswordRequirements extends Compound
{
    /**
     * @param array<mixed> $options
     *
     * @return list<Assert\NotBlank|Assert\Length|Assert\PasswordStrength|Assert\NotCompromisedPassword>
     */
    protected function getConstraints(array $options): array
    {
        return [
            new Assert\NotBlank(message: 'password.not_blank'),
            // 4096: the maximum length that Symfony hashes, for security reasons.
            new Assert\Length(min: 12, max: 4096, minMessage: 'password.too_short'),
            new Assert\PasswordStrength(message: 'password.too_weak'),
            new Assert\NotCompromisedPassword(message: 'password.compromised'),
        ];
    }
}
