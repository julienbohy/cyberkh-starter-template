<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\UserFactory;

final class ResetPasswordControllerTest extends AbstractControllerTestCase
{
    private const string NEW_PASSWORD = 'Un-Nouveau-Mot-De-Passe-2026';

    public function testAUserResetsTheirPassword(): void
    {
        UserFactory::createOne([
            'email' => 'jane@example.com',
        ]);

        $this->requestReset('jane@example.com');

        self::assertResponseRedirects('/reset-password/check-email');
        $email = $this->sentEmail();
        self::assertEmailAddressContains($email, 'from', 'noreply@example.com');
        self::assertEmailAddressContains($email, 'to', 'jane@example.com');

        // The link puts the token in the session, then redirects to the URL without it.
        $this->client->request('GET', $this->linkOf($email, '/reset-password/reset/'));
        self::assertResponseRedirects('/reset-password/reset');
        $this->client->followRedirect();

        $this->client->submitForm($this->trans('reset_password.reset.submit'), [
            'change_password_form[plainPassword][first]' => self::NEW_PASSWORD,
            'change_password_form[plainPassword][second]' => self::NEW_PASSWORD,
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=alert]', $this->trans('reset_password.flash.password_changed'));

        $this->logIn('jane@example.com', UserFactory::DEFAULT_PASSWORD);
        self::assertResponseRedirects('/login');

        $this->logIn('jane@example.com', self::NEW_PASSWORD);
        self::assertResponseRedirects('/');
    }

    public function testAnUnknownEmailIsNotRevealed(): void
    {
        $this->requestReset('nobody@example.com');

        self::assertResponseRedirects('/reset-password/check-email');
        self::assertEmailCount(0);
    }

    private function requestReset(string $email): void
    {
        $this->client->request('GET', '/reset-password');
        $this->client->submitForm($this->trans('reset_password.request.submit'), [
            'reset_password_request_form[email]' => $email,
        ]);
    }
}
