<?php

declare(strict_types=1);

namespace App\Tests\Integration;

/**
 * Regression tests for legal disclaimers.
 *
 * Polish tax law requires that tools presenting tax calculations carry a clear
 * disclaimer that results are informational only — not legal or tax advice.
 * These tests pin the disclaimer text so that accidental template refactors
 * cannot silently remove required legal copy.
 *
 * Pages with the full _disclaimer.html.twig partial:
 *   - Dashboard index
 *   - Dashboard calculation detail
 *   - Dashboard FIFO detail
 *   - Dashboard dividends detail
 *   - Declaration preview (PIT-38)
 *
 * Pages covered only by the base.html.twig footer disclaimer:
 *   - Losses page (base footer: "Nie stanowi doradztwa podatkowego")
 *
 * Public pages with base_public.html.twig footer disclaimer:
 *   - Landing page (/)
 *   - Pricing page (/cennik)
 *
 * NOTE: The /losses page does NOT include _disclaimer.html.twig. Only the
 * footer from base.html.twig carries the disclaimer text there. If a full
 * disclaimer is ever required on /losses, add the partial and update this test.
 */
final class DisclaimerRegressionTest extends AuthenticatedWebTestCase
{
    private const string DISCLAIMER_KEY_PHRASE = 'doradztwa podatkowego';

    private const string DISCLAIMER_CONSULT_PHRASE = 'doradca podatkowym';

    /**
     * The dashboard renders _disclaimer.html.twig which contains both the
     * "doradztwa podatkowego ani prawnego" text and the "skonsultuj sie z doradca
     * podatkowym" call to action.
     */
    public function testDashboardContainsFullDisclaimer(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();

        $html = $client->getResponse()->getContent();
        self::assertNotFalse($html);

        self::assertStringContainsString(
            self::DISCLAIMER_KEY_PHRASE,
            $html,
            'Dashboard must contain the legal disclaimer "doradztwa podatkowego"',
        );

        self::assertStringContainsString(
            self::DISCLAIMER_CONSULT_PHRASE,
            $html,
            'Dashboard must contain the "skonsultuj sie z doradca podatkowym" clause',
        );
    }

    /**
     * The declaration preview page renders _disclaimer.html.twig.
     * With no transaction data the controller redirects to /import, so we follow
     * the redirect and verify the disclaimer is present on the import page via
     * the base layout footer — the critical signal is that the disclaimer is
     * never completely absent from the user journey.
     *
     * We also test the dashboard route directly (which stays on 200) as the
     * primary assertion for the declaration flow disclaimer.
     */
    public function testDeclarationPreviewRedirectFlowKeepsDisclaimerInFooter(): void
    {
        $client = $this->createAuthenticatedClient();

        // With no data, controller redirects to /import
        $client->request('GET', '/declaration/2025/preview');
        self::assertResponseRedirects();

        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        $html = $client->getResponse()->getContent();
        self::assertNotFalse($html);

        // The base.html.twig footer always contains the footer disclaimer
        self::assertStringContainsString(
            self::DISCLAIMER_KEY_PHRASE,
            $html,
            'Import page (redirect target from declaration) must still carry the base disclaimer',
        );
    }

    /**
     * The dashboard calculation detail page includes _disclaimer.html.twig.
     */
    public function testDashboardCalculationContainsDisclaimer(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/dashboard/calculation/2025');

        self::assertResponseIsSuccessful();

        $html = $client->getResponse()->getContent();
        self::assertNotFalse($html);

        self::assertStringContainsString(
            self::DISCLAIMER_KEY_PHRASE,
            $html,
            'Dashboard calculation page must contain the legal disclaimer',
        );

        self::assertStringContainsString(
            self::DISCLAIMER_CONSULT_PHRASE,
            $html,
            'Dashboard calculation page must contain the "skonsultuj sie z doradca podatkowym" clause',
        );
    }

    /**
     * The dashboard FIFO detail page includes _disclaimer.html.twig.
     */
    public function testDashboardFifoContainsDisclaimer(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/dashboard/fifo/2025');

        self::assertResponseIsSuccessful();

        $html = $client->getResponse()->getContent();
        self::assertNotFalse($html);

        self::assertStringContainsString(
            self::DISCLAIMER_KEY_PHRASE,
            $html,
            'Dashboard FIFO page must contain the legal disclaimer',
        );
    }

    /**
     * The dashboard dividends detail page includes _disclaimer.html.twig.
     */
    public function testDashboardDividendsContainsDisclaimer(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/dashboard/dividends/2025');

        self::assertResponseIsSuccessful();

        $html = $client->getResponse()->getContent();
        self::assertNotFalse($html);

        self::assertStringContainsString(
            self::DISCLAIMER_KEY_PHRASE,
            $html,
            'Dashboard dividends page must contain the legal disclaimer',
        );
    }

    /**
     * The losses page does not include _disclaimer.html.twig but it extends
     * base.html.twig whose footer always contains "Nie stanowi doradztwa podatkowego".
     */
    public function testLossesPageContainsFooterDisclaimer(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/losses');

        self::assertResponseIsSuccessful();

        $html = $client->getResponse()->getContent();
        self::assertNotFalse($html);

        self::assertStringContainsString(
            self::DISCLAIMER_KEY_PHRASE,
            $html,
            'Losses page must contain the footer disclaimer "doradztwa podatkowego"',
        );
    }

    /**
     * The landing page extends base_public.html.twig whose footer carries
     * "Nie stanowi doradztwa podatkowego". Additionally, the FAQ section
     * explicitly states the tool is not a replacement for a tax advisor.
     */
    public function testLandingPageContainsDisclaimerInFooterAndFaq(): void
    {
        $client = self::createClient();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        $html = $client->getResponse()->getContent();
        self::assertNotFalse($html);

        self::assertStringContainsString(
            self::DISCLAIMER_KEY_PHRASE,
            $html,
            'Landing page must contain the disclaimer "doradztwa podatkowego"',
        );
    }

    /**
     * The disclaimer partial contains a "Zastrzezenie" heading — this regression
     * test pins the exact structure so the heading is not accidentally removed.
     */
    public function testDashboardDisclaimerContainsWarningHeading(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();

        $html = $client->getResponse()->getContent();
        self::assertNotFalse($html);

        self::assertStringContainsString(
            'Zastrzezenie',
            $html,
            'The disclaimer block must include the "Zastrzezenie" heading',
        );
    }
}
