<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration tests for PricingConsentController.
 *
 * The route /cennik/wybierz is PUBLIC_ACCESS — no authentication required.
 */
final class PricingConsentControllerWebTest extends WebTestCase
{
    public function testMissingConsentRedirectsToPricing(): void
    {
        $client = self::createClient();

        $client->request('POST', '/cennik/wybierz', [
            'plan' => 'standard',
            // withdrawal_consent intentionally omitted
        ]);

        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/cennik', (string) $location);
    }

    public function testInvalidPlanRedirectsToPricing(): void
    {
        $client = self::createClient();

        $client->request('POST', '/cennik/wybierz', [
            'plan' => 'invalid',
            'withdrawal_consent' => '1',
        ]);

        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/cennik', (string) $location);
    }

    public function testValidConsentWithStandardPlanRedirectsToLogin(): void
    {
        $client = self::createClient();

        $client->request('POST', '/cennik/wybierz', [
            'plan' => 'standard',
            'withdrawal_consent' => '1',
        ]);

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location);
        self::assertStringContainsString('plan=standard', $location);
    }

    public function testValidConsentWithProPlanRedirectsToLogin(): void
    {
        $client = self::createClient();

        $client->request('POST', '/cennik/wybierz', [
            'plan' => 'pro',
            'withdrawal_consent' => '1',
        ]);

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location);
        self::assertStringContainsString('plan=pro', $location);
    }
}
