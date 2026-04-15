<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        private readonly RateLimiterFactory $healthCheckLimiter,
    ) {
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $limiter = $this->healthCheckLimiter->create($request->getClientIp() ?? 'unknown');

        if (! $limiter->consume()->isAccepted()) {
            return new JsonResponse(
                data: [
                    'error' => 'Too many requests',
                ],
                status: Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $checks = [
            'db' => $this->checkDb(),
            'cache' => $this->checkCache(),
        ];

        $healthy = ! in_array(false, $checks, strict: true);

        return new JsonResponse(
            data: [
                'status' => $healthy ? 'ok' : 'degraded',
                'checks' => $checks,
            ],
            status: $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    private function checkDb(): bool
    {
        try {
            $result = $this->connection->executeQuery('SELECT 1');
            $value = $result->fetchOne();
            $result->free();

            return $value === '1' || $value === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            $nonce = bin2hex(random_bytes(8));
            $item = $this->cache->getItem('_health_check_probe');
            $item->set($nonce);
            $item->expiresAfter(10);
            $this->cache->save($item);

            $read = $this->cache->getItem('_health_check_probe');
            $hit = $read->isHit() && $read->get() === $nonce;
            $this->cache->deleteItem('_health_check_probe');

            return $hit;
        } catch (\Throwable) {
            return false;
        }
    }
}
