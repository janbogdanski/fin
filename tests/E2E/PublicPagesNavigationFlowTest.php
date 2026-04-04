<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * E2E: Visitor navigates public pages: Landing -> Pricing -> Blog -> Landing.
 *
 * Verifies all public SEO-critical pages load correctly and cross-link properly.
 */
final class PublicPagesNavigationFlowTest extends WebTestCase
{
    public function testLandingToPricingNavigation(): void
    {
        $client = self::createClient();

        // Step 1: Landing page
        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        // Step 2: Click pricing link
        $pricingLink = $crawler->filter('a[href*="/cennik"]')->first();
        self::assertNotEmpty($pricingLink->count(), 'Pricing link must exist on landing');

        $client->click($pricingLink->link());
        self::assertResponseIsSuccessful();

        $pricingText = mb_strtolower($client->getCrawler()->text());
        self::assertTrue(
            str_contains($pricingText, 'cennik')
            || str_contains($pricingText, 'plan')
            || str_contains($pricingText, 'cena')
            || str_contains($pricingText, 'zl'),
            'Pricing page should contain pricing-related content',
        );
    }

    public function testBlogIndexLoadsSuccessfully(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/blog');

        self::assertResponseIsSuccessful();

        $pageText = mb_strtolower($crawler->text());
        self::assertTrue(
            str_contains($pageText, 'blog')
            || str_contains($pageText, 'artykul')
            || str_contains($pageText, 'pit')
            || str_contains($pageText, 'podatek'),
            'Blog index should contain blog-related content',
        );
    }

    /**
     * @dataProvider publicRouteProvider
     */
    public function testPublicPageReturns200(string $route): void
    {
        $client = self::createClient();
        $client->request('GET', $route);

        self::assertResponseIsSuccessful(
            sprintf('Public route %s should return 200', $route),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publicRouteProvider(): array
    {
        return [
            'landing'  => ['/'],
            'login'    => ['/login'],
            'cennik'   => ['/cennik'],
            'blog'     => ['/blog'],
        ];
    }

    public function testLandingPageContainsSeoElements(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        // Title tag
        $title = $crawler->filter('title');
        self::assertGreaterThan(0, $title->count(), 'Page must have a <title> tag');
        self::assertStringContainsString('PIT-38', $title->text());

        // Meta description
        $metaDesc = $crawler->filter('meta[name="description"]');
        self::assertGreaterThan(0, $metaDesc->count(), 'Page must have a meta description');

        // H1 tag
        $h1 = $crawler->filter('h1');
        self::assertGreaterThan(0, $h1->count(), 'Page must have an H1 heading');
    }
}
