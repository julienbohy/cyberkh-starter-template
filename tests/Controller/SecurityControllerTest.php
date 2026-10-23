<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

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
        self::assertSelectorTextContains('main', $this->trans('dashboard.logged_in_as', [
            '%email%' => 'jane@example.com',
        ]));

        $this->client->clickLink($this->trans('nav.logout'));

        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testTheDashboardIsForTheSignedInUsers(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testASignedInUserSkipsTheLoginPage(): void
    {
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request('GET', '/login');

        self::assertResponseRedirects('/');
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

    public function testADisabledUserCannotLogIn(): void
    {
        UserFactory::new()->disabled()->create([
            'email' => 'jane@example.com',
        ]);

        $this->logIn('jane@example.com', UserFactory::DEFAULT_PASSWORD);

        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=alert]', $this->trans('security.login.account_disabled', [], 'security'));
    }

    public function testADisabledUserIsLoggedOutAtTheirNextRequest(): void
    {
        $user = UserFactory::createOne([
            'email' => 'jane@example.com',
        ]);
        $this->logIn('jane@example.com', UserFactory::DEFAULT_PASSWORD);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        // Loaded again: the kernel of the client reboots at each request, with a new entity manager.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->find(User::class, $user->getId());
        self::assertNotNull($user);
        $user->setEnabled(false);
        $entityManager->flush();

        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }
}
