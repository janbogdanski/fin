# Change Review — Dashboard Calculation Source Prices

- Data: 2026-04-09
- Reviewer role: senior dev
- Scope: calculation dashboard transparency, shared dashboard source transaction lookup, filter behavior
- Diff: working tree batch before commit

## Findings

- Brak findings.

## Open Questions

- Czy w następnym kroku chcemy dodać filtr po wyniku (`zysk`, `strata`, `zero`) zamiast samego brokera i instrumentu?

## Change Summary

- Widok `dashboard/calculation` pokazuje teraz buy/sell source prices, waluty, brokerów i kursy NBP w jednym wierszu.
- Lookup źródłowych transakcji został ujednolicony we wspólnym serwisie dashboardu.
- Web test zabezpiecza realny HTML, a filtr po brokerze działa także dla wierszy cross-broker.
