<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\Service;

use App\BrokerImport\Application\Port\BrokerAdapterRequestPort;
use App\Shared\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Uid\Uuid;

/**
 * Stores an unrecognized broker file to the database and notifies the admin by email.
 *
 * Triggered automatically when no adapter recognizes the uploaded file.
 * The stored record allows the team to inspect real-world export formats
 * and prioritize new broker adapter implementations.
 */
final readonly class BrokerAdapterRequestService implements BrokerAdapterRequestPort
{
    public function __construct(
        private Connection $connection,
        private MailerInterface $mailer,
        private string $senderEmail,
        private string $recipientEmail,
    ) {
    }

    public function submit(UserId $userId, string $filename, string $fileContent): void
    {
        $id = Uuid::v4()->toRfc4122();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->insert('broker_adapter_request', [
            'id' => $id,
            'user_id' => $userId->toString(),
            'filename' => $filename,
            'file_content' => $fileContent,
            'file_size' => strlen($fileContent),
            'status' => 'pending',
            'created_at' => $now,
        ]);

        $this->sendNotification($id, $userId, $filename, $fileContent);
    }

    public function deleteByUser(UserId $userId): void
    {
        $this->connection->delete('broker_adapter_request', ['user_id' => $userId->toString()]);
    }

    private function sendNotification(
        string $requestId,
        UserId $userId,
        string $filename,
        string $fileContent,
    ): void {
        $message = (new Email())
            ->from($this->senderEmail)
            ->to($this->recipientEmail)
            ->subject(sprintf('[TaxPilot] Nieznany format brokera: %s', $filename))
            ->text(sprintf(
                "Uzytkownik przeslal plik w nierozpoznanym formacie.\n\n"
                . "Request ID: %s\n"
                . "User ID:    %s\n"
                . "Plik:       %s\n"
                . "Rozmiar:    %s bajtow\n",
                $requestId,
                $userId->toString(),
                $filename,
                number_format(strlen($fileContent)),
            ))
            ->addPart((new DataPart($fileContent, $filename)));

        $this->mailer->send($message);
    }
}
