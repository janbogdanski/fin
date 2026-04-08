<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * E2E: Visitor navigates public pages: Landing -> Pricing -> Blog -> Landing.
 *
 * Verifies all public SEO-critical pages load correctly and cross-link properly.
 */
#[Group('e2e')]
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

    #[DataProvider('publicRouteProvider')]
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
            'landing' => ['/'],
            'login' => ['/login'],
            'cennik' => ['/cennik'],
            'blog' => ['/blog'],
            'regulamin' => ['/regulamin'],
            'polityka-prywatnosci' => ['/polityka-prywatnosci'],
        ];
    }

    public function testLegalTermsPageContainsRequiredContent(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/regulamin');

        self::assertResponseIsSuccessful();

        $h1 = $crawler->filter('h1');
        self::assertGreaterThan(0, $h1->count(), 'Terms page must have an H1 heading');
        self::assertStringContainsStringIgnoringCase('Regulamin', $h1->text());

        $pageText = $crawler->text();
        self::assertStringContainsString('Dokument w przygotowaniu', $pageText);
        self::assertStringContainsString('Administratorem danych', $pageText);
    }

    public function testLegalPrivacyPageContainsRequiredContent(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/polityka-prywatnosci');

        self::assertResponseIsSuccessful();

        $h1 = $crawler->filter('h1');
        self::assertGreaterThan(0, $h1->count(), 'Privacy page must have an H1 heading');
        self::assertStringContainsStringIgnoringCase('Polityka prywatnosci', $h1->text());

        $pageText = $crawler->text();
        self::assertStringContainsString('Dokument w przygotowaniu', $pageText);
        self::assertStringContainsString('Administratorem danych', $pageText);
    }

    public function testFooterLegalLinksAreNotHashAnchors(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        $regulaminLink = $crawler->filter('a')->reduce(
            static fn (\Symfony\Component\DomCrawler\Crawler $node): bool =>
                str_contains(mb_strtolower($node->text()), 'regulamin'),
        );

        self::assertGreaterThan(0, $regulaminLink->count(), 'Footer must contain a Regulamin link');
        self::assertNotEquals('#', $regulaminLink->first()->attr('href'), 'Regulamin link must not be href="#"');

        $privacyLink = $crawler->filter('a')->reduce(
            static fn (\Symfony\Component\DomCrawler\Crawler $node): bool =>
                str_contains(mb_strtolower($node->text()), 'polityka'),
        );

        self::assertGreaterThan(0, $privacyLink->count(), 'Footer must contain a Privacy Policy link');
        self::assertNotEquals('#', $privacyLink->first()->attr('href'), 'Privacy Policy link must not be href="#"');
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
