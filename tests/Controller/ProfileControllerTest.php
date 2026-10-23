<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Factory\UserFactory;
use League\Flysystem\FilesystemOperator;

final class ProfileControllerTest extends AbstractControllerTestCase
{
    private const string NEW_PASSWORD = 'Un-Nouveau-Mot-De-Passe-2026';

    /**
     * @var list<string>
     */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function testTheProfileIsForTheSignedInUsers(): void
    {
        $this->client->request('GET', '/profile');

        self::assertResponseRedirects('/login');
    }

    public function testAUserUploadsTheirPhoto(): void
    {
        $user = $this->logInAsUser();

        $this->uploadAvatar($this->image(300, 200, 'png'));

        self::assertResponseRedirects('/profile');
        $photo = $this->user($user)->getAvatarName();
        self::assertNotNull($photo);
        self::assertTrue($this->storage('avatars.storage')->fileExists($photo));

        $this->client->request('GET', '/users/' . $user->getId() . '/avatar/small?v=' . $photo);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        self::assertTrue($this->storage('avatars_cache.storage')->fileExists('avatar_small/' . $photo . '.webp'));
    }

    public function testANewPhotoReplacesTheFormerOneAndItsThumbnails(): void
    {
        $user = $this->logInAsUser();
        $this->uploadAvatar($this->image(200, 200, 'png'));
        $former = $this->user($user)->getAvatarName();
        self::assertNotNull($former);
        $this->client->request('GET', '/users/' . $user->getId() . '/avatar/small');

        $this->uploadAvatar($this->image(200, 200, 'jpeg'));

        self::assertNotSame($former, $this->user($user)->getAvatarName());
        self::assertFalse($this->storage('avatars.storage')->fileExists($former));
        self::assertFalse($this->storage('avatars_cache.storage')->fileExists('avatar_small/' . $former . '.webp'));
    }

    public function testAPhotoTooSmallIsRefused(): void
    {
        $user = $this->logInAsUser();

        $this->uploadAvatar($this->image(64, 64, 'png'));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-slot=form-message]', $this->trans('avatar.too_small', [], 'validators'));
        self::assertNull($this->user($user)->getAvatarName());
    }

    public function testAFileOtherThanAnImageIsRefused(): void
    {
        $this->logInAsUser();
        $file = $this->file('not-an-image.png', 'Hello');

        $this->uploadAvatar($file);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-slot=form-message]');
    }

    public function testAUserDeletesTheirPhoto(): void
    {
        $user = $this->logInAsUser();
        $this->uploadAvatar($this->image(200, 200, 'png'));
        $photo = $this->user($user)->getAvatarName();
        self::assertNotNull($photo);

        $this->client->request('GET', '/profile');
        $this->client->submitForm($this->trans('profile.avatar.delete'));

        self::assertResponseRedirects('/profile');
        self::assertNull($this->user($user)->getAvatarName());
        self::assertFalse($this->storage('avatars.storage')->fileExists($photo));
        $this->client->request('GET', '/users/' . $user->getId() . '/avatar/small');
        self::assertResponseStatusCodeSame(404);
    }

    public function testThePhotosAreForTheSignedInUsers(): void
    {
        $user = $this->logInAsUser();
        $this->uploadAvatar($this->image(200, 200, 'png'));
        $this->client->request('GET', '/logout');
        $this->client->getCookieJar()->clear();

        $this->client->request('GET', '/users/' . $user->getId() . '/avatar/small');

        self::assertResponseRedirects('/login');
    }

    public function testAUserChangesTheirPasswordAndStaysLoggedIn(): void
    {
        $user = $this->logInAsUser();

        $this->changePassword('not-the-password');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-slot=form-message]', $this->trans('password.current_wrong', [], 'validators'));

        $this->changePassword(UserFactory::DEFAULT_PASSWORD);
        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/logout');
        $this->client->getCookieJar()->clear();
        $this->logIn($user->getEmail(), self::NEW_PASSWORD);
        self::assertResponseRedirects('/');
    }

    private function logInAsUser(): User
    {
        $user = UserFactory::createOne();
        $this->logIn($user->getEmail(), UserFactory::DEFAULT_PASSWORD);

        return $user;
    }

    private function uploadAvatar(string $file): void
    {
        $crawler = $this->client->request('GET', '/profile');
        $form = $crawler->selectButton($this->trans('profile.avatar.submit'))->form();
        $field = $form['avatar[avatarFile]'];
        self::assertInstanceOf(\Symfony\Component\DomCrawler\Field\FileFormField::class, $field);
        $field->upload($file);
        $this->client->submit($form);
    }

    private function changePassword(string $currentPassword): void
    {
        $this->client->request('GET', '/profile');
        $this->client->submitForm($this->trans('profile.password.submit'), [
            'change_own_password[currentPassword]' => $currentPassword,
            'change_own_password[plainPassword][first]' => self::NEW_PASSWORD,
            'change_own_password[plainPassword][second]' => self::NEW_PASSWORD,
        ]);
    }

    /**
     * Loaded again: the kernel of the client reboots at each request, with a new entity manager.
     */
    private function user(User $user): User
    {
        $user = UserFactory::repository()->find($user->getId());
        self::assertNotNull($user);

        return $user;
    }

    private function storage(string $name): FilesystemOperator
    {
        $storage = static::getContainer()->get($name);
        self::assertInstanceOf(FilesystemOperator::class, $storage);

        return $storage;
    }

    /**
     * A plain image of $width × $height, as a PNG or a JPEG file.
     *
     * @param positive-int $width
     * @param positive-int $height
     */
    private function image(int $width, int $height, string $format): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 0, 114, 117));
        ob_start();
        $format === 'png' ? imagepng($image) : imagejpeg($image);

        return $this->file('photo.' . ($format === 'png' ? 'png' : 'jpg'), (string) ob_get_clean());
    }

    private function file(string $name, string $content): string
    {
        $file = sys_get_temp_dir() . '/' . uniqid('', true) . '-' . $name;
        file_put_contents($file, $content);
        $this->files[] = $file;

        return $file;
    }
}
