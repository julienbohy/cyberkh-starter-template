<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Preference\Language;
use App\Preference\Theme;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\Cookie;

final class PreferenceTest extends AbstractControllerTestCase
{
    public function testThePagesAreInEnglishByDefault(): void
    {
        $this->client->request('GET', '/login');

        self::assertSelectorExists('html[lang="en"]');
        self::assertSelectorTextContains('main', $this->trans('login.title'));
    }

    public function testTheLanguageOfTheBrowserIsFollowed(): void
    {
        $this->client->request('GET', '/login', server: [
            'HTTP_ACCEPT_LANGUAGE' => 'fr-BE,fr;q=0.9,en;q=0.5',
        ]);

        self::assertSelectorExists('html[lang="fr"]');
        self::assertSelectorTextContains('main', $this->trans('login.title', locale: 'fr'));
    }

    public function testAnUnsupportedBrowserLanguageFallsBackToEnglish(): void
    {
        $this->client->request('GET', '/login', server: [
            'HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9',
        ]);

        self::assertSelectorExists('html[lang="en"]');
    }

    public function testTheChoiceOfAVisitorWinsOverTheBrowser(): void
    {
        $this->client->getCookieJar()->set(new Cookie('language', 'en'));

        $this->client->request('GET', '/login', server: [
            'HTTP_ACCEPT_LANGUAGE' => 'fr',
        ]);

        self::assertSelectorExists('html[lang="en"]');
    }

    public function testAVisitorSwitchesTheLanguageAndComesBack(): void
    {
        $this->client->request('GET', '/login');

        $this->client->submitForm(Language::French->nativeName());

        self::assertResponseRedirects('/login');
        self::assertResponseCookieValueSame('language', 'fr');
        $this->client->followRedirect();
        self::assertSelectorExists('html[lang="fr"]');
    }

    public function testTheLanguageOfTheAccountWinsOverTheCookie(): void
    {
        $user = UserFactory::createOne([
            'language' => Language::French,
        ]);
        $this->client->getCookieJar()->set(new Cookie('language', 'en'));
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile');

        self::assertSelectorExists('html[lang="fr"]');
        self::assertSelectorTextContains('main', $this->trans('profile.title', locale: 'fr'));
    }

    public function testASignedInUserSwitchesTheLanguageOfTheirAccount(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $this->client->request('GET', '/profile');

        $this->client->submitForm(Language::French->nativeName());

        self::assertResponseRedirects('/profile');
        self::assertSame(Language::French, $this->reload($user)->getLanguage());
    }

    public function testAnUnknownLanguageIsRefused(): void
    {
        $this->client->request('GET', '/login');
        $values = $this->client->getCrawler()->selectButton(Language::French->nativeName())->form()->getValues();

        $this->client->request('POST', '/preferences/language', [
            ...$values,
            'language' => 'de',
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testASwitchWithoutAValidTokenIsRefused(): void
    {
        $this->client->request('POST', '/preferences/language', [
            'language' => 'fr',
            '_token' => 'forged',
        ]);

        // What the application does with an invalid token: it sends the visitor to the login page.
        self::assertResponseRedirects('http://localhost/login');
        self::assertResponseNotHasCookie('language');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignTargets(): iterable
    {
        yield 'another host' => ['https://evil.example/'];
        yield 'protocol-relative' => ['//evil.example/'];
        yield 'backslash' => ['/\evil.example/'];
        yield 'tab before slashes' => ["/\t/evil.example/"];
    }

    #[DataProvider('foreignTargets')]
    public function testTheSwitchOnlyGoesBackInsideTheSite(string $target): void
    {
        $this->client->request('GET', '/login');
        $values = $this->client->getCrawler()->selectButton(Language::French->nativeName())->form()->getValues();

        $this->client->request('POST', '/preferences/language', [
            ...$values,
            '_target' => $target,
        ]);

        self::assertResponseRedirects('/');
    }

    public function testTheThemeFollowsTheDeviceByDefault(): void
    {
        $this->client->request('GET', '/login');

        self::assertSelectorExists('html[data-theme="system"]');
        self::assertSelectorNotExists('html.dark');
        self::assertSelectorExists('meta[name="color-scheme"][content="light dark"]');
        self::assertStringContainsString('prefers-color-scheme: dark', (string) $this->client->getResponse()->getContent());
    }

    public function testADarkThemeIsDrawnByTheServer(): void
    {
        $this->client->loginUser(UserFactory::createOne([
            'theme' => Theme::Dark,
        ]));

        $this->client->request('GET', '/profile');

        self::assertSelectorExists('html.dark[data-theme="dark"]');
        self::assertStringNotContainsString('prefers-color-scheme: dark', (string) $this->client->getResponse()->getContent());
    }

    public function testAVisitorSwitchesTheTheme(): void
    {
        $this->client->request('GET', '/login');

        $this->client->submitForm($this->trans('preference.theme.dark'));

        self::assertResponseRedirects('/login');
        self::assertResponseCookieValueSame('theme', 'dark');
        $this->client->followRedirect();
        self::assertSelectorExists('html.dark');
    }

    public function testASignedInUserSwitchesTheThemeOfTheirAccount(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $this->client->request('GET', '/profile');

        $this->client->submitForm($this->trans('preference.theme.light'));

        self::assertResponseRedirects('/profile');
        self::assertSame(Theme::Light, $this->reload($user)->getTheme());
        $this->client->followRedirect();
        self::assertSelectorExists('html[data-theme="light"]');
        self::assertSelectorNotExists('html.dark');
    }

    public function testAnUnknownThemeIsRefused(): void
    {
        $this->client->request('GET', '/login');
        $values = $this->client->getCrawler()->selectButton($this->trans('preference.theme.dark'))->form()->getValues();

        $this->client->request('POST', '/preferences/theme', [
            ...$values,
            'theme' => 'sepia',
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testAnUnknownLanguageCookieFallsBackToTheBrowser(): void
    {
        $this->client->getCookieJar()->set(new Cookie('language', 'de'));

        $this->client->request('GET', '/login', server: [
            'HTTP_ACCEPT_LANGUAGE' => 'fr',
        ]);

        self::assertSelectorExists('html[lang="fr"]');
    }

    public function testAnUnknownThemeCookieFollowsTheDevice(): void
    {
        $this->client->getCookieJar()->set(new Cookie('theme', 'sepia'));

        $this->client->request('GET', '/login');

        self::assertSelectorExists('html[data-theme="system"]');
    }

    public function testTheLanguagesOfTheEnumAreTheEnabledLocales(): void
    {
        /** @var list<string> $enabled */
        $enabled = static::getContainer()->getParameter('kernel.enabled_locales');

        self::assertEqualsCanonicalizing($enabled, array_column(Language::cases(), 'value'));
        self::assertSame('en', static::getContainer()->getParameter('kernel.default_locale'));
    }

    private function reload(User $user): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $reloaded);

        return $reloaded;
    }
}
