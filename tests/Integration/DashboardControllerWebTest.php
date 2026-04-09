<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Integration tests for DashboardController.
 *
 * Tests the empty state (no imported transactions) which should render
 * successfully with zero-value summaries.
 */
final class DashboardControllerWebTest extends AuthenticatedWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        $connection->delete('closed_positions', [
            'user_id' => self::TEST_USER_ID,
        ]);
        $connection->delete('imported_transactions', [
            'user_id' => self::TEST_USER_ID,
        ]);

        self::ensureKernelShutdown();
    }

    public function testDashboardIndexReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
    }

    public function testDashboardCalculationReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/dashboard/calculation/2025');

        self::assertResponseIsSuccessful();
    }

    public function testDashboardFifoReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/dashboard/fifo/2025');

        self::assertResponseIsSuccessful();
    }

    public function testDashboardDividendsReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/dashboard/dividends/2025');

        self::assertResponseIsSuccessful();
    }

    public function testDashboardCalculationWithDifferentYearReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/dashboard/calculation/2024');

        self::assertResponseIsSuccessful();
    }

    public function testDashboardIndexShowsEmptyStateMessage(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();

        // Empty state: no transactions imported yet.
        // Template renders "Brak danych" heading and a prompt to upload a broker file.
        $pageText = $crawler->text();
        self::assertStringContainsString(
            'Brak danych',
            $pageText,
            'Dashboard empty state should display "Brak danych" heading',
        );
        self::assertStringContainsString(
            'Wgraj plik brokera',
            $pageText,
            'Dashboard empty state should prompt the user to upload a broker file',
        );
    }

    public function testDashboardFifoShowsSourceTradePricesAndCurrencies(): void
    {
        $client = $this->createAuthenticatedClient();
        $this->seedImportedTransactionsAndClosedPosition();

        $crawler = $client->request('GET', '/dashboard/fifo/2025');

        self::assertResponseIsSuccessful();

        $pageText = $crawler->text();
        self::assertStringContainsString('AAPL', $pageText);
        self::assertStringContainsString('US0378331005', $pageText);
        self::assertStringContainsString('Broker kupna', $pageText);
        self::assertStringContainsString('Broker sprzedazy', $pageText);
        self::assertStringContainsString('Waluta kupna', $pageText);
        self::assertStringContainsString('Waluta sprzedazy', $pageText);
        self::assertStringContainsString('170.25', $pageText);
        self::assertStringContainsString('195.10', $pageText);
        self::assertStringContainsString('USD', $pageText);
        self::assertStringContainsString('Kazdy wiersz to jedno sparowanie FIFO', $pageText);
    }

    private function seedImportedTransactionsAndClosedPosition(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);

        $buyTransactionId = '10000000-0000-0000-0000-000000000001';
        $sellTransactionId = '10000000-0000-0000-0000-000000000002';
        $importBatchId = '20000000-0000-0000-0000-000000000001';

        $connection->insert('imported_transactions', [
            'id' => $buyTransactionId,
            'user_id' => self::TEST_USER_ID,
            'import_batch_id' => $importBatchId,
            'broker_id' => 'ibkr',
            'isin' => 'US0378331005',
            'symbol' => 'AAPL',
            'transaction_type' => 'BUY',
            'transaction_date' => '2025-03-14 15:30:00',
            'quantity' => '10.00000000',
            'price_amount' => '170.25',
            'price_currency' => 'USD',
            'commission_amount' => '1.00',
            'commission_currency' => 'USD',
            'description' => 'Dashboard FIFO test buy',
            'content_hash' => 'dashboard-fifo-buy',
            'created_at' => '2025-03-14 15:35:00',
        ]);

        $connection->insert('imported_transactions', [
            'id' => $sellTransactionId,
            'user_id' => self::TEST_USER_ID,
            'import_batch_id' => $importBatchId,
            'broker_id' => 'degiro',
            'isin' => 'US0378331005',
            'symbol' => 'AAPL',
            'transaction_type' => 'SELL',
            'transaction_date' => '2025-09-19 15:30:00',
            'quantity' => '10.00000000',
            'price_amount' => '195.10',
            'price_currency' => 'USD',
            'commission_amount' => '1.00',
            'commission_currency' => 'USD',
            'description' => 'Dashboard FIFO test sell',
            'content_hash' => 'dashboard-fifo-sell',
            'created_at' => '2025-09-19 15:35:00',
        ]);

        $connection->insert('closed_positions', [
            'user_id' => self::TEST_USER_ID,
            'tax_category' => 'EQUITY',
            'buy_transaction_id' => $buyTransactionId,
            'sell_transaction_id' => $sellTransactionId,
            'isin' => 'US0378331005',
            'quantity' => '10.00000000',
            'cost_basis_pln' => '6885.00',
            'proceeds_pln' => '7900.00',
            'buy_commission_pln' => '4.05',
            'sell_commission_pln' => '3.95',
            'gain_loss_pln' => '1007.00',
            'buy_date' => '2025-03-14 15:30:00',
            'sell_date' => '2025-09-19 15:30:00',
            'buy_nbp_rate_currency' => 'USD',
            'buy_nbp_rate_value' => '4.0500',
            'buy_nbp_rate_date' => '2025-03-13',
            'buy_nbp_rate_table' => '051/A/NBP/2025',
            'sell_nbp_rate_currency' => 'USD',
            'sell_nbp_rate_value' => '3.9500',
            'sell_nbp_rate_date' => '2025-09-18',
            'sell_nbp_rate_table' => '181/A/NBP/2025',
            'buy_broker' => 'ibkr',
            'sell_broker' => 'degiro',
        ]);
    }
}
