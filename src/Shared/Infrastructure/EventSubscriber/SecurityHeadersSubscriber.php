<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds security headers to every HTTP response.
 *
 * In debug mode (dev), script-src and style-src include 'unsafe-inline' so that
 * Symfony's importmap inline scripts and the WebProfiler toolbar render correctly.
 * In production, 'unsafe-inline' is omitted for stricter CSP.
 *
 * @see https://owasp.org/www-project-secure-headers/
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly bool $appDebug)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        $inlineAllowed = $this->appDebug ? " 'unsafe-inline'" : '';

        $headers = [
            'X-Frame-Options'           => 'DENY',
            'X-Content-Type-Options'    => 'nosniff',
            'X-XSS-Protection'          => '0',
            'Referrer-Policy'           => 'strict-origin-when-cross-origin',
            'Permissions-Policy'        => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy'   => "default-src 'self'; script-src 'self'{$inlineAllowed}; style-src 'self'{$inlineAllowed}; img-src 'self' data:; font-src 'self'",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        ];

        foreach ($headers as $header => $value) {
            $response->headers->set($header, $value);
        }
    }
}
