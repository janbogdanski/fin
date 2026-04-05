<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Controller;

use App\Declaration\Application\Result\DeclarationResult;
use App\Declaration\Application\Result\NoData;
use App\Declaration\Application\Result\PaymentRequired;
use App\Declaration\Application\Result\ProfileIncomplete;
use Symfony\Component\HttpFoundation\Response;

trait HandlesDeclarationResult
{
    /**
     * Maps non-success DeclarationResult to a redirect Response, or null if result is PIT38WithSummary.
     */
    private function handleDeclarationResult(
        DeclarationResult $result,
        int $taxYear,
        string $formLabel,
    ): ?Response {
        if ($result instanceof PaymentRequired) {
            return $this->redirectToRoute('billing_checkout_page', [
                'product_code' => $result->requiredProduct->value,
            ]);
        }

        if ($result instanceof NoData) {
            $this->addFlash('warning', sprintf(
                'Brak danych -- wgraj CSV z transakcjami aby wygenerowac %s.',
                $formLabel,
            ));

            return $this->redirectToRoute('import_index');
        }

        if ($result instanceof ProfileIncomplete) {
            $this->addFlash('warning', sprintf(
                'Uzupelnij swoj NIP i dane osobowe w profilu, aby wygenerowac %s.',
                $formLabel,
            ));

            return $this->redirectToRoute('profile_edit');
        }

        return null;
    }
}
