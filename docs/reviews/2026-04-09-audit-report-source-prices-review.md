# Change Review — Audit Report Source Prices

- Data: 2026-04-09
- Reviewer role: senior dev
- Scope: audit report source prices and currencies, declaration audit trail transparency
- Diff: working tree batch before commit

## Findings

- Brak findings.

## Open Questions

- Czy puste dane zrodlowe w raporcie maja byc renderowane jako puste komorki, czy jawnie jako `brak danych`?

## Change Summary

- Audit report pokazuje teraz symbol oraz oryginalne ceny i waluty BUY/SELL.
- Builder raportu dolacza source transactions po `buyTransactionId` i `sellTransactionId`.
- Procesowy test raportu obejmuje provenance cross-broker i spojnosc z `TaxSummary`.
