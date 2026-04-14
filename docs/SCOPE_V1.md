# TaxPilot v1 — Scope Freeze

## Metadata

| | |
|---|---|
| Status | DRAFT — awaiting Product Owner confirmation |
| Purpose | Frozen scope for closed beta. Distinguishes v1 launch items from backlog. |
| Closes | BETA-BLK-001 when confirmed |
| Rule | Any feature not on this list is explicitly OUT of v1 scope. No exceptions without Tech Lead sign-off. |

---

## Supported Brokers

| Broker | Format | Adapter | Notes |
|---|---|---|---|
| Interactive Brokers (IBKR) | CSV Activity Statement | `IBKRActivityAdapter` | ISIN-based, multi-currency |
| Degiro — Account Statement | CSV | `DegiroAccountStatementAdapter` | Default export format |
| Degiro — Transactions | CSV | `DegiroTransactionsAdapter` | Alternative export format |
| Revolut Stocks | CSV | `RevolutStocksAdapter` | No ISIN — symbol-based FIFO |
| Bossa.pl | CSV History | `BossaHistoryAdapter` | Polish broker, PLN-native |
| XTB | XLSX Statement | `XTBStatementAdapter` | Closed Positions + Cash Operations sheets |

### Explicitly NOT supported in v1

| Broker | Reason |
|---|---|
| mBank eMakler | No sample CSV from PO (BLK-002) |
| Trading 212 | No adapter; blog article only |
| eToro | No adapter; blog article only |
| Saxo Bank | Not planned |
| BNP Paribas GOonline | Not planned |
| Any other broker | Not planned |

---

## Supported Tax Flow

| Step | What | Details |
|---|---|---|
| Import | Upload broker CSV/XLSX | File size limit: 10 MB; up to 5000 rows |
| Parse | Normalize to NormalizedTransaction | 6 adapters, see Supported Brokers above |
| Calculate | FIFO capital gains (art. 30b PIT) | Multi-year, multi-broker, fractional shares |
| Calculate | Dividend / WHT (art. 30a PIT) | Foreign dividends with UPO relief |
| Calculate | Prior year loss carry-forward (art. 9 ust. 3) | User-entered losses (manual input) |
| Declare | Generate PIT-38(18) XML | Tax year 2025 only; validated against official MF XSD |
| Export | Download XML for e-Deklaracje | User fills DataUrodzenia, KodUrzedu, adres before submitting |

### Tax year

- **Only 2025** (PIT-38 submitted in 2026, form variant 18)
- Prior year losses: manually entered for years 2020–2024
- Multi-year FIFO: positions opened before 2025 are carried forward correctly

### Explicitly NOT in v1 tax flow

- PIT-38 for years before 2025
- Auto-submission to e-Deklaracje (user downloads XML and submits manually)
- PIT-ZG integration (foreign income from UPO countries with progressive tax)
- XTB commission data (XTBStatementAdapter emits zero commission — XTB stores this in a separate report)
- Crypto tax calculation
- Stock options / RSU vesting

---

## Supported User Features

| Feature | Status |
|---|---|
| Magic link authentication (email) | In scope |
| Free / Standard / Pro subscription plans (Stripe) | In scope |
| Dashboard: calculation summary | In scope |
| Audit report (PDF-printable HTML) | In scope |
| Audit trail (tamper-proof closed positions log) | In scope |
| Prior year loss CRUD | In scope |
| GDPR: right to erasure (account deletion + anonymization) | In scope |
| Community format-error reporting | In scope |
| Blog articles (IBKR, Degiro, Revolut, Bossa, eToro, Trading212, ETF) | In scope |

### Explicitly NOT in v1 user features

| Feature | Reason |
|---|---|
| i18n (Ukrainian / English) | Planned for PIT-2027 season |
| Mobile app | Not planned |
| Direct broker API / OAuth integration | Not planned |
| Portfolio tracking / real-time prices | Not planned |
| Tax advisory or personalized tax guidance | Legal opinion pending (BETA-BLK-003) |

---

## Limits and Constraints

| Constraint | Value |
|---|---|
| Max CSV/XLSX file size | 10 MB |
| Supported tax year | 2025 |
| Max positions per user | ~5000 (SQL aggregation deferred, see P2-033) |
| Supported browsers | Evergreen (Chrome, Firefox, Safari, Edge) |
| Runtime | MyDevil (PHP 8.4, Postgres 17, Redis 7) |
| Language | Polish only |

---

## Confirmation Required

> **Product Owner must confirm this list before BETA-BLK-001 can be CLOSED.**
>
> Specifically confirm:
> 1. Broker list is correct and complete for v1
> 2. XTB is in scope (adapter exists and is functional)
> 3. mBank eMakler stays BLOCKED (BLK-002) — not in v1
> 4. Tax year 2025 only is correct scope
> 5. XTB zero-commission limitation is acceptable for v1

---

## Decision Record

| Date | Decision | Who |
|---|---|---|
| 2026-04-14 | DRAFT created from actual code state | Tech Lead |
| YYYY-MM-DD | CONFIRMED / AMENDED | Product Owner |
