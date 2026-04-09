# Change Review — Dashboard FIFO Source Prices

- Data: 2026-04-09
- Reviewer role: senior dev
- Scope: dashboard FIFO transparency, source prices and currencies in user-facing UI
- Diff: working tree batch before commit

## Findings

- Brak findings.

## Open Questions

- Czy przy brakujacych danych zrodlowych w wierszu FIFO zostajemy przy `brak danych`, czy chcemy osobny badge typu `niezmapowane`?

## Change Summary

- Dashboard FIFO pokazuje teraz symbol, broker buy/sell oraz oryginalne ceny i waluty z eksportu brokera.
- Widok zachowuje kursy NBP, koszt PLN, przychod PLN i wynik, wiec user widzi pelne uzasadnienie wiersza.
- Web test zabezpiecza realny render HTML z seeded imported transactions i closed position.
