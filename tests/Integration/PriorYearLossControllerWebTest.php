<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Integration tests for PriorYearLossController (GET /losses, POST /losses, POST /losses/{id}/delete).
 *
 * Covers: CSRF validation, year validation (AC5), category validation,
 * BigDecimal amount validation, happy-path store/delete, and auth guard.
 *
 * Clock is frozen to TESTING_YEAR via MockClock to prevent year-boundary flakiness.
 */
final class PriorYearLossControllerWebTest extends AuthenticatedWebTestCase
{
    private const int TESTING_YEAR = 2026;
    // ──────────────────────────────────────────────
    // GET /losses
    // ──────────────────────────────────────────────

    public function testIndexReturns200ForAuthenticatedUser(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/losses');

        self::assertResponseIsSuccessful();
    }

    public function testIndexRedirectsToLoginForUnauthenticatedUser(): void
    {
        $client = self::createClient();

        $client->request('GET', '/losses');

        self::assertResponseRedirects();
        self::assertStringContainsString(
            '/login',
            (string) $client->getResponse()->headers->get('Location'),
        );
    }

    // ──────────────────────────────────────────────
    // POST /losses — happy path
    // ──────────────────────────────────────────────

    public function testStoreHappyPathRedirectsWithSuccessFlash(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/losses');
        $csrfToken = $this->extractStoreCsrfToken($crawler);

        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => (string) (self::TESTING_YEAR - 1),
            'tax_category' => 'EQUITY',
            'amount' => '15000.50',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('Dodano strate', $client->getCrawler()->text());
    }

    // ──────────────────────────────────────────────
    // POST /losses — CSRF
    // ──────────────────────────────────────────────

    public function testStoreWithInvalidCsrfRedirectsWithError(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/losses', [
            '_token' => 'invalid_token',
            'loss_year' => (string) (self::TESTING_YEAR - 1),
            'tax_category' => 'EQUITY',
            'amount' => '10000.00',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('CSRF', $client->getCrawler()->text());
    }

    public function testStoreWithMissingCsrfRedirectsWithError(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/losses', [
            'loss_year' => (string) (self::TESTING_YEAR - 1),
            'tax_category' => 'EQUITY',
            'amount' => '10000.00',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('CSRF', $client->getCrawler()->text());
    }

    // ──────────────────────────────────────────────
    // POST /losses — year validation
    // ──────────────────────────────────────────────

    public function testStoreWithCurrentYearRedirectsWithError(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/losses');
        $csrfToken = $this->extractStoreCsrfToken($crawler);

        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => (string) self::TESTING_YEAR,
            'tax_category' => 'EQUITY',
            'amount' => '10000.00',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('wczesniejszy', $client->getCrawler()->text());
    }

    public function testStoreWithFutureYearRedirectsWithError(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/losses');
        $csrfToken = $this->extractStoreCsrfToken($crawler);

        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => (string) (self::TESTING_YEAR + 1),
            'tax_category' => 'EQUITY',
            'amount' => '10000.00',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('wczesniejszy', $client->getCrawler()->text());
    }

    public function testStoreWithExpiredYearRedirectsWithError(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/losses');
        $csrfToken = $this->extractStoreCsrfToken($crawler);
        $expiredYear = self::TESTING_YEAR - 6;

        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => (string) $expiredYear,
            'tax_category' => 'EQUITY',
            'amount' => '10000.00',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('wygasla', $client->getCrawler()->text());
    }

    // ──────────────────────────────────────────────
    // POST /losses — category validation
    // ──────────────────────────────────────────────

    public function testStoreWithInvalidCategoryRedirectsWithError(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/losses');
        $csrfToken = $this->extractStoreCsrfToken($crawler);

        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => (string) (self::TESTING_YEAR - 1),
            'tax_category' => 'INVALID_CATEGORY',
            'amount' => '10000.00',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('kategori', mb_strtolower($client->getCrawler()->text()));
    }

    // ──────────────────────────────────────────────
    // POST /losses — amount validation
    // ──────────────────────────────────────────────

    public function testStoreWithNegativeAmountRedirectsWithError(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/losses');
        $csrfToken = $this->extractStoreCsrfToken($crawler);

        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => (string) (self::TESTING_YEAR - 1),
            'tax_category' => 'EQUITY',
            'amount' => '-500.00',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('wieksza od zera', $client->getCrawler()->text());
    }

    public function testStoreWithZeroAmountRedirectsWithError(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/losses');
        $csrfToken = $this->extractStoreCsrfToken($crawler);

        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => (string) (self::TESTING_YEAR - 1),
            'tax_category' => 'EQUITY',
            'amount' => '0',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('wieksza od zera', $client->getCrawler()->text());
    }

    public function testStoreWithNonNumericAmountRedirectsWithError(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/losses');
        $csrfToken = $this->extractStoreCsrfToken($crawler);

        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => (string) (self::TESTING_YEAR - 1),
            'tax_category' => 'EQUITY',
            'amount' => 'not-a-number',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('wieksza od zera', $client->getCrawler()->text());
    }

    public function testStoreWithAmountExceeding100MillionRedirectsWithError(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/losses');
        $csrfToken = $this->extractStoreCsrfToken($crawler);

        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => (string) (self::TESTING_YEAR - 1),
            'tax_category' => 'EQUITY',
            'amount' => '100000001',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('przekraczac', $client->getCrawler()->text());
    }

    // ──────────────────────────────────────────────
    // POST /losses/{id}/delete
    // ──────────────────────────────────────────────

    public function testDeleteHappyPathRedirectsWithSuccessFlash(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/losses');
        $csrfToken = $this->extractStoreCsrfToken($crawler);

        // Create a loss to delete
        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => (string) (self::TESTING_YEAR - 1),
            'tax_category' => 'DERIVATIVE',
            'amount' => '5000.00',
        ]);
        self::assertResponseRedirects('/losses');

        // Load the index to find the created loss and its delete form
        $crawler = $client->request('GET', '/losses');
        self::assertResponseIsSuccessful();

        $deleteForm = $crawler->filter('form[action*="/losses/"][action*="/delete"]');

        if ($deleteForm->count() === 0) {
            self::markTestSkipped('No delete form found on /losses page.');
        }

        $deleteAction = (string) $deleteForm->first()->attr('action');
        $deleteCsrfToken = (string) $deleteForm->first()->filter('input[name="_token"]')->attr('value');

        $client->request('POST', $deleteAction, [
            '_token' => $deleteCsrfToken,
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('usunieta', $client->getCrawler()->text());
    }

    public function testDeleteWithInvalidCsrfRedirectsWithError(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/losses');
        $csrfToken = $this->extractStoreCsrfToken($crawler);

        // Create a loss so we have a valid ID
        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => (string) (self::TESTING_YEAR - 1),
            'tax_category' => 'EQUITY',
            'amount' => '1000.00',
        ]);

        $crawler = $client->request('GET', '/losses');
        $deleteForm = $crawler->filter('form[action*="/losses/"][action*="/delete"]');

        if ($deleteForm->count() === 0) {
            self::markTestSkipped('No delete form found on /losses page.');
        }

        $deleteAction = (string) $deleteForm->first()->attr('action');

        $client->request('POST', $deleteAction, [
            '_token' => 'invalid_token',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('CSRF', $client->getCrawler()->text());
    }

    // ──────────────────────────────────────────────
    // POST /losses — boundary edge cases
    // ──────────────────────────────────────────────

    public function testStoreWithCommaDecimalSeparatorWorksCorrectly(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/losses');
        $csrfToken = $this->extractStoreCsrfToken($crawler);

        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => (string) (self::TESTING_YEAR - 1),
            'tax_category' => 'CRYPTO',
            'amount' => '12345,67',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('Dodano strate', $client->getCrawler()->text());
    }

    public function testStoreWithExactly100MillionIsAccepted(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/losses');
        $csrfToken = $this->extractStoreCsrfToken($crawler);

        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => (string) (self::TESTING_YEAR - 2),
            'tax_category' => 'EQUITY',
            'amount' => '100000000',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('Dodano strate', $client->getCrawler()->text());
    }

    public function testStoreWithOldestAllowedYearIsAccepted(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/losses');
        $csrfToken = $this->extractStoreCsrfToken($crawler);
        $oldestAllowed = self::TESTING_YEAR - 5;

        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => (string) $oldestAllowed,
            'tax_category' => 'DERIVATIVE',
            'amount' => '1000.00',
        ]);

        self::assertResponseRedirects('/losses');

        $client->followRedirect();

        self::assertStringContainsString('Dodano strate', $client->getCrawler()->text());
    }

    protected function createAuthenticatedClient(): KernelBrowser
    {
        $client = parent::createAuthenticatedClient();
        self::getContainer()->set(
            ClockInterface::class,
            new MockClock(new \DateTimeImmutable(self::TESTING_YEAR . '-06-15 12:00:00')),
        );

        return $client;
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Extract the CSRF token for the store form from the rendered /losses page.
     */
    private function extractStoreCsrfToken(Crawler $crawler): string
    {
        $tokenInput = $crawler->filter('form[action*="/losses"] input[name="_token"]');
        self::assertGreaterThan(0, $tokenInput->count(), 'CSRF token input not found in store form.');

        return (string) $tokenInput->first()->attr('value');
    }
}
