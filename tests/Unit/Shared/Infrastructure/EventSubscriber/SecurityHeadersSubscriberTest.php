<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\EventSubscriber;

use App\Shared\Infrastructure\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersSubscriberTest extends TestCase
{
    public function testSubscribesToKernelResponse(): void
    {
        $events = SecurityHeadersSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    public function testSetsXFrameOptionsDeny(): void
    {
        $response = $this->dispatchResponse(appDebug: false);

        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
    }

    public function testSetsXContentTypeOptionsNosniff(): void
    {
        $response = $this->dispatchResponse(appDebug: false);

        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function testSetsXXssProtectionToZero(): void
    {
        $response = $this->dispatchResponse(appDebug: false);

        self::assertSame('0', $response->headers->get('X-XSS-Protection'));
    }

    public function testSetsReferrerPolicy(): void
    {
        $response = $this->dispatchResponse(appDebug: false);

        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    public function testSetsPermissionsPolicy(): void
    {
        $response = $this->dispatchResponse(appDebug: false);

        self::assertSame(
            'camera=(), microphone=(), geolocation=()',
            $response->headers->get('Permissions-Policy'),
        );
    }

    public function testScriptSrcAlwaysIncludesUnsafeInlineForImportmap(): void
    {
        $prodResponse = $this->dispatchResponse(appDebug: false);
        $devResponse = $this->dispatchResponse(appDebug: true);

        self::assertStringContainsString(
            "script-src 'self' 'unsafe-inline'",
            $prodResponse->headers->get('Content-Security-Policy'),
            'Importmap requires unsafe-inline in prod',
        );
        self::assertStringContainsString(
            "script-src 'self' 'unsafe-inline'",
            $devResponse->headers->get('Content-Security-Policy'),
            'Importmap requires unsafe-inline in dev',
        );
    }

    public function testStyleSrcHasUnsafeInlineOnlyInDebug(): void
    {
        $prodCsp = $this->dispatchResponse(appDebug: false)->headers->get('Content-Security-Policy');
        $devCsp = $this->dispatchResponse(appDebug: true)->headers->get('Content-Security-Policy');

        self::assertStringContainsString("style-src 'self' 'unsafe-inline'", $devCsp);
        self::assertStringNotContainsString(
            "style-src 'self' 'unsafe-inline'",
            $prodCsp,
            'style-src must not allow unsafe-inline in prod',
        );
    }

    public function testProdCsp(): void
    {
        $csp = $this->dispatchResponse(appDebug: false)->headers->get('Content-Security-Policy');

        self::assertSame(
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'",
            $csp,
        );
    }

    public function testDevCsp(): void
    {
        $csp = $this->dispatchResponse(appDebug: true)->headers->get('Content-Security-Policy');

        self::assertSame(
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'",
            $csp,
        );
    }

    public function testSetsStrictTransportSecurity(): void
    {
        $response = $this->dispatchResponse(appDebug: false);

        self::assertSame(
            'max-age=31536000; includeSubDomains',
            $response->headers->get('Strict-Transport-Security'),
        );
    }

    public function testFrameAncestorsNoneInCsp(): void
    {
        $response = $this->dispatchResponse(appDebug: false);

        self::assertStringContainsString(
            "frame-ancestors 'none'",
            $response->headers->get('Content-Security-Policy'),
        );
    }

    public function testAllHeadersPresentOnSingleResponse(): void
    {
        $response = $this->dispatchResponse(appDebug: false);

        $expectedHeaders = [
            'X-Frame-Options',
            'X-Content-Type-Options',
            'X-XSS-Protection',
            'Referrer-Policy',
            'Permissions-Policy',
            'Content-Security-Policy',
            'Strict-Transport-Security',
        ];

        foreach ($expectedHeaders as $header) {
            self::assertTrue(
                $response->headers->has($header),
                sprintf('Missing security header: %s', $header),
            );
        }
    }

    public function testSkipsSubRequests(): void
    {
        $subscriber = new SecurityHeadersSubscriber(appDebug: false);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');
        $response = new Response();

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        self::assertFalse($response->headers->has('X-Frame-Options'), 'Sub-requests must not get security headers');
    }

    private function dispatchResponse(bool $appDebug): Response
    {
        $subscriber = new SecurityHeadersSubscriber($appDebug);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');
        $response = new Response();

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        return $response;
    }
}
