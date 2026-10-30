<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\UserFactory;
use App\Preference\Language;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

/**
 * Every email shares one layout, whose styles must reach the clients that drop style tags.
 */
final class EmailLayoutTest extends AbstractControllerTestCase
{
    public function testAnEmailIsBrandedInTheLanguageOfItsRecipient(): void
    {
        UserFactory::createOne([
            'email' => 'jane@example.com',
            'language' => Language::French,
        ]);

        $this->requestReset('jane@example.com');

        $html = (string) $this->sentEmail()->getHtmlBody();
        self::assertStringContainsString('<html lang="fr"', $html);
        self::assertStringContainsString($this->trans('app.name'), $html);
        self::assertStringContainsString($this->trans('email.tagline', locale: 'fr'), $html);
        self::assertStringContainsString($this->trans('reset_password.email.heading', locale: 'fr'), html_entity_decode($html));
    }

    public function testTheStylesAreInlined(): void
    {
        UserFactory::createOne([
            'email' => 'jane@example.com',
        ]);

        $this->requestReset('jane@example.com');

        $html = (string) $this->sentEmail()->getHtmlBody();
        self::assertMatchesRegularExpression('/<h1[^>]+style="[^"]*color:\s*#061415/', $html);
        self::assertMatchesRegularExpression('/<a[^>]+class="button"[^>]+style="[^"]*color:\s*#ffffff/', $html);
        self::assertStringNotContainsString('<style', $html);
    }

    public function testTheActionLinkIsAButtonThatWorksWithoutStyleSupport(): void
    {
        UserFactory::createOne([
            'email' => 'jane@example.com',
        ]);

        $this->requestReset('jane@example.com');

        $email = $this->sentEmail();
        self::assertStringContainsString('bgcolor="#007275"', (string) $email->getHtmlBody());
        self::assertStringContainsString('/reset-password/reset/', $this->linkOf($email, '/reset-password/reset/'));
    }

    public function testTheLayoutHoldsInClientsThatIgnorePartsOfCss(): void
    {
        UserFactory::createOne([
            'email' => 'jane@example.com',
        ]);

        $this->requestReset('jane@example.com');

        $html = (string) $this->sentEmail()->getHtmlBody();
        // Outlook on Windows ignores max-width, and Gmail may drop the styles of the body.
        self::assertStringContainsString('<!--[if mso]><table role="presentation" width="600"', $html);
        self::assertMatchesRegularExpression('/<p[^>]+style="[^"]*font-family:/', $html);
        self::assertDoesNotMatchRegularExpression('/class="button-wrapper"[^>]+margin/', $html);
    }

    public function testTheTextVersionKeepsTheLinkAndDropsTheLayoutMarkup(): void
    {
        UserFactory::createOne([
            'email' => 'jane@example.com',
            'language' => Language::French,
        ]);

        $this->requestReset('jane@example.com');

        $email = $this->sentEmail();
        $text = (string) $email->getTextBody();
        self::assertStringContainsString($this->trans('reset_password.email.heading', locale: 'fr'), $text);
        self::assertStringContainsString($this->linkOf($email, '/reset-password/reset/'), $text);
        self::assertStringNotContainsString('<', $text);
        self::assertStringNotContainsString('{', $text);
        self::assertStringNotContainsString('&#', $text);
        self::assertMatchesRegularExpression('/^' . preg_quote($this->trans('email.tagline', locale: 'fr'), '/') . '$/m', $text);
    }

    public function testInliningKeepsWhatAUserWroteEscaped(): void
    {
        $email = new TemplatedEmail()
            ->htmlTemplate('email_with_user_input.html.twig')
            ->context([
                'value' => '<script>alert(1)</script>',
            ]);

        self::getContainer()->get('twig.mime_body_renderer')->render($email);

        $html = (string) $email->getHtmlBody();
        self::assertStringNotContainsString('<script', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testTheLanguageOfTheRequestDoesNotReachTheEmail(): void
    {
        UserFactory::createOne([
            'email' => 'jane@example.com',
            'language' => Language::English,
        ]);

        $this->client->request('GET', '/reset-password', server: [
            'HTTP_ACCEPT_LANGUAGE' => 'fr',
        ]);
        $this->client->submitForm($this->trans('reset_password.request.submit', locale: 'fr'), [
            'reset_password_request_form[email]' => 'jane@example.com',
        ]);

        self::assertStringContainsString('<html lang="en"', (string) $this->sentEmail()->getHtmlBody());
        self::assertSame($this->trans('reset_password.email.subject'), $this->sentEmail()->getSubject());
    }

    private function requestReset(string $email): void
    {
        $this->client->request('GET', '/reset-password');
        $this->client->submitForm($this->trans('reset_password.request.submit'), [
            'reset_password_request_form[email]' => $email,
        ]);
    }
}
