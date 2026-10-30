<?php

declare(strict_types=1);

namespace App\Controller\Preference;

use App\Entity\User;
use App\Preference\Language;
use App\Preference\PreferenceCookie;
use App\Preference\ReturnPath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Changes the language: on the account of a signed-in user, and in a cookie for everyone, so the
 * choice also holds on the login page.
 */
final class SwitchLanguageController extends AbstractController
{
    #[Route(path: '/preferences/language', name: 'app_preference_language', methods: ['POST'])]
    #[IsCsrfTokenValid(id: 'preference')]
    public function __invoke(Request $request, EntityManagerInterface $entityManager): Response
    {
        $language = Language::tryFrom($request->request->getString('language'))
            ?? throw new BadRequestHttpException('Unknown language.');

        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setLanguage($language);
            $entityManager->flush();
        }

        $response = $this->redirect(ReturnPath::from($request));
        $response->headers->setCookie(PreferenceCookie::create(PreferenceCookie::LANGUAGE, $language->value));

        return $response;
    }
}
