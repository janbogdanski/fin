<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Controller;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Infrastructure\Audit\AuditLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles community format-problem reports.
 *
 * When import fails with UnsupportedBrokerFormatException, the user can
 * submit a one-click report. The report is logged to the audit_log so the
 * team can identify adapters that need attention (ADR-019, P3-002).
 *
 * No PII is stored — only broker ID, user UUID (pseudonymised), and IP.
 */
final class ImportFormatReportController extends AbstractController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RateLimiterFactory $importUploadLimiter,
    ) {
    }

    #[Route('/import/zglos-problem', name: 'import_format_report', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if (! $this->isCsrfTokenValid('import_format_report', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Nieprawidlowy token CSRF. Sprobuj ponownie.');

            return $this->redirectToRoute('import_index');
        }

        $limiter = $this->importUploadLimiter->create((string) $request->getClientIp());

        if (! $limiter->consume(1)->isAccepted()) {
            $this->addFlash('warning', 'Zbyt wiele zgloszen. Sprobuj ponownie pozniej.');

            return $this->redirectToRoute('import_index');
        }

        /** @var SecurityUser|null $user */
        $user = $this->getUser();

        $brokerId = $request->request->getString('broker_id');

        $this->auditLogger->log(
            'import.format_unsupported_report',
            $user?->id(),
            ['broker_id' => $brokerId ?: 'unknown'],
            $request->getClientIp(),
        );

        $this->addFlash('success', 'Dziękujemy za zgłoszenie! Sprawdzimy format i zaktualizujemy adapter wkrótce.');

        return $this->redirectToRoute('import_index');
    }
}
