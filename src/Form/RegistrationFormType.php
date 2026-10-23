<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Validator\PasswordRequirements;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

/**
 * @extends AbstractType<User>
 */
final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'registration.form.email',
                'attr' => [
                    'autocomplete' => 'email',
                    'placeholder' => 'registration.form.email_placeholder',
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'registration.form.password',
                'help' => 'registration.form.password_help',
                // Hashed in the controller, not set on the user as is.
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
                'constraints' => [new PasswordRequirements()],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'registration.form.agree_terms',
                'mapped' => false,
                'constraints' => [new IsTrue(message: 'registration.agree_terms')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
