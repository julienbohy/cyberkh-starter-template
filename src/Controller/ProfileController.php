<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AvatarType;
use App\Form\ChangeOwnPasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Vich\UploaderBundle\Handler\UploadHandler;

/**
 * The profile of the signed-in user: their photo and their password.
 */
#[IsGranted(attribute: AuthenticatedVoter::IS_AUTHENTICATED)]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route(path: '/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function edit(Request $request, UserPasswordHasherInterface $passwordHasher, Security $security): Response
    {
        $user = $this->currentUser();

        $avatarForm = $this->createForm(AvatarType::class, $user);
        $avatarForm->handleRequest($request);
        if ($avatarForm->isSubmitted() && $avatarForm->isValid()) {
            // VichUploader stores the file, and removes the former one, when Doctrine flushes.
            $this->entityManager->flush();
            $this->addFlash('success', 'profile.flash.avatar_saved');

            return $this->redirectToRoute('app_profile');
        }

        $passwordForm = $this->createForm(ChangeOwnPasswordType::class);
        $passwordForm->handleRequest($request);
        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $passwordForm->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $this->entityManager->flush();

            // A new password logs the sessions out (User::isEqualTo()): this one is logged in again.
            $security->login($user, 'form_login', 'main');
            $this->addFlash('success', 'profile.flash.password_changed');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit.html.twig', [
            'avatarForm' => $avatarForm,
            'passwordForm' => $passwordForm,
        ], new Response(status: $avatarForm->isSubmitted() || $passwordForm->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route(path: '/profile/avatar/delete', name: 'app_profile_avatar_delete', methods: ['POST'])]
    #[IsCsrfTokenValid(id: 'delete-avatar')]
    public function deleteAvatar(UploadHandler $uploadHandler): Response
    {
        $user = $this->currentUser();
        $uploadHandler->remove($user, 'avatarFile');
        $this->entityManager->flush();
        $this->addFlash('success', 'profile.flash.avatar_deleted');

        return $this->redirectToRoute('app_profile');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (! $user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
