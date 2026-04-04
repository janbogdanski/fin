<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * E2E: Visitor lands on homepage, sees CTA, navigates to login page.
 *
 * Tests the primary acquisition funnel: Landing -> Login.
 *
 * @group e2e
 */
final class LandingToLoginFlowTest extends WebTestCase
{
    public function testLandingPageLoadsWithHeroAndCTA(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        // Hero headline visible
        self::assertSelectorTextContains('h1', 'PIT-38');

        // CTA button present and links to login
        $ctaLink = $crawler->filter('a[href*="/login"]');
        self::assertGreaterThan(0, $ctaLink->count(), 'CTA link to login must be present on landing page');
    }

    public function testLandingCtaLeadsToLoginPage(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        // Click the first CTA link pointing to login
        $ctaLink = $crawler->filter('a[href*="/login"]')->first();
        $client->click($ctaLink->link());

        self::assertResponseIsSuccessful();

        // Login page should have an email input and CSRF token
        self::assertSelectorExists('input[name="email"]');
        self::assertSelectorExists('input[name="_csrf_token"]');
    }

    public function testLandingPageHasPricingLink(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        $pricingLink = $crawler->filter('a[href*="/cennik"]');
        self::assertGreaterThan(0, $pricingLink->count(), 'Pricing link must be present on landing page');
    }
}
