<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array{email: string}>
 */
final class ResetPasswordRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'reset_password.form.email',
                'attr' => [
                    'autocomplete' => 'email',
                    'placeholder' => 'registration.form.email_placeholder',
                ],
                'constraints' => [
                    new NotBlank(message: 'email.not_blank'),
                    new Email(message: 'email.invalid'),
                ],
            ])
        ;
    }
}
