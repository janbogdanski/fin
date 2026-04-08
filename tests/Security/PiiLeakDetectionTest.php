<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Identity\Infrastructure\Security\SecurityUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * PII leak detection tests.
 *
 * Verifies that NIP (Polish tax ID, 10 digits) and user email addresses
 * never appear in:
 *  - error pages served to unauthenticated users
 *  - public pages (landing, pricing, blog)
 *  - API/form validation error responses
 *
 * NIP is sensitive PII under GDPR — leaking it in error bodies,
 * server logs forwarded to third parties, or public HTML is a data breach.
 */
#[Group('security')]
final class PiiLeakDetectionTest extends WebTestCase
{
    private const string TEST_USER_ID = '00000000-0000-0000-0000-000000000001';

    private const string TEST_USER_EMAIL = 'test@example.com';

    /**
     * A valid NIP (passes checksum) used as test fixture.
     * Source: publicly known example NIP used in Polish tax documentation.
     */
    private const string VALID_NIP = '5260001246';

    /**
     * A syntactically valid 10-digit string that fails the NIP checksum.
     * Useful for triggering "Invalid NIP check digit" error path.
     */
    private const string INVALID_NIP_CHECKSUM = '1234567890';

    // -------------------------------------------------------------------------
    // Unauthenticated error pages
    // -------------------------------------------------------------------------

    public function testFourOhFourPageDoesNotContainUserEmail(): void
    {
        $client = self::createClient();
        $this->ensureTestUserExists();

        $securityUser = new SecurityUser(self::TEST_USER_ID, self::TEST_USER_EMAIL);
        $client->loginUser($securityUser);

        // Trigger a 404 on a route that does not exist
        $client->request('GET', '/this-path-definitely-does-not-exist-404');

        $response = $client->getResponse();

        self::assertSame(
            404,
            $response->getStatusCode(),
            'Expected a 404 response for a non-existent route.',
        );

        self::assertStringNotContainsString(
            self::TEST_USER_EMAIL,
            (string) $response->getContent(),
            'User email must not appear in 404 error page body.',
        );
    }

    public function testUnauthenticated404DoesNotContainEmail(): void
    {
        $client = self::createClient();

        $client->request('GET', '/this-path-definitely-does-not-exist-404');

        $response = $client->getResponse();

        self::assertSame(404, $response->getStatusCode());

        self::assertStringNotContainsString(
            self::TEST_USER_EMAIL,
            (string) $response->getContent(),
            'User email must not appear in 404 error page for anonymous visitor.',
        );
    }

    // -------------------------------------------------------------------------
    // Public pages — no NIP-shaped data
    // -------------------------------------------------------------------------

    #[DataProvider('publicPagesProvider')]
    public function testPublicPageDoesNotContainNipShapedData(string $url): void
    {
        $client = self::createClient();

        $client->request('GET', $url);

        $response = $client->getResponse();
        $body = (string) $response->getContent();

        // Any 10-digit sequence in a public page is suspicious — NIP is exactly 10 digits.
        // We check for sequences NOT preceded or followed by other digits to avoid matching
        // e.g. timestamps or larger numbers.
        self::assertDoesNotMatchRegularExpression(
            '/(?<!\d)\d{10}(?!\d)/',
            $body,
            sprintf('Public page %s must not contain a 10-digit sequence that could be a NIP.', $url),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publicPagesProvider(): iterable
    {
        yield 'landing page /' => ['/'];
        yield 'pricing /cennik' => ['/cennik'];
        yield 'blog /blog' => ['/blog'];
        yield 'login page /login' => ['/login'];
    }

    // -------------------------------------------------------------------------
    // Profile form validation — error message must not echo raw NIP
    // -------------------------------------------------------------------------

    public function testProfileValidationErrorMessageDoesNotContainRawNip(): void
    {
        $client = self::createClient();
        $this->ensureTestUserExists();

        $securityUser = new SecurityUser(self::TEST_USER_ID, self::TEST_USER_EMAIL);
        $client->loginUser($securityUser);

        // GET /profile first to establish session and extract a valid CSRF token from the form.
        $crawler = $client->request('GET', '/profile');
        self::assertResponseIsSuccessful();

        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');
        self::assertNotEmpty($csrfToken, 'CSRF token must be present in profile form.');

        // Submit a NIP that passes length/digit check but fails checksum.
        // The controller catches \InvalidArgumentException and passes $e->getMessage()
        // to the flash bag. The flash message text must NOT contain the raw submitted NIP.
        $client->request('POST', '/profile', [
            '_csrf_token' => $csrfToken,
            'nip' => self::INVALID_NIP_CHECKSUM,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);

        $response = $client->getResponse();
        // The error is rendered inline (422), not a redirect. Extract only the flash message area.
        $crawler = $client->getCrawler();

        // Flash messages are the only text that must not contain the raw NIP.
        // The form input re-populating the field with the submitted value is intentional UX
        // (the user sees what they typed), but error text / flash messages must be generic.
        $flashText = $crawler->filter('[role="alert"], .flash, .alert')->text('');

        self::assertStringNotContainsString(
            self::INVALID_NIP_CHECKSUM,
            $flashText,
            'Flash / error message must not echo the raw submitted NIP digits.',
        );
    }

    public function testProfileValidationErrorMessageDoesNotContainMalformedNip(): void
    {
        $client = self::createClient();
        $this->ensureTestUserExists();

        $securityUser = new SecurityUser(self::TEST_USER_ID, self::TEST_USER_EMAIL);
        $client->loginUser($securityUser);

        // GET /profile to establish session and get CSRF token.
        $crawler = $client->request('GET', '/profile');
        self::assertResponseIsSuccessful();

        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');
        self::assertNotEmpty($csrfToken, 'CSRF token must be present in profile form.');

        // Submit a NIP with non-digit characters — triggers "NIP must be exactly 10 digits".
        // The error message must be generic, not include the raw submitted string.
        $malformedNip = 'ABCD123456';

        $client->request('POST', '/profile', [
            '_csrf_token' => $csrfToken,
            'nip' => $malformedNip,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);

        $crawler = $client->getCrawler();
        $flashText = $crawler->filter('[role="alert"], .flash, .alert')->text('');

        self::assertStringNotContainsString(
            $malformedNip,
            $flashText,
            'Flash / error message must not echo the raw submitted malformed NIP value.',
        );
    }

    // -------------------------------------------------------------------------
    // Unauthenticated access to protected endpoints — no PII in redirect
    // -------------------------------------------------------------------------

    /**
     * Unauthenticated POST to the profile update endpoint should redirect,
     * not render a page that could include stale NIP data.
     */
    public function testUnauthenticatedProfilePostDoesNotLeakPii(): void
    {
        $client = self::createClient();

        $client->request('POST', '/profile', [
            'nip' => self::VALID_NIP,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);

        $response = $client->getResponse();

        // Must redirect to /login, not render a page with our submitted data
        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/login', $response->headers->get('Location') ?? '');

        // The redirect response body itself must not echo back the NIP
        self::assertStringNotContainsString(
            self::VALID_NIP,
            (string) $response->getContent(),
            'Redirect response to unauthenticated user must not echo submitted NIP.',
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function ensureTestUserExists(): void
    {
        $container = self::getContainer();

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $container->get(\Doctrine\DBAL\Connection::class);

        $exists = $connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE id = :id',
            [
                'id' => self::TEST_USER_ID,
            ],
        );

        if ((int) $exists > 0) {
            return;
        }

        $connection->insert('users', [
            'id' => self::TEST_USER_ID,
            'email' => self::TEST_USER_EMAIL,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'referral_code' => 'TEST-' . substr(self::TEST_USER_ID, 0, 8),
            'bonus_transactions' => 0,
        ]);
    }
}
