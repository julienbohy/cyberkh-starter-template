<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\UserFactory;

final class SecurityControllerTest extends AbstractControllerTestCase
{
    public function testAVerifiedUserLogsInAndOut(): void
    {
        UserFactory::createOne([
            'email' => 'jane@example.com',
        ]);

        $this->logIn('jane@example.com', UserFactory::DEFAULT_PASSWORD);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', $this->trans('home.logged_in_as', [
            '%email%' => 'jane@example.com',
        ]));

        $this->client->clickLink($this->trans('nav.logout'));

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', $this->trans('home.anonymous'));
    }

    public function testAWrongPasswordIsRefused(): void
    {
        UserFactory::createOne([
            'email' => 'jane@example.com',
        ]);

        $this->logIn('jane@example.com', 'not-the-password');

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=alert]', $this->trans('Invalid credentials.', [], 'security'));
    }

    public function testAnUnverifiedUserCannotLogIn(): void
    {
        UserFactory::new()->unverified()->create([
            'email' => 'jane@example.com',
        ]);

        $this->logIn('jane@example.com', UserFactory::DEFAULT_PASSWORD);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=alert]', $this->trans('security.login.email_not_verified', [], 'security'));
    }

    public function testAWrongPasswordDoesNotRevealAnUnverifiedAccount(): void
    {
        UserFactory::new()->unverified()->create([
            'email' => 'jane@example.com',
        ]);

        $this->logIn('jane@example.com', 'not-the-password');

        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=alert]', $this->trans('Invalid credentials.', [], 'security'));
    }
}
