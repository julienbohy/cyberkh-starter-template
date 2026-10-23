<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\After;

final class RegistrationControllerTest extends AbstractControllerTestCase
{
    private const string PASSWORD = 'Un-Mot-De-Passe-Solide-2026';

    public function testARegisteredUserLogsInOnceTheirEmailIsVerified(): void
    {
        $this->register('jane@example.com', self::PASSWORD);

        self::assertResponseRedirects('/login');
        $user = UserFactory::repository()->findOneBy([
            'email' => 'jane@example.com',
        ]);
        self::assertNotNull($user);
        self::assertFalse($user->isVerified());

        $email = $this->sentEmail();
        self::assertEmailAddressContains($email, 'from', 'noreply@example.com');
        self::assertEmailAddressContains($email, 'to', 'jane@example.com');

        $this->logIn('jane@example.com', self::PASSWORD);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=alert]', $this->trans('security.login.email_not_verified', [], 'security'));

        $this->client->request('GET', $this->linkOf($email, '/verify/email'));

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=alert]', $this->trans('registration.flash.email_verified'));

        $this->logIn('jane@example.com', self::PASSWORD);
        self::assertResponseRedirects('/');
    }

    public function testAnEmailIsRegisteredOnce(): void
    {
        UserFactory::createOne([
            'email' => 'jane@example.com',
        ]);

        $this->register('jane@example.com', self::PASSWORD);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-slot=form-message]', $this->trans('email.already_used', [], 'validators'));
        self::assertEmailCount(0);
    }

    public function testAWeakPasswordIsRefused(): void
    {
        $this->register('jane@example.com', 'password');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-slot=form-message]', $this->trans('password.too_short', [
            '{{ limit }}' => '12',
        ], 'validators'));
        UserFactory::assert()->empty();
    }

    public function testAnInvalidVerificationLinkIsRefused(): void
    {
        $user = UserFactory::new()->unverified()->create();

        $this->client->request('GET', '/verify/email?id=' . $user->getId() . '&signature=forged');

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('[role=alert]');
        self::assertFalse($user->isVerified());
    }

    public function testTheRegistrationCanBeDisabled(): void
    {
        $_ENV['APP_REGISTRATION_ENABLED'] = $_SERVER['APP_REGISTRATION_ENABLED'] = '0';
        // The environment variables are read when the kernel boots.
        self::ensureKernelShutdown();
        $this->client = static::createClient();

        $this->client->request('GET', '/register');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/login');
        self::assertSelectorNotExists('a[href="/register"]');
    }

    #[After]
    public function enableTheRegistration(): void
    {
        $_ENV['APP_REGISTRATION_ENABLED'] = $_SERVER['APP_REGISTRATION_ENABLED'] = '1';
    }

    private function register(string $email, string $password): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm($this->trans('registration.submit'), [
            'registration_form[email]' => $email,
            'registration_form[plainPassword]' => $password,
            'registration_form[agreeTerms]' => true,
        ]);
    }
}
