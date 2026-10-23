<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The CSRF field of every form keeps data-controller="csrf-protection" through the form theme:
 * without it, the browser sends no double-submit token, and Symfony refuses the forms that come
 * after the login, which sent one.
 */
final class CsrfProtectionTest extends AbstractControllerTestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function forms(): iterable
    {
        yield 'registration' => ['/register', 'registration_form', false];
        yield 'password reset request' => ['/reset-password', 'reset_password_request_form', false];
        yield 'photo of the profile' => ['/profile', 'avatar', true];
        yield 'password of the profile' => ['/profile', 'change_own_password', true];
        yield 'invitation' => ['/admin/users/new', 'invitation', true];
    }

    #[DataProvider('forms')]
    public function testTheCsrfFieldCarriesItsController(string $url, string $form, bool $signedIn): void
    {
        if ($signedIn) {
            $this->client->loginUser(UserFactory::new()->superAdmin()->create());
        }

        $this->client->request('GET', $url);

        self::assertSelectorExists(\sprintf('input[name="%s[_token]"][data-controller="csrf-protection"]', $form));
    }
}
