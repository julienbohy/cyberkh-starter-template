<?php

declare(strict_types=1);

namespace App\Mailer;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Starts an email in the language of its recipient: the subject is translated here, and the body
 * is rendered in that language too (TemplatedEmail::locale()), even later by the messenger worker.
 * The language of whoever triggered the email does not matter.
 */
final readonly class RecipientEmails
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param array<string, string> $subjectParameters
     */
    public function create(User $recipient, string $subject, array $subjectParameters = []): TemplatedEmail
    {
        $locale = $recipient->getLanguage()->value;

        return new TemplatedEmail()
            ->to($recipient->getEmail())
            ->locale($locale)
            ->subject($this->translator->trans($subject, $subjectParameters, locale: $locale));
    }
}
