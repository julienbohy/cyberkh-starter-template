<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Security\Role;
use App\Tests\Controller\AbstractControllerTestCase;

final class UserControllerTest extends AbstractControllerTestCase
{
    private const string PASSWORD = 'Un-Mot-De-Passe-Solide-2026';

    public function testTheUsersAreForTheSuperAdminsOnly(): void
    {
        $this->client->request('GET', '/admin/users');
        self::assertResponseRedirects('/login');

        $this->client->loginUser(UserFactory::new()->create([
            'grantedRoles' => [Role::Admin],
        ]));
        $this->client->request('GET', '/admin/users');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorNotExists('a[href="/admin/users"]');

        $this->logInAsSuperAdmin();
        $this->client->request('GET', '/');
        self::assertSelectorExists('a[href="/admin/users"]');
        $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();
    }

    public function testTheUsersAreSearchedByEmail(): void
    {
        UserFactory::createOne([
            'email' => 'jane@example.com',
        ]);
        UserFactory::createOne([
            'email' => 'john@example.com',
        ]);
        $this->logInAsSuperAdmin();

        $this->client->request('GET', '/admin/users?q=JANE');

        self::assertSelectorTextContains('table', 'jane@example.com');
        self::assertSelectorTextNotContains('table', 'john@example.com');
    }

    public function testAnInvitedUserChoosesTheirPassword(): void
    {
        $this->logInAsSuperAdmin();

        $this->client->request('GET', '/admin/users/new');
        $this->client->submitForm($this->trans('admin.user.new.submit'), [
            'invitation[email]' => 'jane@example.com',
            // The checkboxes of a choice, in the order of Role: 0 for the super admin, 1 for the admin.
            'invitation[grantedRoles][1]' => 'ROLE_ADMIN',
        ]);

        $user = $this->user('jane@example.com');
        self::assertResponseRedirects('/admin/users/' . $user->getId());
        self::assertSame([Role::Admin], $user->getGrantedRoles());
        self::assertFalse($user->isVerified());

        $email = $this->sentEmail();
        self::assertEmailAddressContains($email, 'to', 'jane@example.com');

        $this->client->request('GET', '/logout');
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', $this->linkOf($email, '/reset-password/reset/'));
        $this->client->followRedirect();
        $this->client->submitForm($this->trans('reset_password.reset.submit'), [
            'change_password_form[plainPassword][first]' => self::PASSWORD,
            'change_password_form[plainPassword][second]' => self::PASSWORD,
        ]);

        $this->logIn('jane@example.com', self::PASSWORD);
        self::assertResponseRedirects('/');
        self::assertTrue($this->user('jane@example.com')->isVerified());
    }

    public function testASuperAdminEditsTheRolesAndTheStatus(): void
    {
        $user = UserFactory::createOne();
        $this->logInAsSuperAdmin();

        $this->client->request('GET', '/admin/users/' . $user->getId());
        $this->client->submitForm($this->trans('admin.user.edit.submit'), [
            'user[grantedRoles][0]' => 'ROLE_SUPER_ADMIN',
            'user[grantedRoles][1]' => 'ROLE_ADMIN',
            'user[enabled]' => false,
        ]);

        self::assertResponseRedirects('/admin/users/' . $user->getId());
        $user = $this->user($user->getEmail());
        self::assertSame([Role::SuperAdmin, Role::Admin], $user->getGrantedRoles());
        self::assertFalse($user->isEnabled());
    }

    public function testASuperAdminCannotLockThemselfOut(): void
    {
        $superAdmin = $this->logInAsSuperAdmin();

        $crawler = $this->client->request('GET', '/admin/users/' . $superAdmin->getId());

        self::assertNotNull($crawler->filter('#user_enabled')->attr('disabled'));
        self::assertCount(0, $crawler->filter('form[action$="/delete"]'));
        self::assertCount(0, $crawler->selectLink($this->trans('admin.user.edit.impersonate')));
    }

    public function testASuperAdminDeletesAUser(): void
    {
        $user = UserFactory::createOne([
            'email' => 'jane@example.com',
        ]);
        $this->logInAsSuperAdmin();

        $this->client->request('GET', '/admin/users/' . $user->getId());
        $this->client->submitForm($this->trans('admin.user.delete.confirm'));

        self::assertResponseRedirects('/admin/users');
        self::assertNull(UserFactory::repository()->findOneBy([
            'email' => 'jane@example.com',
        ]));
    }

    public function testADeletionNeedsItsCsrfToken(): void
    {
        $user = UserFactory::createOne();
        $this->logInAsSuperAdmin();

        $this->client->request('POST', '/admin/users/' . $user->getId() . '/delete', [
            '_token' => 'forged',
        ]);

        // An invalid CSRF token is an authentication error: Symfony sends to the login page.
        self::assertResponseRedirects('/login');
        self::assertNotNull(UserFactory::repository()->find($user->getId()));
    }

    public function testASuperAdminSendsAPasswordLink(): void
    {
        $user = UserFactory::createOne([
            'email' => 'jane@example.com',
        ]);
        $this->logInAsSuperAdmin();

        $this->client->request('GET', '/admin/users/' . $user->getId());
        $this->client->submitForm($this->trans('admin.user.edit.password_link'));

        self::assertResponseRedirects('/admin/users/' . $user->getId());
        self::assertEmailAddressContains($this->sentEmail(), 'to', 'jane@example.com');
    }

    public function testASuperAdminLogsInAsAUser(): void
    {
        $user = UserFactory::createOne([
            'email' => 'jane@example.com',
        ]);
        $this->logInAsSuperAdmin();

        $this->client->request('GET', '/admin/users/' . $user->getId());
        $this->client->clickLink($this->trans('admin.user.edit.impersonate'));
        $this->client->followRedirect();

        self::assertSelectorTextContains('main', $this->trans('nav.impersonating', [
            '%email%' => 'jane@example.com',
        ]));
        self::assertSelectorNotExists('a[href="/admin/users"]');

        $this->client->clickLink($this->trans('nav.exit_impersonation'));
        $this->client->followRedirect();
        self::assertSelectorExists('a[href="/admin/users"]');
    }

    private function logInAsSuperAdmin(): User
    {
        $superAdmin = UserFactory::new()->superAdmin()->create();
        $this->client->loginUser($superAdmin);

        return $superAdmin;
    }

    private function user(string $email): User
    {
        $user = UserFactory::repository()->findOneBy([
            'email' => $email,
        ]);
        self::assertNotNull($user);

        return $user;
    }
}
