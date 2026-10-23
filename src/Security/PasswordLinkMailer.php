<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\ResetPasswordRequestRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelper;

/**
 * Emails a link to choose a password: asked by the user (forgotten password), or sent by a super
 * admin (invitation). Both lead to the page of the password reset.
 */
final readonly class PasswordLinkMailer
{
    /**
     * The lifetime of an invitation link, in seconds: 3 days.
     */
    private const int INVITATION_LIFETIME = 259200;

    public function __construct(
        // The class, not its interface: only the class takes the lifetime of a link, for now.
        #[Autowire(service: 'symfonycasts.reset_password.helper')]
        private ResetPasswordHelper $resetPasswordHelper,
        private ResetPasswordRequestRepository $resetPasswordRequestRepository,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @throws ResetPasswordExceptionInterface when the user asked for a link too recently
     */
    public function sendResetLink(User $user): ResetPasswordToken
    {
        $token = $this->resetPasswordHelper->generateResetToken($user);
        $this->send($user, $token, 'reset_password.email.subject', 'reset_password/email.html.twig');

        return $token;
    }

    /**
     * Replaces the links not used yet, so that a super admin can send one again at any time.
     */
    public function sendInvitation(User $user): void
    {
        $this->resetPasswordRequestRepository->removeRequests($user);
        $token = $this->resetPasswordHelper->generateResetToken($user, self::INVITATION_LIFETIME);
        $this->send($user, $token, 'admin.user.invitation_email.subject', 'admin/user/invitation_email.html.twig');
    }

    private function send(User $user, ResetPasswordToken $token, string $subject, string $template): void
    {
        $this->mailer->send(
            new TemplatedEmail()
                ->to($user->getEmail())
                ->subject($this->translator->trans($subject))
                ->htmlTemplate($template)
                ->context([
                    'resetToken' => $token,
                ]),
        );
    }
}
