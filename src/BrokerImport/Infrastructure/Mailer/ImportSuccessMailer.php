<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Mailer;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class ImportSuccessMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $senderEmail,
    ) {
    }

    public function sendImportSuccess(
        string $userEmail,
        int $importedCount,
        string $brokerDisplayName,
        int $totalTransactionCount,
        int $brokerCount,
    ): void {
        $brokerLabel = $brokerCount === 1 ? 'brokera' : 'brokerow';

        $message = (new Email())
            ->from($this->senderEmail)
            ->to($userEmail)
            ->subject(sprintf('[TaxPilot] Import z %s gotowy — %d transakcji', $brokerDisplayName, $importedCount))
            ->text(sprintf(
                "Import zostal pomyslnie zakonczony.\n\n"
                . "Broker:          %s\n"
                . "Nowe transakcje: %d\n"
                . "Lacznie w bazie: %d transakcji z %d %s\n\n"
                . "Zaloguj sie na taxpilot.pl, aby wyliczyc podatek.\n",
                $brokerDisplayName,
                $importedCount,
                $totalTransactionCount,
                $brokerCount,
                $brokerLabel,
            ));

        $this->mailer->send($message);
    }
}
