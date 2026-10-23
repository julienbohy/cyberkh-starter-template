<?php

declare(strict_types=1);

namespace App\Form;

use App\Validator\PasswordRequirements;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

/**
 * The password of the signed-in user, changed with their current one.
 *
 * @extends AbstractType<array{currentPassword: string, plainPassword: string}>
 */
final class ChangeOwnPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'profile.password.current',
                'attr' => [
                    'autocomplete' => 'current-password',
                ],
                'constraints' => [new UserPassword(message: 'password.current_wrong')],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'first_options' => [
                    'label' => 'reset_password.form.new_password',
                    'help' => 'registration.form.password_help',
                    'constraints' => [new PasswordRequirements()],
                ],
                'second_options' => [
                    'label' => 'reset_password.form.repeat_password',
                ],
                'invalid_message' => 'password.mismatch',
            ])
        ;
    }
}
