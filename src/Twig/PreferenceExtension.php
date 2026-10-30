<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Preference\Language;
use App\Preference\PreferenceCookie;
use App\Preference\Theme;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * The language and theme choices, for the switches and for the theme class of the page.
 */
final readonly class PreferenceExtension
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
    ) {}

    /**
     * The theme of the signed-in user, else the one a visitor chose, else the device's.
     */
    #[AsTwigFunction(name: 'current_theme')]
    public function currentTheme(): Theme
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            return $user->getTheme();
        }

        $cookie = $this->requestStack->getMainRequest()?->cookies->getString(PreferenceCookie::THEME) ?? '';

        return Theme::tryFrom($cookie) ?? Theme::System;
    }

    /**
     * @return list<Language>
     */
    #[AsTwigFunction(name: 'languages')]
    public function languages(): array
    {
        return Language::cases();
    }

    /**
     * @return list<Theme>
     */
    #[AsTwigFunction(name: 'themes')]
    public function themes(): array
    {
        return Theme::cases();
    }
}
