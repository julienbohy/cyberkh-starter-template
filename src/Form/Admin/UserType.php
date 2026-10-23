<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\User;
use App\Security\Role;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The account of a user, as a super admin edits it.
 *
 * @extends AbstractType<User>
 */
final class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // A super admin cannot lock themself out: their own roles and status are read only.
        $ownAccount = $options['own_account'];

        $builder
            ->add('grantedRoles', EnumType::class, [
                'class' => Role::class,
                'choice_label' => static fn(Role $role): string => $role->label(),
                'multiple' => true,
                'expanded' => true,
                'label' => 'admin.user.form.roles',
                'help' => 'admin.user.form.roles_help',
                'disabled' => $ownAccount,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'admin.user.form.enabled',
                'help' => 'admin.user.form.enabled_help',
                'required' => false,
                'disabled' => $ownAccount,
            ])
            ->add('isVerified', CheckboxType::class, [
                'label' => 'admin.user.form.verified',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'own_account' => false,
        ]);
        $resolver->setAllowedTypes('own_account', 'bool');
    }
}
