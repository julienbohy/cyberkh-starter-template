<?php

declare(strict_types=1);

namespace App\Form;

use App\Validator\PasswordRequirements;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<array{plainPassword: string}>
 */
final class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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
                // Hashed in the controller, not set on the user as is.
                'mapped' => false,
            ])
        ;
    }
}
