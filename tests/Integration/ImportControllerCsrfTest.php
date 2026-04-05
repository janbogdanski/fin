<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\BrokerImport\Infrastructure\Controller\ImportUploadController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * P0-007: Verifies CSRF protection on the CSV upload endpoint.
 *
 * Uses KernelTestCase to test the controller CSRF validation logic
 * directly via the container (no browser-kit dependency required).
 */
final class ImportControllerCsrfTest extends KernelTestCase
{
    public function testUploadWithoutCsrfTokenRedirects(): void
    {
        $controller = $this->bootAndGetController();
        $request = $this->createRequestWithSession('POST', '/import/upload');

        $response = $controller($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/import', $response->headers->get('Location') ?? '');
        $this->assertFlashContains($request, 'CSRF');
    }

    public function testUploadWithInvalidCsrfTokenRedirects(): void
    {
        $controller = $this->bootAndGetController();
        $request = $this->createRequestWithSession('POST', '/import/upload', [
            '_token' => 'definitely_invalid_token',
        ]);

        $response = $controller($request);

        self::assertSame(302, $response->getStatusCode());
        $this->assertFlashContains($request, 'CSRF');
    }

    public function testUploadWithValidCsrfTokenPassesCsrfCheck(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        // Generate a valid CSRF token through the session-backed manager
        $session = new Session(new MockArraySessionStorage());
        $this->pushSessionToRequestStack($session);

        /** @var CsrfTokenManagerInterface $csrfManager */
        $csrfManager = $container->get('security.csrf.token_manager');
        $validToken = $csrfManager->getToken('import_upload')->getValue();

        /** @var ImportUploadController $controller */
        $controller = $container->get(ImportUploadController::class);
        $controller->setContainer($container);

        $request = Request::create('/import/upload', 'POST', [
            '_token' => $validToken,
        ]);
        $request->setSession($session);

        $response = $controller($request);

        self::assertSame(302, $response->getStatusCode());

        $flashes = $session->getFlashBag()->peekAll();
        $allErrors = implode(' ', $flashes['error'] ?? []);

        // Should NOT contain CSRF error — the token was valid
        self::assertStringNotContainsString('CSRF', $allErrors);
        // Should contain a file-related error instead
        self::assertStringContainsString('plik', mb_strtolower($allErrors));
    }

    private function bootAndGetController(): ImportUploadController
    {
        self::bootKernel();
        $container = self::getContainer();

        $session = new Session(new MockArraySessionStorage());
        $this->pushSessionToRequestStack($session);

        /** @var ImportUploadController $controller */
        $controller = $container->get(ImportUploadController::class);
        $controller->setContainer($container);

        return $controller;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function createRequestWithSession(string $method, string $uri, array $parameters = []): Request
    {
        $container = self::getContainer();

        /** @var RequestStack $requestStack */
        $requestStack = $container->get(RequestStack::class);
        $session = $requestStack->getCurrentRequest()?->getSession();

        $request = Request::create($uri, $method, $parameters);

        if ($session !== null) {
            $request->setSession($session);
        }

        return $request;
    }

    private function pushSessionToRequestStack(Session $session): void
    {
        $container = self::getContainer();

        /** @var RequestStack $requestStack */
        $requestStack = $container->get(RequestStack::class);

        $mainRequest = Request::create('/');
        $mainRequest->setSession($session);
        $requestStack->push($mainRequest);
    }

    private function assertFlashContains(Request $request, string $needle): void
    {
        $session = $request->getSession();
        \assert($session instanceof Session);
        $flashes = $session->getFlashBag()->peekAll();
        $allErrors = implode(' ', $flashes['error'] ?? []);
        self::assertStringContainsString($needle, $allErrors);
    }
}
