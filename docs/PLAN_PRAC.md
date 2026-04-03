# Plan Prac: TaxPilot v1

## Metadata

| | |
|---|---|
| **Data** | 2026-04-03 |
| **Target** | Public Beta: luty 2027 (sezon PIT-38 za rok 2026) |
| **Infra** | MyDevil.net + Cloudflare (ADR-009) |
| **Stack** | PHP 8.4 + Symfony 7.2 + Twig/Hotwire + PostgreSQL + Redis |
| **Scope v1** | Akcje + ETF + dywidendy zagraniczne + FIFO cross-broker, 3 brokerzy |
| **Input** | EVENT_STORMING.md, ARCHITECTURE.md, 18 ADR-ów, REVIEW_CONSOLIDATED.md |

---

## Faza 0: Gate (kwiecień 2026) — 3 tygodnie

### Cel: spełnić warunki brzegowe ZANIM napiszemy linię kodu

| Tydzień | Zadanie | Owner | Deliverable | Bloker |
|---|---|---|---|---|
| 0-1 | Zlecenie opinii prawnej: narzędzie vs. doradztwo + generowanie PIT-38 XML | Mec. Wiśniewska | Pisemna opinia prawna kancelarii FinTech | B-08 |
| 0-1 | Zlecenie DPIA | Łukasz [risk] | DPIA document (GDPR Art. 35) | B-10 |
| 0-1 | Landing page + waitlist: taxpilot.pl | Paweł [front] + Zofia | Strona + formularz, deploy na MyDevil | — |
| 0-2 | Kampania waitlist: 1000 zapisów | Łukasz | Reddit, FB grupy, Twitter/X, fora | — |
| 0-2 | Golden dataset v1: 20 zestawów (Tomasz) | Tomasz [DP] | 20 CSV + expected results + komentarze | — |
| 0-1 | Usunięcie zdarzenia #117 z EVENT_STORMING | Marek [senior-dev] | PR do EVENT_STORMING.md | B-09 |
| 0-1 | Cleanup TypeScript z IMPLEMENTATION_PLAN | Marek [senior-dev] | PR do IMPLEMENTATION_PLAN.md | B-06 |
| 0-2 | Ubezpieczenie OC | Łukasz [risk] | Polisa OC dla sp. z o.o. | — |
| 0-2 | Rejestracja spółki + regulamin | Łukasz + Mec. Wiśniewska | KRS + regulamin serwisu | — |

### Gate Decision (koniec tygodnia 2)

| Warunek | Wymagany? |
|---|---|
| Opinia prawna: pozytywna (narzędzie, nie doradztwo) | **MUST** — bez tego stop |
| Waitlist: >500 zapisów | **SHOULD** — <200 = pivot discussion |
| Golden dataset: 20 zestawów gotowych | **MUST** |
| DPIA: zlecona (nie musi być ukończona) | **MUST** |
| Ubezpieczenie OC: oferta | **SHOULD** |

**Jeśli opinia prawna negatywna** → Pivot: B2B tool for tax advisors (plan Advisor), nie B2C kalkulator.

---

## Faza 1: Walking Skeleton (maj-czerwiec 2026) — 4 tygodnie

### Cel: CSV z IBKR → obliczony zysk/strata → wynik na ekranie. End-to-end.

### Sprint 1 (tydzień 3-4): "CSV to Number"

**Cel: User wgrywa CSV z IBKR → widzi FIFO matching → zysk/strata w PLN.**

| # | Story | Owner | TDD test first | DoD |
|---|---|---|---|---|
| 1 | **Infra: Symfony skeleton + Docker Compose + CI** | Bartek [devops] | — | `make dev` działa, GH Actions green (lint+stan+test) |
| 2 | **Deploy: MyDevil + Deployer + Cloudflare** | Bartek [devops] | — | `make deploy` → taxpilot.pl responds 200 |
| 3 | **Auth: magic link login** | Marek [senior-dev] | Test: token 256-bit, single-use, 15min expiry | User loguje się emailem (ADR-015) |
| 4 | **Domain: Money value object** | Marek [senior-dev] | Test: arithmetic, toPLN z guard, rounded() | brick/math, property tests |
| 5 | **Domain: ISIN value object** | Marek [senior-dev] | Test: format + Luhn check digit | Odrzuca invalid ISINs |
| 6 | **Domain: NBPRate value object** | Marek [senior-dev] | Test: factory validates rate > 0, table format | Private constructor, factory |
| 7 | **Domain: TaxPositionLedger (FIFO basic)** | Marek [senior-dev] | Test: golden dataset #1, #2, #3 | Buy/sell, FIFO matching, cross-broker |
| 8 | **Infra: NBP API client + Redis cache** | Ania [data] | Test: pobiera kurs, cache hit, dzień wolny fallback | Batch download, cache per waluta+data |
| 9 | **Infra: IBKR CSV adapter (ACL)** | Ania [data] | Test: 5 sample IBKR files | Parsuje Activity Statement → NormalizedTransaction |
| 10 | **UI: Upload CSV → progress → wynik** | Paweł [front] + Zofia | — | Upload → tabela transakcji → zysk/strata per instrument |
| 11 | **Security: NIP encryption (sodium)** | Michał P. [security] | Test: encrypt/decrypt roundtrip, blind index | ADR-012 implemented |
| 12 | **Security: CSV upload controls** | Michał P. [security] | Test: size limit, injection sanitize | ADR-018 implemented |

**Review na koniec sprintu:**
- Kasia [QA]: golden dataset #1-3 pass?
- Tomasz [DP]: wynik dla prostego case = poprawny co do grosza?

### Sprint 2 (tydzień 5-6): "Multi-Broker + Dividends"

**Cel: 3 brokerzy, dywidendy zagraniczne, PIT-38 preview.**

| # | Story | Owner | TDD test first | DoD |
|---|---|---|---|---|
| 13 | **Import: Degiro CSV adapter** | Ania [data] | Test: 5 sample Degiro files | Parse → NormalizedTransaction |
| 14 | **Import: XTB CSV adapter** | Ania [data] | Test: 5 sample XTB files | Parse → NormalizedTransaction |
| 15 | **Domain: Cross-broker FIFO** | Marek [senior-dev] | Test: golden dataset #3 (IBKR buy + Degiro sell) | FIFO per ISIN, not per broker |
| 16 | **Domain: DividendTaxService** | Marek [senior-dev] | Test: golden dataset #5-7 (USA/UK/DE dividends) | WHT, UPO, dopłata do 19% |
| 17 | **Domain: UPO Registry** | Marek [senior-dev] | Test: 10 krajów, poprawne stawki (UK=15%!) | DB seed z tabelą UPO |
| 18 | **Domain: LossCarryForwardPolicy** | Marek [senior-dev] | Test: golden dataset #8 (straty z lat poprzednich) | 50% limit, 5 lat, osobne koszyki |
| 19 | **UI: PIT-38 preview** | Paweł [front] + Zofia | — | Formularz z wypełnionymi polami, drill-down |
| 20 | **UI: Dashboard — roczne podsumowanie** | Zofia [front] | — | Przychód, koszt, zysk/strata, per instrument type |
| 21 | **Audit: basic trail** | Marek [senior-dev] | Test: każda kalkulacja zapisana z kursami NBP | Drill-down per transakcja |
| 22 | **Import: Duplicate detection** | Ania [data] | Test: re-import tego samego pliku | Hash-based, user confirmation |
| 23 | **Domain: Zaokrąglanie art. 63 §1** | Marek [senior-dev] | Test: >= 50gr w górę, < 50gr w dół | TaxRoundingPolicy correct |

**Review na koniec sprintu:**
- Golden dataset #1-10 ALL PASS
- Tomasz [DP]: dywidendy z 5 krajów = poprawne
- Kasia [QA]: cross-broker FIFO edge cases

---

## Faza 2: Core Features (lipiec-sierpień 2026) — 4 tygodnie

### Sprint 3 (tydzień 7-8): "Declaration Ready"

| # | Story | Owner | DoD |
|---|---|---|---|
| 24 | PIT-38 XML export (e-Deklaracje schema) + XSD validation w CI | Ania [data] | XML valid per schema MF, test w CI |
| 25 | PIT/ZG XML per kraj | Ania [data] | Osobny PIT/ZG per kraj dywidendy |
| 26 | PDF export (audit trail raport) | Paweł [front] | PDF z drill-down obliczeń |
| 27 | Prior year loss — input UI + calculation | Marek + Zofia | Suwak straty (default=0, brak sugestii!) |
| 28 | PIT-8C upload + porównanie z obliczeniami | Ania [data] | Rozbieżności highlighted |
| 29 | Error handling UX — brakujące dane, partial import | Zofia [front] | Jasne komunikaty, akcje naprawcze |
| 30 | Optimistic locking na TaxPositionLedger | Marek [senior-dev] | Version column, retry on conflict |
| 31 | Rate limiting (Cloudflare + Symfony) | Michał P. [security] | Per ADR-015 limits |
| 32 | CSRF + security headers + CSP | Michał P. [security] | Symfony CSRF, CSP header, SameSite |

### Sprint 4 (tydzień 9-10): "Billing + Polish"

| # | Story | Owner | DoD |
|---|---|---|---|
| 33 | Stripe integration (Free / Basic 79 / Pro 149 PLN) | Paweł [front] | Webhook verified, subscription active |
| 34 | Paywall: obliczenie free, export paid | Paweł [front] | Free: preview, Paid: XML+PDF |
| 35 | Onboarding wizard (brokerzy, instrumenty, rok) | Zofia [front] | 3-step wizard |
| 36 | Manual transaction add/edit/delete | Paweł [front] | CRUD z recalculation trigger |
| 37 | Email: magic link, reminder 30.04 | Ania [data] | Resend/Mailgun integration |
| 38 | GDPR: data export (JSON) + account deletion (anonymize) | Michał P. [security] | Per ADR-013 |
| 39 | Eksport danych dla doradcy podatkowego (Excel) | Paweł [front] | CSV/Excel z FIFO matching |
| 40 | Golden dataset rozszerzony: 40+ scenariuszy | Kasia [QA] + Tomasz | Fractional, same-day, cross-year, exotic |
| 41 | Timezone handling per broker | Marek [senior-dev] | Per ADR-016 |
| 42 | Transfer detection (HS-026) — warning + manual link | Ania [data] | Flag withdrawal, user confirms |

---

## Faza 3: Hardening (wrzesień-październik 2026) — 4 tygodnie

### Sprint 5 (tydzień 11-12): "Security + Load"

| # | Zadanie | Owner | DoD |
|---|---|---|---|
| 43 | Pentest (zewnętrzna firma lub crowd-security) | Michał P. [security] | Raport, 0 findings P0/P1 |
| 44 | Load test: 500 concurrent users, 50k transakcji | Aleksandra [perf] | p95 < 5s dla obliczenia |
| 45 | Mutation testing: MSI > 80% na Domain | Michał W. [QA] | Infection report green |
| 46 | Audit trail: immutable (REVOKE UPDATE/DELETE) | Michał P. [security] | Tamper-proof per review |
| 47 | DPIA: finalizacja | Łukasz [risk] | Podpisany dokument |
| 48 | Regulamin: final review prawny | Mec. Wiśniewska | Disclaimer, OC, GDPR |
| 49 | Backup + restore test | Bartek [devops] | pg_dump → R2 → restore = dane OK |
| 50 | Health monitoring: /health endpoint + UptimeRobot | Sylwester [SRE] | Alert na downtime |

### Sprint 6 (tydzień 13-14): "Beta + Fixes"

| # | Zadanie | Owner | DoD |
|---|---|---|---|
| 51 | Beta invites: 100 userów z waitlist | Łukasz [risk] | Zróżnicowane profile (daytrader, pasywny, krypto) |
| 52 | In-app feedback widget | Zofia [front] | Prosty formularz na każdym ekranie |
| 53 | Beta bug fixing: P0 natychmiast, P1 w sprincie | Marek + Paweł | 0 P0 na koniec beta |
| 54 | Tomasz waliduje 10 obliczeń beta userów | Tomasz [DP] | Doradca podatkowy potwierdza poprawność |
| 55 | Legal review final | Mec. Wiśniewska | Regulamin + disclaimer zamrożone |
| 56 | Performance tuning na real data | Aleksandra [perf] | Pre-compute, cache optimization |

---

## Faza 4: Launch (listopad 2026 - styczeń 2027) — 8 tygodni

### Sprint 7-8 (tydzień 15-18): "Closed Beta"

| # | Zadanie | Owner | DoD |
|---|---|---|---|
| 57 | Closed beta: 500 userów | Łukasz | Feedback loops, weekly calls |
| 58 | Bug fixing + UX improvements z feedbacku | Team | NPS > 30 |
| 59 | SEO + content marketing (blog: "Jak rozliczyć PIT-38 z IBKR") | Paweł | 5 artykułów, organic traffic |
| 60 | Dociągnięcie golden dataset do 50+ scenariuszy | Kasia + Tomasz | Regression suite complete |

### Sprint 9-10 (tydzień 19-22): "Public Beta Launch"

| # | Zadanie | Owner | DoD |
|---|---|---|---|
| 61 | **PUBLIC BETA LAUNCH — luty 2027** | ALL | taxpilot.pl live, payment active |
| 62 | Monitoring sezon podatkowy | Sylwester [SRE] | Uptime 99.9%, error rate < 0.1% |
| 63 | Support: FAQ + chatbot + community forum | Zofia + Paweł | Self-service first |
| 64 | Iteracja na feedback sezonu | Team | Weekly releases |

---

## Kamienie milowe

```
2026-04-03  Event Storming + Architecture DONE ◄── JESTEŚMY TU
     │
2026-04 w3  Gate Decision (opinia prawna + waitlist)
     │
2026-05     Sprint 1: CSV to Number (IBKR + FIFO basic)
2026-06     Sprint 2: Multi-broker + Dividends + PIT-38 preview
     │
2026-07     Sprint 3: Declaration Ready (XML export, PIT/ZG)
2026-08     Sprint 4: Billing + Polish
     │
2026-09     Sprint 5: Security + Load testing
2026-10     Sprint 6: Beta 100 userów
     │
2026-11     Sprint 7-8: Closed Beta 500 userów
2027-01     Sprint 9: Final prep
     │
2027-02     ★ PUBLIC BETA LAUNCH ★
2027-04-30  Deadline PIT-38 za 2026
```

---

## Budżet do launch

| Pozycja | Koszt | Uwagi |
|---|---|---|
| MyDevil hosting (10 mies.) | 800 PLN | ~80 PLN/mies |
| Domena taxpilot.pl | 50 PLN | Roczna |
| Opinia prawna | 3 000-5 000 PLN | Kancelaria FinTech |
| DPIA | 2 000-5 000 PLN | Audytor GDPR |
| Ubezpieczenie OC | 5 000-15 000 PLN | Roczna polisa |
| Rejestracja sp. z o.o. | 600 PLN | KRS + notariusz |
| Kampania waitlist (ads) | 2 000 PLN | Reddit, FB, Google |
| Pentest | 5 000-15 000 PLN | Zewnętrzna firma |
| Stripe fees (beta) | ~500 PLN | 1.4% + 0.25 EUR per transaction |
| **TOTAL** | **19 000 - 44 000 PLN** | **Bez wynagrodzeń zespołu** |

---

## Ryzyka timeline

| Ryzyko | Impact | Mitigation |
|---|---|---|
| Opinia prawna negatywna | STOP → pivot B2B | Mieć plan B gotowy w tygodniu 0 |
| Waitlist < 200 | Czy jest rynek? | Zmienić messaging, inne kanały, dłuższa kampania |
| Golden dataset opóźniony | Sprint 1 bez testów akceptacyjnych | Tomasz dostaje template + deadline w tygodniu 0 |
| Sezon podatkowy miss (luty 2027) | Czekamy rok | Scope freeze — cut features, not quality |
| MyDevil nie daje rady na peak | Degradacja UX | Cloudflare cache + pre-compute + monitoring |
| Zmiana schema XML e-Deklaracje przez MF | XML export nie działa | Monitoring strony MF, XSD validation w CI |

---

## KPI

### Pre-Launch

| KPI | Target |
|---|---|
| Waitlist signups | >1 000 |
| Golden dataset accuracy | 100% (40+/40+ pass) |
| Pentest P0/P1 findings | 0 |
| PIT-38 XML e-Deklaracje valid | Pass |
| Mutation testing MSI (Domain) | >80% |

### Sezon 2027 (luty-kwiecień)

| KPI | Target |
|---|---|
| Registered users | 5 000 |
| Paid conversions | 500 (10%) |
| PIT-38 exports | 400 |
| User-reported accuracy | >98% |
| NPS | >40 |
| Uptime | 99.9% |

---

*Plan prac: Fully Featured AI Agent Team, 2026-04-03.*
*Zatwierdzenie: Mariusz Gil (architektura) + Łukasz [risk] (biznes).*
