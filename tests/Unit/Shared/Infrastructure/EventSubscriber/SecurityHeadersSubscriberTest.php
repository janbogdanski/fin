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
    private SecurityHeadersSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new SecurityHeadersSubscriber();
    }

    public function testSubscribesToKernelResponse(): void
    {
        $events = SecurityHeadersSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    public function testSetsXFrameOptionsDeny(): void
    {
        $response = $this->dispatchResponse();

        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
    }

    public function testSetsXContentTypeOptionsNosniff(): void
    {
        $response = $this->dispatchResponse();

        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function testSetsXXssProtectionToZero(): void
    {
        $response = $this->dispatchResponse();

        self::assertSame('0', $response->headers->get('X-XSS-Protection'));
    }

    public function testSetsReferrerPolicy(): void
    {
        $response = $this->dispatchResponse();

        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    public function testSetsPermissionsPolicy(): void
    {
        $response = $this->dispatchResponse();

        self::assertSame(
            'camera=(), microphone=(), geolocation=()',
            $response->headers->get('Permissions-Policy'),
        );
    }

    public function testSetsContentSecurityPolicy(): void
    {
        $response = $this->dispatchResponse();

        self::assertSame(
            "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'",
            $response->headers->get('Content-Security-Policy'),
        );
    }

    public function testSetsStrictTransportSecurity(): void
    {
        $response = $this->dispatchResponse();

        self::assertSame(
            'max-age=31536000; includeSubDomains',
            $response->headers->get('Strict-Transport-Security'),
        );
    }

    public function testAllHeadersPresentOnSingleResponse(): void
    {
        $response = $this->dispatchResponse();

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

    private function dispatchResponse(): Response
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');
        $response = new Response();

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        return $response;
    }
}
