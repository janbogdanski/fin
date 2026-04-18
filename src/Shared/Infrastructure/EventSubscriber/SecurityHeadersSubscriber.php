<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds security headers to every HTTP response.
 *
 * script-src always includes 'unsafe-inline' because Symfony AssetMapper renders
 * the importmap as an inline <script type="importmap"> element — the importmap spec
 * provides no src= attribute alternative, so inline cannot be avoided.
 *
 * style-src includes 'unsafe-inline' only in debug mode (dev) because the Symfony
 * WebProfiler toolbar injects inline styles that cannot carry a nonce.
 *
 * @see https://owasp.org/www-project-secure-headers/
 * @see https://www.w3.org/TR/import-maps/ (importmap must be inline)
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly bool $appDebug
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        // 'unsafe-inline' in style-src is needed only in dev: the WebProfiler toolbar
        // injects inline styles that cannot carry a nonce.
        $styleInline = $this->appDebug ? " 'unsafe-inline'" : '';

        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '0',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self'{$styleInline}; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        ];

        foreach ($headers as $header => $value) {
            $response->headers->set($header, $value);
        }
    }
}
