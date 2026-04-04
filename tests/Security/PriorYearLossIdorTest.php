<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Identity\Infrastructure\Security\SecurityUser;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * P2-109 — IDOR: user A must not be able to delete user B's PriorYearLoss.
 *
 * The repository's delete() uses "WHERE id = :id AND user_id = :userId",
 * so a cross-user delete is a silent no-op.  This test verifies the record
 * survives and the response is a normal redirect (not an error 500).
 *
 * @group security
 */
final class PriorYearLossIdorTest extends WebTestCase
{
    private const string USER_A_ID = '00000000-0000-0000-0009-000000000001';

    private const string USER_A_EMAIL = 'user-a-idor@example.com';

    private const string USER_B_ID = '00000000-0000-0000-0009-000000000002';

    private const string USER_B_EMAIL = 'user-b-idor@example.com';

    private const string USER_B_LOSS_ID = '00000000-0000-0000-0009-000000000099';

    public function testUserACannotDeleteUserBLoss(): void
    {
        $client = self::createClient();
        $container = self::getContainer();

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $this->seedUsers($connection);
        $this->seedLossForUserB($connection);

        // Authenticate as User A
        $client->loginUser(new SecurityUser(self::USER_A_ID, self::USER_A_EMAIL));

        // Obtain a valid CSRF token for the delete action (token id is "losses_delete_{id}")
        // We load /losses to warm up the session, then inject the token directly.
        $client->request('GET', '/losses');

        $knownToken = 'test-csrf-losses-delete-idor';
        $session = $client->getRequest()->getSession();
        $csrfKey = '_csrf/losses_delete_' . self::USER_B_LOSS_ID;
        $session->set($csrfKey, $knownToken);
        $session->save();

        // User A attempts to delete User B's loss
        $client->request(
            'POST',
            '/losses/' . self::USER_B_LOSS_ID . '/delete',
            ['_token' => $knownToken],
        );

        // Response must be a redirect (not 500 or 403)
        self::assertResponseRedirects('/losses');

        // The loss record owned by User B must still exist in the database
        $count = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM prior_year_losses WHERE id = :id AND user_id = :userId',
            ['id' => self::USER_B_LOSS_ID, 'userId' => self::USER_B_ID],
        );

        self::assertSame(
            1,
            $count,
            'User B\'s loss record must not be deleted by User A (IDOR prevented).',
        );
    }

    private function seedUsers(Connection $connection): void
    {
        foreach ([
            [self::USER_A_ID, self::USER_A_EMAIL, 'IDOR-A001'],
            [self::USER_B_ID, self::USER_B_EMAIL, 'IDOR-B002'],
        ] as [$id, $email, $referral]) {
            $exists = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM users WHERE id = :id',
                ['id' => $id],
            );

            if ($exists === 0) {
                $connection->insert('users', [
                    'id' => $id,
                    'email' => $email,
                    'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'referral_code' => $referral,
                    'bonus_transactions' => 0,
                ]);
            }
        }
    }

    private function seedLossForUserB(Connection $connection): void
    {
        $exists = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM prior_year_losses WHERE id = :id',
            ['id' => self::USER_B_LOSS_ID],
        );

        if ($exists === 0) {
            $connection->insert('prior_year_losses', [
                'id' => self::USER_B_LOSS_ID,
                'user_id' => self::USER_B_ID,
                'loss_year' => 2023,
                'tax_category' => 'EQUITY',
                'original_amount' => '5000.00',
                'remaining_amount' => '5000.00',
                'used_in_years' => '[]',
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }
}
