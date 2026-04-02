<?php

declare(strict_types=1);

namespace App\Declaration\Domain\Service;

use App\Declaration\Domain\DTO\AuditReportData;
use App\Declaration\Domain\DTO\DividendEntry;
use App\Declaration\Domain\DTO\PriorYearLoss;
use App\TaxCalc\Domain\Model\ClosedPosition;
use Brick\Math\BigDecimal;

/**
 * Generuje raport audytowy w formacie HTML (potem konwertowany do PDF).
 *
 * Raport zawiera pelny audit trail: FIFO matching, dywidendy per kraj,
 * straty z lat poprzednich, podsumowania per instrument i per broker.
 *
 * Pure PHP — bez Twig, bez Symfony. HTML jako string.
 */
final class AuditReportGenerator
{
    public function generate(AuditReportData $data): string
    {
        $html = $this->renderHeader($data);
        $html .= $this->renderFIFOTable($data->closedPositions);
        $html .= $this->renderInstrumentSummary($data->closedPositions);
        $html .= $this->renderBrokerSummary($data->closedPositions);
        $html .= $this->renderDividendSection($data->dividends);
        $html .= $this->renderPriorYearLosses($data->priorYearLosses);
        $html .= $this->renderTotalSummary($data);
        $html .= $this->renderFooter();

        return $html;
    }

    private function renderHeader(AuditReportData $data): string
    {
        $year = $this->e((string) $data->taxYear);
        $name = $this->e($data->firstName . ' ' . $data->lastName);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="pl">
        <head>
            <meta charset="UTF-8">
            <title>Raport audytowy PIT-38 za {$year}</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 10px; margin: 20px; }
                h1 { font-size: 16px; border-bottom: 2px solid #333; padding-bottom: 5px; }
                h2 { font-size: 13px; margin-top: 20px; border-bottom: 1px solid #999; padding-bottom: 3px; }
                table { border-collapse: collapse; width: 100%; margin-top: 8px; }
                th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: right; }
                th { background: #f0f0f0; font-weight: bold; text-align: center; }
                td.left { text-align: left; }
                .summary-row { font-weight: bold; background: #fafafa; }
                .loss { color: #c00; }
                .gain { color: #060; }
                .footer { margin-top: 30px; font-size: 9px; color: #666; border-top: 1px solid #ccc; padding-top: 5px; }
            </style>
        </head>
        <body>
        <h1>Raport audytowy PIT-38 &mdash; rok podatkowy {$year}</h1>
        <p>Podatnik: {$name}</p>
        <p>Data wygenerowania: {$this->e(date('Y-m-d H:i:s'))}</p>
        HTML;
    }

    /**
     * @param list<ClosedPosition> $positions
     */
    private function renderFIFOTable(array $positions): string
    {
        if ($positions === []) {
            return '<h2>Tabela FIFO matching</h2><p>Brak zamknietych pozycji.</p>';
        }

        $rows = '';
        foreach ($positions as $pos) {
            $gainClass = $pos->gainLossPLN->isNegative() ? 'loss' : 'gain';

            $rows .= '<tr>'
                . '<td class="left">' . $this->e($pos->isin->toString()) . '</td>'
                . '<td>' . $this->e($pos->buyDate->format('Y-m-d')) . '</td>'
                . '<td>' . $this->e($pos->sellDate->format('Y-m-d')) . '</td>'
                . '<td>' . $this->e($pos->quantity->__toString()) . '</td>'
                . '<td>' . $this->e($pos->costBasisPLN->toScale(2)->__toString()) . '</td>'
                . '<td>' . $this->e($pos->proceedsPLN->toScale(2)->__toString()) . '</td>'
                . '<td>' . $this->e($pos->buyNBPRate->rate()->__toString()) . '</td>'
                . '<td>' . $this->e($pos->sellNBPRate->rate()->__toString()) . '</td>'
                . '<td>' . $this->e($pos->buyCommissionPLN->toScale(2)->__toString()) . '</td>'
                . '<td>' . $this->e($pos->sellCommissionPLN->toScale(2)->__toString()) . '</td>'
                . '<td class="' . $gainClass . '">' . $this->e($pos->gainLossPLN->toScale(2)->__toString()) . '</td>'
                . '</tr>';
        }

        return <<<HTML
        <h2>Tabela FIFO matching</h2>
        <table>
        <thead>
            <tr>
                <th>ISIN</th>
                <th>Data kupna</th>
                <th>Data sprzedazy</th>
                <th>Ilosc</th>
                <th>Koszt (PLN)</th>
                <th>Przychod (PLN)</th>
                <th>Kurs NBP kupno</th>
                <th>Kurs NBP sprzedaz</th>
                <th>Prowizja kupno (PLN)</th>
                <th>Prowizja sprzedaz (PLN)</th>
                <th>Zysk/Strata (PLN)</th>
            </tr>
        </thead>
        <tbody>
        {$rows}
        </tbody>
        </table>
        HTML;
    }

    /**
     * Podsumowanie per instrument (ISIN).
     *
     * @param list<ClosedPosition> $positions
     */
    private function renderInstrumentSummary(array $positions): string
    {
        return $this->renderGroupedSummary(
            positions: $positions,
            title: 'Podsumowanie per instrument',
            columnHeader: 'ISIN',
            keyExtractor: static fn (ClosedPosition $pos): string => $pos->isin->toString(),
        );
    }

    /**
     * Podsumowanie per broker.
     *
     * @param list<ClosedPosition> $positions
     */
    private function renderBrokerSummary(array $positions): string
    {
        return $this->renderGroupedSummary(
            positions: $positions,
            title: 'Podsumowanie per broker',
            columnHeader: 'Broker',
            keyExtractor: static fn (ClosedPosition $pos): string => $pos->sellBroker->toString(),
        );
    }

    /**
     * Shared rendering for grouped position summaries (per instrument, per broker).
     *
     * @param list<ClosedPosition> $positions
     * @param callable(ClosedPosition): string $keyExtractor
     */
    private function renderGroupedSummary(
        array $positions,
        string $title,
        string $columnHeader,
        callable $keyExtractor,
    ): string {
        if ($positions === []) {
            return '';
        }

        /** @var array<string, array{proceeds: BigDecimal, costs: BigDecimal, gainLoss: BigDecimal}> $grouped */
        $grouped = [];
        foreach ($positions as $pos) {
            $key = $keyExtractor($pos);
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'proceeds' => BigDecimal::zero(),
                    'costs' => BigDecimal::zero(),
                    'gainLoss' => BigDecimal::zero(),
                ];
            }
            $grouped[$key]['proceeds'] = $grouped[$key]['proceeds']->plus($pos->proceedsPLN->toScale(2));
            $grouped[$key]['costs'] = $grouped[$key]['costs']->plus($pos->costBasisPLN->toScale(2));
            $grouped[$key]['gainLoss'] = $grouped[$key]['gainLoss']->plus($pos->gainLossPLN->toScale(2));
        }

        $rows = '';
        foreach ($grouped as $key => $totals) {
            $gainClass = $totals['gainLoss']->isNegative() ? 'loss' : 'gain';
            $rows .= '<tr>'
                . '<td class="left">' . $this->e($key) . '</td>'
                . '<td>' . $this->e($totals['proceeds']->toScale(2)->__toString()) . '</td>'
                . '<td>' . $this->e($totals['costs']->toScale(2)->__toString()) . '</td>'
                . '<td class="' . $gainClass . '">' . $this->e($totals['gainLoss']->toScale(2)->__toString()) . '</td>'
                . '</tr>';
        }

        return <<<HTML
        <h2>{$this->e($title)}</h2>
        <table>
        <thead>
            <tr><th>{$this->e($columnHeader)}</th><th>Przychod (PLN)</th><th>Koszty (PLN)</th><th>Zysk/Strata (PLN)</th></tr>
        </thead>
        <tbody>{$rows}</tbody>
        </table>
        HTML;
    }

    /**
     * @param list<DividendEntry> $dividends
     */
    private function renderDividendSection(array $dividends): string
    {
        if ($dividends === []) {
            return '<h2>Dywidendy</h2><p>Brak dywidend.</p>';
        }

        // Grupowanie per kraj
        /** @var array<string, list<DividendEntry>> $byCountry */
        $byCountry = [];
        foreach ($dividends as $div) {
            $byCountry[$div->countryCode->value][] = $div;
        }

        $html = '<h2>Dywidendy per kraj</h2>';

        foreach ($byCountry as $country => $entries) {
            $html .= '<h3>' . $this->e($country) . '</h3>';
            $html .= '<table><thead><tr>'
                . '<th>Data</th><th>Instrument</th><th>Brutto (PLN)</th>'
                . '<th>WHT (PLN)</th><th>Netto (PLN)</th><th>Kurs NBP</th><th>Tabela NBP</th>'
                . '</tr></thead><tbody>';

            $totalGross = BigDecimal::zero();
            $totalWHT = BigDecimal::zero();

            foreach ($entries as $entry) {
                $html .= '<tr>'
                    . '<td>' . $this->e($entry->payDate->format('Y-m-d')) . '</td>'
                    . '<td class="left">' . $this->e($entry->instrumentName) . '</td>'
                    . '<td>' . $this->e($entry->grossAmountPLN) . '</td>'
                    . '<td>' . $this->e($entry->whtPLN) . '</td>'
                    . '<td>' . $this->e($entry->netAmountPLN) . '</td>'
                    . '<td>' . $this->e($entry->nbpRate) . '</td>'
                    . '<td>' . $this->e($entry->nbpTableNumber) . '</td>'
                    . '</tr>';

                $totalGross = $totalGross->plus(BigDecimal::of($entry->grossAmountPLN));
                $totalWHT = $totalWHT->plus(BigDecimal::of($entry->whtPLN));
            }

            $totalNet = $totalGross->minus($totalWHT);
            $html .= '<tr class="summary-row">'
                . '<td colspan="2" class="left">Suma ' . $this->e($country) . '</td>'
                . '<td>' . $this->e($totalGross->toScale(2)->__toString()) . '</td>'
                . '<td>' . $this->e($totalWHT->toScale(2)->__toString()) . '</td>'
                . '<td>' . $this->e($totalNet->toScale(2)->__toString()) . '</td>'
                . '<td colspan="2"></td>'
                . '</tr>';

            $html .= '</tbody></table>';
        }

        return $html;
    }

    /**
     * @param list<PriorYearLoss> $losses
     */
    private function renderPriorYearLosses(array $losses): string
    {
        if ($losses === []) {
            return '';
        }

        $rows = '';
        foreach ($losses as $loss) {
            $rows .= '<tr>'
                . '<td>' . $this->e((string) $loss->year) . '</td>'
                . '<td>' . $this->e($loss->amount) . '</td>'
                . '<td>' . $this->e($loss->deducted) . '</td>'
                . '</tr>';
        }

        return <<<HTML
        <h2>Straty z lat poprzednich</h2>
        <table>
        <thead>
            <tr><th>Rok</th><th>Kwota straty (PLN)</th><th>Odliczone (PLN)</th></tr>
        </thead>
        <tbody>{$rows}</tbody>
        </table>
        HTML;
    }

    private function renderTotalSummary(AuditReportData $data): string
    {
        return <<<HTML
        <h2>Podsumowanie koncowe</h2>
        <table>
        <tbody>
            <tr><td class="left">Przychod z odplatnego zbycia</td><td>{$this->e($data->totalProceeds)}</td></tr>
            <tr><td class="left">Koszty uzyskania przychodu</td><td>{$this->e($data->totalCosts)}</td></tr>
            <tr><td class="left">Dochod/Strata z odplatnego zbycia</td><td>{$this->e($data->totalGainLoss)}</td></tr>
            <tr><td class="left">Dywidendy brutto</td><td>{$this->e($data->totalDividendGross)}</td></tr>
            <tr><td class="left">WHT zaplacony za granica</td><td>{$this->e($data->totalDividendWHT)}</td></tr>
            <tr class="summary-row"><td class="left">Podatek nalezny ogolem</td><td>{$this->e($data->totalTax)}</td></tr>
        </tbody>
        </table>
        HTML;
    }

    private function renderFooter(): string
    {
        return <<<'HTML'
        <div class="footer">
            <p>Raport wygenerowany automatycznie przez TaxPilot. Niniejszy dokument stanowi
            material pomocniczy i nie zastepuje profesjonalnej porady podatkowej.</p>
        </div>
        </body>
        </html>
        HTML;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
