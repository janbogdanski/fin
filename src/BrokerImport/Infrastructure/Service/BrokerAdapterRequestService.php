<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Service;

use App\BrokerImport\Application\Port\BrokerAdapterRequestPort;
use App\Shared\Domain\Port\GdprDataErasurePort;
use App\Shared\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
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
 *
 * Retention: records expire after 90 days (expires_at column).
 * Data classification: financial PII — PostgreSQL-only (BYTEA).
 */
final readonly class BrokerAdapterRequestService implements BrokerAdapterRequestPort, GdprDataErasurePort
{
    private const MAX_EMAIL_ATTACHMENT_BYTES = 7_000_000;

    public function __construct(
        private Connection $connection,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $senderEmail,
        private string $recipientEmail,
    ) {
    }

    public function submit(UserId $userId, string $filename, string $fileContent): void
    {
        $id = Uuid::v4()->toRfc4122();
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify('+90 days');

        $this->connection->insert('broker_adapter_request', [
            'id' => $id,
            'user_id' => $userId->toString(),
            'filename' => $filename,
            'file_content' => $fileContent,
            'file_size' => strlen($fileContent),
            'status' => 'pending',
            'created_at' => $now->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        try {
            $this->sendNotification($id, $userId, $filename, $fileContent);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send admin notification for broker adapter request', [
                'request_id' => $id,
                'user_id' => $userId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleteByUser(UserId $userId): void
    {
        $this->connection->delete('broker_adapter_request', [
            'user_id' => $userId->toString(),
        ]);
    }

    private function sendNotification(
        string $requestId,
        UserId $userId,
        string $filename,
        string $fileContent,
    ): void {
        $safeSubjectFilename = preg_replace('/[\r\n\0]/', '', mb_substr($filename, 0, 100)) ?? $filename;
        $tooLarge = strlen($fileContent) > self::MAX_EMAIL_ATTACHMENT_BYTES;

        $textBody = sprintf(
            "Uzytkownik przeslal plik w nierozpoznanym formacie.\n\n"
            . "Request ID: %s\n"
            . "User ID:    %s\n"
            . "Plik:       %s\n"
            . "Rozmiar:    %s bajtow\n",
            $requestId,
            $userId->toString(),
            $filename,
            number_format(strlen($fileContent)),
        );

        if ($tooLarge) {
            $textBody .= "\nPlik zbyt duzy do przesylki emailem — pobierz z bazy danych po Request ID.";
        }

        $message = (new Email())
            ->from($this->senderEmail)
            ->to($this->recipientEmail)
            ->subject(sprintf('[TaxPilot] Nieznany format brokera: %s', $safeSubjectFilename))
            ->text($textBody);

        if (! $tooLarge) {
            $message->addPart(new DataPart($fileContent, $filename));
        }

        $this->mailer->send($message);
    }
}
