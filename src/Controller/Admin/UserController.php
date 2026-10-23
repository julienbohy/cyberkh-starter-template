<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\InvitationType;
use App\Form\Admin\UserType;
use App\Repository\UserRepository;
use App\Security\PasswordLinkMailer;
use App\Security\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The management of the users, for the super admins only (access_control guards /admin too).
 */
#[IsGranted(attribute: Role::SuperAdmin->value)]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PasswordLinkMailer $passwordLinkMailer,
    ) {}

    #[Route(path: '/admin/users', name: 'admin_user_index', methods: ['GET'])]
    public function index(
        UserRepository $userRepository,
        #[MapQueryParameter]
        string $q = '',
        #[MapQueryParameter]
        int $page = 1,
    ): Response {
        return $this->render('admin/user/index.html.twig', [
            'search' => $q,
            'users' => $userRepository->search(trim($q), $page),
        ]);
    }

    #[Route(path: '/admin/users/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(InvitationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // No password: the invited user chooses it from the link, which verifies their email too.
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->passwordLinkMailer->sendInvitation($user);

            $this->addFlash('success', 'admin.user.flash.invited');

            return $this->redirectToRoute('admin_user_edit', [
                'id' => $user->getId(),
            ]);
        }

        return $this->render('admin/user/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: '/admin/users/{id}', name: 'admin_user_edit', requirements: [
        'id' => Requirement::UUID,
    ], methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user, [
            'own_account' => $this->isOwnAccount($user),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'admin.user.flash.saved');

            return $this->redirectToRoute('admin_user_edit', [
                'id' => $user->getId(),
            ]);
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
            'own_account' => $this->isOwnAccount($user),
        ]);
    }

    #[Route(path: '/admin/users/{id}/password-link', name: 'admin_user_password_link', requirements: [
        'id' => Requirement::UUID,
    ], methods: ['POST'])]
    #[IsCsrfTokenValid(id: new Expression('"password-link-" ~ args["user"].getId()'))]
    public function sendPasswordLink(User $user): Response
    {
        $this->passwordLinkMailer->sendInvitation($user);
        $this->addFlash('success', 'admin.user.flash.password_link_sent');

        return $this->redirectToRoute('admin_user_edit', [
            'id' => $user->getId(),
        ]);
    }

    #[Route(path: '/admin/users/{id}/delete', name: 'admin_user_delete', requirements: [
        'id' => Requirement::UUID,
    ], methods: ['POST'])]
    #[IsCsrfTokenValid(id: new Expression('"delete-" ~ args["user"].getId()'))]
    public function delete(User $user): Response
    {
        if ($this->isOwnAccount($user)) {
            throw $this->createAccessDeniedException('A super admin cannot delete their own account.');
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();
        $this->addFlash('success', 'admin.user.flash.deleted');

        return $this->redirectToRoute('admin_user_index');
    }

    private function isOwnAccount(User $user): bool
    {
        $currentUser = $this->getUser();

        return $currentUser instanceof User && $currentUser->getId()?->equals($user->getId()) === true;
    }
}
