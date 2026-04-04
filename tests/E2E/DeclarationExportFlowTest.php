<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\ValueObject\UserId;
use App\Tests\Integration\AuthenticatedWebTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * E2E: Declaration XML export happy path.
 *
 * Covers:
 * - Unauthenticated access redirects to /login
 * - Invalid year returns 404
 * - No data redirects to import
 * - With seeded data: 200 response, application/xml Content-Type, Content-Disposition attachment
 *
 * @group e2e
 */
final class DeclarationExportFlowTest extends AuthenticatedWebTestCase
{
    private const string TAX_YEAR = '2024';

    private const string FAKE_TRANSACTION_ID = '11111111-1111-1111-1111-111111111111';

    private const string FAKE_BATCH_ID = '22222222-2222-2222-2222-222222222222';

    public function testExportXmlRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', sprintf('/declaration/%s/export/xml', self::TAX_YEAR));

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testExportXmlWithInvalidYearReturns404(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/declaration/abcd/export/xml');

        self::assertResponseStatusCodeSame(404);
    }

    public function testExportXmlWithNoDataRedirectsToImport(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', sprintf('/declaration/%s/export/xml', self::TAX_YEAR));

        self::assertResponseRedirects();
        self::assertStringContainsString('/import', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testExportXmlWithDataReturnsXmlDownload(): void
    {
        $client = $this->createAuthenticatedClient();
        $this->seedUserProfile();
        $this->seedImportedTransaction();

        $client->request('GET', sprintf('/declaration/%s/export/xml', self::TAX_YEAR));

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);

        $response = $client->getResponse();

        self::assertStringContainsString(
            'application/xml',
            (string) $response->headers->get('Content-Type'),
            'Expected Content-Type: application/xml for XML export',
        );

        self::assertStringContainsString(
            'attachment',
            (string) $response->headers->get('Content-Disposition'),
            'Expected Content-Disposition: attachment for file download',
        );

        self::assertStringContainsString(
            sprintf('PIT-38_%s.xml', self::TAX_YEAR),
            (string) $response->headers->get('Content-Disposition'),
            'Expected filename PIT-38_{year}.xml in Content-Disposition',
        );

        $body = (string) $response->getContent();
        self::assertStringContainsString('<?xml', $body, 'Response body should be XML');
        self::assertStringContainsString('PIT-38', $body, 'XML body should reference PIT-38 declaration');
    }

    /**
     * Seeds NIP + first/last name for the test user via the ORM so that
     * EncryptedStringType encrypts the values correctly.
     * NIP 1234563218 is a valid checksum value used for testing purposes.
     */
    private function seedUserProfile(): void
    {
        /** @var UserRepositoryInterface $userRepository */
        $userRepository = self::getContainer()->get(UserRepositoryInterface::class);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $user = $userRepository->findById(UserId::fromString(self::TEST_USER_ID));
        self::assertNotNull($user, 'Test user must exist before seeding profile');

        $user->updateProfile('1234563218', 'Jan', 'Kowalski');
        $entityManager->flush();
    }

    /**
     * Inserts a minimal imported_transactions row so countByUser() > 0.
     * The transaction is a BUY (not a SELL), so it never triggers the payment gate.
     */
    private function seedImportedTransaction(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);

        $alreadyExists = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM imported_transactions WHERE id = :id',
            ['id' => self::FAKE_TRANSACTION_ID],
        );

        if ($alreadyExists > 0) {
            return;
        }

        $connection->insert('imported_transactions', [
            'id' => self::FAKE_TRANSACTION_ID,
            'user_id' => self::TEST_USER_ID,
            'import_batch_id' => self::FAKE_BATCH_ID,
            'broker_id' => 'revolut',
            'isin' => 'US0378331005',
            'symbol' => 'AAPL',
            'transaction_type' => 'BUY',
            'transaction_date' => sprintf('%s-06-15 10:00:00', self::TAX_YEAR),
            'quantity' => '1.00000000',
            'price_amount' => '150.00',
            'price_currency' => 'USD',
            'commission_amount' => '0.00',
            'commission_currency' => 'USD',
            'description' => 'E2E test transaction',
            'content_hash' => 'e2e-test-hash-export-' . self::FAKE_TRANSACTION_ID,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
