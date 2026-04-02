<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer;

use App\Identity\Application\Port\MagicLinkMailerPort;
use App\Identity\Domain\Model\MagicLinkToken;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SymfonyMagicLinkMailer implements MagicLinkMailerPort
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $senderEmail,
    ) {
    }

    public function sendMagicLink(string $email, MagicLinkToken $token): void
    {
        $verifyUrl = $this->urlGenerator->generate(
            'auth_verify',
            [
                'token' => $token->token(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $message = (new Email())
            ->from($this->senderEmail)
            ->to($email)
            ->subject('Zaloguj sie do TaxPilot')
            ->html(sprintf(
                '<p>Kliknij ponizszy link, aby sie zalogowac:</p>'
                . '<p><a href="%s">Zaloguj sie do TaxPilot</a></p>'
                . '<p>Link wygasa za 15 minut.</p>'
                . '<p>Jesli nie prosilesz o logowanie, zignoruj ten e-mail.</p>',
                htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8'),
            ));

        $this->mailer->send($message);
    }
}
