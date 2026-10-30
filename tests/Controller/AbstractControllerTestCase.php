<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\RealTime\HubSpy;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The pages are asserted through their translation keys, not their texts.
 */
abstract class AbstractControllerTestCase extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // The recorded updates are static: they would leak from one test into the next.
        HubSpy::reset();
    }

    /**
     * @param array<string, string> $parameters
     */
    protected function trans(string $id, array $parameters = [], string $domain = 'messages', string $locale = 'en'): string
    {
        // Explicit: a request switches the locale of the translator, and it stays switched after it.
        return static::getContainer()->get(TranslatorInterface::class)->trans($id, $parameters, $domain, $locale);
    }

    protected function logIn(string $email, string $password): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm($this->trans('login.submit'), [
            '_username' => $email,
            '_password' => $password,
        ]);
    }

    /**
     * The only email sent, so far, by the current test.
     */
    protected function sentEmail(): Email
    {
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }

    /**
     * The first link of the email whose URL contains $path.
     */
    protected function linkOf(Email $email, string $path): string
    {
        self::assertSame(1, preg_match('#href="(http://localhost' . preg_quote($path, '#') . '[^"]*)"#', (string) $email->getHtmlBody(), $matches));

        return html_entity_decode($matches[1]);
    }
}
