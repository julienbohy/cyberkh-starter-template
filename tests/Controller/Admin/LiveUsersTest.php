<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Security\Role;
use App\Tests\Controller\AbstractControllerTestCase;
use App\Tests\RealTime\HubSpy;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The list of the users refreshes when another super admin changes it.
 */
final class LiveUsersTest extends AbstractControllerTestCase
{
    private const string TOPIC = '/admin/users';

    public function testTheListSubscribesToItsTopic(): void
    {
        $this->client->loginUser(UserFactory::new()->superAdmin()->create());

        $this->client->request('GET', '/admin/users');

        self::assertSame([self::TOPIC], $this->subscribedTopics());
        self::assertSame([self::TOPIC], $this->mercureGrantedTopics());
    }

    public function testAPageWithoutTopicHasNoStream(): void
    {
        $this->client->loginUser(UserFactory::new()->superAdmin()->create());

        $this->client->request('GET', '/profile');

        self::assertSelectorNotExists('turbo-mercure-stream-source');
    }

    public function testInvitingAUserRefreshesTheList(): void
    {
        $this->client->loginUser(UserFactory::new()->superAdmin()->create());
        $this->client->request('GET', '/admin/users/new');

        $this->client->submitForm($this->trans('admin.user.new.submit'), [
            'invitation[email]' => 'jane@example.com',
        ]);

        self::assertResponseRedirects();
        $this->assertOneLiveRefresh(self::TOPIC);
    }

    public function testEditingAUserRefreshesTheList(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser(UserFactory::new()->superAdmin()->create());
        $this->client->request('GET', '/admin/users/' . $user->getId());

        $this->client->submitForm($this->trans('admin.user.edit.submit'), [
            'user[enabled]' => false,
        ]);

        self::assertResponseRedirects();
        $this->assertOneLiveRefresh(self::TOPIC);
    }

    public function testDeletingAUserRefreshesTheList(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser(UserFactory::new()->superAdmin()->create());
        $this->client->request('GET', '/admin/users/' . $user->getId());

        $this->client->submitForm($this->trans('admin.user.delete.confirm'));

        self::assertResponseRedirects('/admin/users');
        $this->assertOneLiveRefresh(self::TOPIC);
    }

    public function testAFailingHubDoesNotBreakTheAction(): void
    {
        $user = UserFactory::createOne([
            'email' => 'jane@example.com',
        ]);
        $this->client->loginUser(UserFactory::new()->superAdmin()->create());
        $this->client->request('GET', '/admin/users/' . $user->getId());
        HubSpy::failNextPublish();

        $this->client->submitForm($this->trans('admin.user.delete.confirm'));

        self::assertResponseRedirects('/admin/users');
        self::assertNull(UserFactory::repository()->findOneBy([
            'email' => 'jane@example.com',
        ]));
    }

    public function testADemotedSuperAdminGetsNothingOnRefresh(): void
    {
        $superAdmin = UserFactory::new()->superAdmin()->create();
        $this->client->loginUser($superAdmin);
        $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->find(User::class, $superAdmin->getId());
        self::assertInstanceOf(User::class, $user);
        $user->setGrantedRoles([Role::Admin]);
        $entityManager->flush();

        $this->client->request('GET', '/admin/users');

        self::assertSelectorNotExists('turbo-mercure-stream-source');
        self::assertFalse($this->client->getResponse()->isSuccessful());
    }

    public function testTheSearchSurvivesARefresh(): void
    {
        $this->client->loginUser(UserFactory::new()->superAdmin()->create());

        $this->client->request('GET', '/admin/users', [
            'q' => 'jane',
        ]);

        self::assertInputValueSame('q', 'jane');
        self::assertSelectorExists('form#admin-user-search[data-turbo-permanent][data-controller="permanent-form"]');
    }
}
