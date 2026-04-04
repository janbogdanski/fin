# Content Research Notes

Generated: 2026-04-04

---

## Task 1: Screenshot Placeholder Audit (P2-087)

### Method

Each blog article was read in full. All `<!-- Screenshot: ... -->` HTML comments were extracted. The directory `/public/images/blog/` was checked for existence and contents.

### Public images directory status

The directory `/public/images/blog/` **does not exist**. The only files under `/public/` are:

- `public/index.php`
- `public/assets/styles/app.css`
- `public/robots.txt`

There are **zero image files** in the project's public directory.

### Screenshot placeholders found per article

| Article file | Placeholder text | Image file exists? |
|---|---|---|
| `rozliczenie-interactive-brokers.md` | `widok strony Statements w Client Portal z zaznaczonym Activity Statement i okresem Annual` | No |
| `rozliczenie-interactive-brokers.md` | `fragment sekcji Trades w Activity Statement — tabela z kolumnami Symbol, Date/Time, Quantity, T. Price, Proceeds, Comm/Fee` | No |
| `rozliczenie-degiro.md` | `widok strony Transactions w Degiro z zaznaczonym zakresem dat i przyciskiem Export` | No |
| `rozliczenie-degiro.md` | `widok Account Statement w Degiro z pozycjami dywidendowymi` | No |
| `rozliczenie-bossa.md` | `widok eksportu transakcji w panelu Bossa z zaznaczonym kontem Zagranica` | No |
| `pit-38-xml-e-deklaracje.md` | `strona główna e-Deklaracji z zaznaczonym przyciskiem "Wyślij e-Deklarację"` | No |

### Articles with zero placeholders

The following 6 articles contain no `<!-- Screenshot: ... -->` comments:

- `rozliczenie-pit-38-inwestycje-zagraniczne.md`
- `pit-8c-vs-pit-38-roznice.md`
- `rozliczenie-dywidend-zagranicznych.md`
- `rozliczenie-revolut.md`
- `metoda-fifo-pit-38.md`
- `pit-38-termin-2027.md`
- `strata-z-akcji-odliczenie.md`
- `kalkulator-podatku-gieldowego-porownanie.md`

### Summary

- Total placeholders: **6**
- Placeholders fulfilled (real image file exists): **0**
- Placeholders unfulfilled: **6**

All 6 placeholders are unfulfilled. The `/public/images/blog/` directory has not been created yet. No action was taken on the files — this is an audit only.

---

## Task 2: XTB NBP Rate Research (P2-092)

### Question

When exporting from XTB and importing into TaxPilot, does XTB already apply the NBP rate from the previous business day, or does it export raw foreign-currency prices?

### Code findings

**No XTB adapter exists in the codebase.**

A search for `XTB` and `xtb` strings across all PHP files under `src/BrokerImport/` returned zero matches. The adapters that exist are:

| Adapter class | Broker |
|---|---|
| `IBKRActivityAdapter` | Interactive Brokers |
| `RevolutStocksAdapter` | Revolut |
| `DegiroAccountStatementAdapter` | Degiro |
| `DegiroTransactionsAdapter` | Degiro |
| `BossaHistoryAdapter` | Bossa |

There is no `XtbAdapter` or equivalent.

### NormalizedTransaction DTO analysis

All adapters normalize rows into `NormalizedTransaction`, which stores:

- `pricePerUnit: Money` — a price with a currency code (e.g. `USD`, `EUR`)
- `commission: Money` — also with a currency code
- `date: \DateTimeImmutable` — transaction date

The DTO holds **raw foreign-currency values**. There is no PLN field, no NBP rate field, and no pre-converted PLN amount anywhere in the import pipeline. NBP conversion is applied downstream (after import, during FIFO/tax calculation), not during parsing.

This is consistent across all existing adapters — Revolut, IBKR, Degiro, and Bossa all output raw prices in the transaction currency.

### Blog article findings

XTB is mentioned in multiple articles, but always as a **Polish broker that issues PIT-8C**, not as a broker whose CSV TaxPilot parses directly. Key quotes:

- `rozliczenie-pit-38-inwestycje-zagraniczne.md` (line 337): *"Importujesz raport z brokera (IBKR Activity Statement, Degiro Transactions, XTB PIT-8C)."*
- `kalkulator-podatku-gieldowego-porownanie.md` (line 48–49): `XTB — historia transakcji (CSV)` listed as a supported import format, but no XTB adapter exists in the code.
- `pit-8c-vs-pit-38-roznice.md`: XTB is consistently listed alongside mBank and Bossa as a Polish broker that **sends PIT-8C** — pre-calculated PLN amounts.

### Conclusion

**Answer: (c) Something else — XTB is a Polish broker that sends PIT-8C.**

The correct model for XTB is:

1. XTB calculates gains/losses internally using their own methodology and issues a **PIT-8C** document (PLN amounts already computed).
2. The PIT-8C contains pre-converted totals (przychód, koszt, dochód) in PLN — users read these figures from PIT-8C and enter them into PIT-38.
3. TaxPilot does **not** have an XTB adapter that parses raw XTB CSV files. The blog articles and marketing copy mention "XTB CSV" as a supported import, but the implementation does not exist yet.
4. The question of "which NBP rate does XTB use" is therefore moot for TaxPilot's direct import flow. When XTB issues PIT-8C, they apply their own rate methodology. TaxPilot does not re-derive the rate from raw XTB data because no raw XTB data is parsed.

### Critical gap identified

The `kalkulator-podatku-gieldowego-porownanie.md` article claims TaxPilot supports `XTB — historia transakcji (CSV)` as an import format. **This is not true at the code level.** No XTB adapter exists. This is a content/marketing accuracy issue that should be tracked.

Additionally, art. 11a ustawy o PIT requires using the NBP rate from the **last business day before the transaction date**. XTB as a Polish broker that issues PIT-8C likely applies this rule correctly (they are subject to Polish tax law obligations as a Polish broker). However, this cannot be verified from the codebase alone — it would require inspecting an actual XTB PIT-8C document.

### Recommendation

1. **Content fix (P0 accuracy):** Remove or qualify the claim that TaxPilot supports XTB CSV import in `kalkulator-podatku-gieldowego-porownanie.md` until the adapter is built.
2. **Feature gap:** If XTB CSV import is on the roadmap, build the `XtbAdapter` and verify what NBP rate logic XTB applies in their exports (transaction-date rate vs. previous business day rate).
3. **PIT-8C import flow:** If the intended UX for XTB users is to enter PIT-8C figures manually, this should be documented explicitly in the XTB onboarding flow and in any article mentioning XTB.
