# Zbiorczy Raport Review: TaxPilot

## Metadata

| | |
|---|---|
| **Data** | 2026-04-03 |
| **Reviewer** | 4 agentów równolegle: Code Reviewer, Security Auditor, QA Lead, Legal+Tax Advisor |
| **Scope** | EVENT_STORMING.md, IMPLEMENTATION_PLAN.md, ARCHITECTURE.md, ADR-001 do ADR-011 |
| **Werdykt** | **BLOCK** — 11 unikalnych P0/BLOCKER findings. Implementacja NIE może rozpocząć się przed ich rozwiązaniem. |

---

## Podsumowanie ilościowe

| Reviewer | BLOCKER/P0 | CRITICAL/P1 | IMPORTANT/P2 | MINOR/P3 |
|---|---|---|---|---|
| Code Reviewer (Marek) | 4 | 10 | 10 | — |
| QA Lead (Kasia) | 4 | 7 | 7 | 5 |
| Security Auditor (Michał P.) | 7 | 12 | 9 | 6 |
| Legal+Tax (Mec. Wiśniewska + Tomasz) | 3 | 11 | ~10 | ~8 |
| **TOTAL (before dedup)** | **18** | **40** | **36** | **19** |

Po deduplikacji: **11 unikalnych BLOCKER-ów**, **~25 unikalnych P1**.

---

## BLOKERY (P0) — MUST FIX BEFORE IMPLEMENTATION

### B-01: Bug w alokacji prowizji buy przy partial sell
- **Zgłosił:** Code Reviewer + QA Lead
- **Plik:** ARCHITECTURE.md, `TaxPositionLedger::registerSell()`
- **Problem:** Prowizja buy dzielona przez `remainingQuantity` zamiast `originalQuantity`. Przy kolejnych partial sells suma alokowanych prowizji > faktyczna prowizja.
- **Fix:** Przechowywać `originalQuantity` na `OpenPosition`, używać jako denominator. Dodać property-based test: `sum(allocated_commissions) == original_commission`.
- **Review:** Tomasz [DP] potwierdza: "Każdy grosz na prowizji musi się zgadzać. US to sprawdzi."

### B-02: Money.toPLN nie waliduje currency match z NBPRate
- **Zgłosił:** Code Reviewer
- **Plik:** ARCHITECTURE.md, `Money::toPLN()`
- **Problem:** Przeliczenie USD Money kursem EUR NBPRate daje cichy błędny wynik.
- **Fix:** Guard: `if (!$this->currency->equals($rate->currency)) throw CurrencyMismatchException`.
- **Review:** Kasia [QA]: "To jest single point of failure. Jeden zły lookup = cały rok przeliczony źle."

### B-03: Multi-year FIFO — brak ciągłości między latami
- **Zgłosił:** QA Lead
- **Plik:** ARCHITECTURE.md, TaxPositionLedger scoped per TaxYear
- **Problem:** Agregat jest per (ISIN, User, TaxYear) — ale FIFO jest ciągłe cross-year. Akcja kupiona 2023, sprzedana 2026 wymaga open position z 2023.
- **Fix:** Agregat per (ISIN, User) BEZ year scope, lub explicit "opening balance snapshot" przy onboardingu. **Wymaga nowego ADR.**
- **Review:** Tomasz [DP]: "To jest 80% moich klientów. Buy and hold. Bez cross-year FIFO system jest bezużyteczny."

### B-04: Zaokrąglanie podatkowe — BŁĘDNA reguła
- **Zgłosił:** Legal (Mec. Wiśniewska)
- **Plik:** ADR-006, ADR-011, ARCHITECTURE.md
- **Problem:** Dokumenty mówią "do pełnego złotego W DÓŁ". Art. 63 §1 Ordynacji: **>= 50 groszy = w górę, < 50 groszy = w dół** (zaokrąglanie matematyczne). Dotyczy PODSTAWY opodatkowania, nie tylko podatku.
- **Fix:** Zmienić `RoundingMode::DOWN` na zaokrąglanie matematyczne per art. 63 §1. Podstawa → zaokrąglij → oblicz 19% → zaokrąglij.
- **Review:** Tomasz: "To zmienia KAŻDE obliczenie. Musi być poprawione ZANIM zaczniecie pisać testy."

### B-05: TaxPositionLedger — unbounded collection (ładuje ALL closed positions)
- **Zgłosił:** Code Reviewer
- **Plik:** ARCHITECTURE.md, Doctrine XML mapping
- **Problem:** OneToMany closedPositions z cascade-persist. Dla 5000 transakcji na jednym instrumencie — ładuje wszystko do pamięci.
- **Fix:** ClosedPositions append-only, nie ładowane do agregatu. FIFO operuje tylko na openPositions.
- **Review:** Aleksandra [perf]: "5k transakcji × 10 instrumentów = 50k obiektów w pamięci. OOM."

### B-06: IMPLEMENTATION_PLAN ma TypeScript/Next.js — ARCHITECTURE ma PHP/Symfony
- **Zgłosił:** Code Reviewer + QA Lead
- **Plik:** IMPLEMENTATION_PLAN.md (stary blok kodu TypeScript)
- **Problem:** Bloki kodu TS, drzewo projektu z .ts, referencje do Next.js, Fastify, decimal.js. Sprzeczność z ADR-002.
- **Fix:** Usunąć/zaktualizować stary blok TS w IMPLEMENTATION_PLAN. ADR-002 i ARCHITECTURE.md są authoritative.

### B-07: PIT-38 XML — brak walidacji XSD schema
- **Zgłosił:** QA Lead
- **Plik:** IMPLEMENTATION_PLAN.md (US-012)
- **Problem:** Brak walidacji XML w CI. User płaci, eksportuje XML, e-Deklaracje go odrzuca.
- **Fix:** XSD schema validation w testach. Test submission do test env MF (jeśli dostępny). Monitoring zmian schema.

### B-08: Granica doradztwo vs. narzędzie — generowanie PIT-38 XML
- **Zgłosił:** Legal (Mec. Wiśniewska)
- **Plik:** EVENT_STORMING.md, ogólnie
- **Problem:** Generowanie gotowego PIT-38 XML może być "sporządzaniem zeznań podatkowych" (art. 2 ust. 1 pkt 3 ustawy o doradztwie). Opinia prawna MUSI to rozstrzygnąć.
- **Fix:** Opinia prawna (G-001) musi jednoznacznie adresować ten punkt. Plan B: generujemy DANE (raport), nie gotowy XML.

### B-09: Zdarzenie #117 — "optymalizacja podatkowa" = doradztwo
- **Zgłosił:** Legal (Mec. Wiśniewska)
- **Plik:** EVENT_STORMING.md
- **Problem:** Nawet "informowanie" o optymalizacji to doradztwo (art. 2 ust. 1 pkt 1).
- **Fix:** Usunąć zdarzenie #117 z zakresu produktu. Kompletnie. Bez dyskusji.

### B-10: Brak DPIA (GDPR Art. 35)
- **Zgłosił:** Security Auditor
- **Problem:** Przetwarzanie danych finansowych na skalę wymaga DPIA PRZED rozpoczęciem przetwarzania. Brak = naruszenie GDPR.
- **Fix:** Zlecenie DPIA jako gate condition G-006. Przed napisaniem kodu.

### B-11: Rounding strategy — Money::of() zaokrągla natychmiast (scale 2)
- **Zgłosił:** QA Lead
- **Plik:** ARCHITECTURE.md, Money value object
- **Problem:** ADR-006 mówi "round at the end", ale Money::of() od razu `toScale(2)`. Intermediate calculations tracą precyzję. Kumulacja błędów na 5000 transakcjach.
- **Fix:** Money::of() zostawia precyzję (scale 8+). Zaokrąglanie do scale 2 dopiero przy: persistence, display, formularz PIT-38.

---

## CRITICAL (P1) — Top 15 (must fix before beta)

| # | Finding | Zgłosił | Fix |
|---|---|---|---|
| 1 | **Redis bez auth/TLS** — sesje + queue z danymi finansowymi | Security | ElastiCache AUTH + TLS. `rediss://` nie `redis://`. |
| 2 | **CSV upload: zero security** — brak size limit, CSV injection, malware scan | Security | Max 50MB, sanitize `=+@-`, UUID filename, ClamAV, rate limit. |
| 3 | **Brak secrets management** — Stripe keys, DB creds, KMS w .env | Security | ADR: AWS Secrets Manager, rotation policy, zero secrets w git. |
| 4 | **Magic link auth underspec** — token entropy, expiry, brute-force | Security | 256-bit token, 15min expiry, single-use, rate limit 3/15min. |
| 5 | **NIP encryption unspecified** — column-level, key rotation, blind index | Security | ADR: AES-256-GCM, KMS, HMAC blind index, never cache/log NIP. |
| 6 | **Brak data retention policy** — GDPR vs. Ordynacja Podatkowa | Security + Legal | ADR: anonymize PII on delete, retain financials 5 years, purge after. |
| 7 | **Race condition** — concurrent import modyfikuje ten sam ledger | QA | Optimistic locking (`version` column) + retry, lub sequential queue per user. |
| 8 | **Golden dataset: 20 to za mało** — brakuje fractional shares, same-day, DRIP, cross-year | QA | Rozszerzyć do 40+ scenariuszy. Priorytet: fractional, cross-year, exotic currencies. |
| 9 | **Timezone handling — brak ADR** — który timezone dla NBP rate? | QA + Code | ADR: UTC internal, CET/CEST for NBP "business day" determination. |
| 10 | **ISIN brak check digit** — Luhn validation missing | Code + QA | Implement ISIN Luhn validation w `ISIN::fromString()`. |
| 11 | **Transfer detection (HS-026)** — transfer wygląda jak sell+buy | QA | Flag "withdrawal/transfer" types, user confirmation, manual linking. |
| 12 | **WHT UK: 10% → 15%** — błędna stawka w tabeli UPO | Legal/Tax | UK UPO z PL: dividendy 15% (nie 10%). Poprawić tabelę. |
| 13 | **Art. 30a ust. 3 — błędny artykuł dla FIFO** | Legal | FIFO = art. 24 ust. 10 w zw. z art. 30b ust. 7. Poprawić wszędzie. |
| 14 | **NBPRate brak walidacji** — rate może być 0, negative, public properties | Code | Private constructor + factory, validate rate > 0, tableNumber format. |
| 15 | **Audit trail not tamper-proof** — PostgreSQL UPDATE/DELETE dostępne | Security | Revoke UPDATE/DELETE na audit tables. Hash chain. S3 Object Lock. |

---

## WYMAGANE NOWE ADR-y (przed implementacją)

| ADR | Temat | Owner | Trigger |
|---|---|---|---|
| ADR-012 | PII Encryption (NIP): AES-256-GCM, KMS, blind index | Security | C-02 |
| ADR-013 | Data Retention & GDPR: retention matrix, erasure policy | Security + Legal | C-03, HS-014 |
| ADR-014 | Secrets Management: AWS Secrets Manager, rotation | Security + DevOps | C-06 |
| ADR-015 | Authentication Security: magic link spec, session, 2FA option | Security | C-07 |
| ADR-016 | Timezone Handling: UTC storage, CET for NBP, per-broker config | QA + Tax Advisor | HS-005 |
| ADR-017 | Multi-Year FIFO: aggregate scope, opening balance | Dev + Tax Advisor | B-03 |
| ADR-018 | CSV Upload Security: size, type, injection, scanning | Security | C-05 |

---

## CO JEST DOBRZE (consensus all reviewers)

Wszyscy 4 reviewerzy podkreślają:

1. **NBPRate na każdej ClosedPosition** — audit trail by design. "Doskonałe" (Legal).
2. **brick/math z NEVER float** — niekompromisowe podejście do precyzji. "Prawidłowe" (Tax Advisor).
3. **Policy classes z artykułami ustaw w docblock** — "Audytor mógłby czytać kod i weryfikować" (Legal).
4. **ACL pattern dla broker adapters** — stateless, pure, testowalny. "Wzorcowe" (Code Reviewer).
5. **SwapFeeStrategy: ASK_USER** — poprawnie unika granicy doradztwa. "Eleganckie" (Legal).
6. **Crypto separation jako explicit policy** — chroni przed regresją. "Must have" (Tax Advisor).
7. **Property-based testing + mutation testing** — "Powyżej normy rynkowej" (QA Lead).

---

## DECISION: GO/NO-GO

### Werdykt: **CONDITIONAL BLOCK → GO po spełnieniu warunków**

**Przed rozpoczęciem implementacji (Tydzień 0-2):**

- [x] B-01: Fix commission allocation bug w ARCHITECTURE.md ✓ (commissionPerUnitPLN pre-computed)
- [x] B-02: Fix Money.toPLN currency validation ✓ (CurrencyMismatchException guard)
- [x] B-03: ADR-017 Multi-Year FIFO — nowy design agregatu ✓ (per UserId×ISIN, bez TaxYear)
- [x] B-04: Fix zaokrąglanie (art. 63 §1 — matematyczne, nie w dół) ✓ (ADR-006 updated)
- [x] B-05: Redesign closedPositions (append-only, nie ładowane do aggregatu) ✓ (newClosedPositions + flushNewClosedPositions)
- [ ] B-06: Usunąć TypeScript z IMPLEMENTATION_PLAN — stary blok do ręcznego cleanup
- [ ] B-08: Opinia prawna ws. generowania PIT-38 XML — wymaga zewnętrznej kancelarii
- [ ] B-09: Usunąć zdarzenie #117 (optymalizacja podatkowa) — wymaga edycji EVENT_STORMING.md
- [ ] B-10: Zlecić DPIA — wymaga zewnętrznego audytora
- [x] B-11: Fix rounding strategy ✓ (Money::of() nie zaokrągla, Money::rounded() na granicach)
- [x] Write ADR-012 do ADR-018 (7 nowych ADR-ów) ✓ (wszystkie 7 napisane)

**Przed beta (Tydzień 11-14):**

- [ ] Wszystkie P1 security findings (Redis, WAF, CSP, CSRF, audit trail)
- [ ] B-07: PIT-38 XSD validation w CI
- [ ] Golden dataset rozszerzony do 40+ scenariuszy
- [ ] Pentest + load test

---

*Raport wygenerowany przez Fully Featured AI Agent Team, 2026-04-03.*
*Review pipeline: Code Reviewer → Security Auditor → QA Lead → Legal+Tax Advisor.*
