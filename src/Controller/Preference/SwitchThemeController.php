<?php

declare(strict_types=1);

namespace App\Controller\Preference;

use App\Entity\User;
use App\Preference\PreferenceCookie;
use App\Preference\ReturnPath;
use App\Preference\Theme;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Changes the color theme: on the account of a signed-in user, and in a cookie for everyone, so the
 * choice also holds on the login page.
 */
final class SwitchThemeController extends AbstractController
{
    #[Route(path: '/preferences/theme', name: 'app_preference_theme', methods: ['POST'])]
    #[IsCsrfTokenValid(id: 'preference')]
    public function __invoke(Request $request, EntityManagerInterface $entityManager): Response
    {
        $theme = Theme::tryFrom($request->request->getString('theme'))
            ?? throw new BadRequestHttpException('Unknown theme.');

        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setTheme($theme);
            $entityManager->flush();
        }

        $response = $this->redirect(ReturnPath::from($request));
        $response->headers->setCookie(PreferenceCookie::create(PreferenceCookie::THEME, $theme->value));

        return $response;
    }
}
