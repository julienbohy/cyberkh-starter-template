<?php

declare(strict_types=1);

namespace App\Preference;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Picks the language of each page: the one of the signed-in user, else the one a visitor chose
 * (cookie), else the browser's, else English. It runs after the firewall (priority 8), to know
 * the user, so it sets the locale of the translator itself rather than leaving it to the
 * listener of the framework, which has already run.
 */
final readonly class LanguageSubscriber
{
    public function __construct(
        private Security $security,
        private LocaleSwitcher $localeSwitcher,
    ) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
    public function onRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $user = $this->security->getUser();
        $language = $user instanceof User
            ? $user->getLanguage()
            : Language::tryFrom($request->cookies->getString(PreferenceCookie::LANGUAGE))
                ?? Language::tryFrom((string) $request->getPreferredLanguage(array_column(Language::cases(), 'value')))
                ?? Language::English;

        $request->setLocale($language->value);
        $this->localeSwitcher->setLocale($language->value);
    }
}
