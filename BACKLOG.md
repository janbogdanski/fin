# TaxPilot — Product Backlog

Jedno zrodlo prawdy. Wszystkie findings z review, retro, QA, security, legal trafiaja tutaj.

**Zasady:**
- Kazdy item ma source (skad przyszedl)
- P0 = bloker (fix TERAZ), P1 = before next release, P2 = tech debt, P3 = nice to have
- Zrobione -> przeniez do DONE z data
- Sprint assignment = kiedy planujemy

---

## P0 — Blockery

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P0-001 | DividendTaxService nie cappuje WHT do stawki UPO (art. 30a ust. 2) | Code Review S1+2, QA S3 | 4 | DONE |
| P0-002 | OpenPosition.reduceQuantity() brak guard na negative | Code Review S1+2, QA S3 | 4 | DONE |
| P0-003 | AuditReportGenerator uzywa bcmath zamiast brick/math + DRY violation | Code Review S1+2, Code S3 | 4 | DONE |
| P0-007 | Brak CSRF token na upload CSV form | Security S3 | 4 | DONE |
| P0-008 | Brak auth — access_control: [] (wszystkie endpointy publiczne) | Security S3 | 4 | DONE |
| P0-009 | registerSell() brak atomowosci — partial fail = corrupted aggregate | QA S3 | 4 | DONE |
| ~~P0-010~~ | AT-003: PriorYearLoss mutable po użyciu — brak locked_at / usage check w save() i delete(); user może edytować stratę po wygenerowaniu PIT-38 | Audit Trail S13 | 13 | DONE — used_in_years JSON col, guard w repo/controller, 11 unit testów |

## P1 — Before Next Release

### Architecture (Code Review S3)

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P1-027 | Reflection hack w DoctrineTaxPositionLedgerRepository -> dodac reconstitute() | Code S3 | 4 | DONE |
| P1-028 | TaxPositionLedger hard-coupled do static CurrencyConverter -> inject lub pre-convert | Code S3 | 4 | DONE |
| P1-029 | GetTaxSummaryHandler wywoluje Command handler -> CQRS violation | Code S3 | 4 | DONE |
| P1-030 | AnnualTaxCalculation 388 linii, SRP violation -> wydzielic snapshot DTO | Code S3 | 4 | DONE |
| P1-031 | Declaration\Domain importuje TaxCalc\Domain\Model -> Dependency Rule violation | Code S3 | 4 | DONE |

### Security (Security Audit S3)

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P1-032 | PII (NIP, imie) w preview bez auth | Security S3 | 4 | DONE |
| P1-033 | Weak/default .env keys — enforcement missing | Security S3 | 4 | DONE — CHANGE_ME placeholders + .env.local override pattern (standard Symfony) |
| P1-034 | CDN scripts bez SRI — integrity attrs missing | Security S3 | 4 | N/A — CDN removed, all assets local (Tailwind CLI standalone) |
| P1-035 | Brak security headers (CSP, X-Frame-Options, HSTS) | Security S3 | 4 | DONE |
| P1-036 | PIT38Data brak walidacji NIP/kwot -> invalid XML dla e-Deklaracje | Security S3, QA S3 | 4 | DONE |
| P1-037 | DeclarationController exportXml() — raw XML concat zamiast generatora | Security S3 | 4 | DONE |

### Performance (Perf Review S3)

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P1-038 | CSV explode() 50MB -> 10MB limit, streaming deferred P2 | Perf S3 | 4 | DONE |
| P1-039 | FIFO usort() po kazdym registerBuy() -> O(N * n log n) | Perf S3 | 4 | DONE |
| P1-040 | removeOpenPosition() array_filter O(n) per remove -> O(K*N) total | Perf S3 | 4 | DONE |
| P1-041 | syncOpenPositions — batch INSERT done, UPSERT not | Perf S3 | 4 | PARTIAL — DELETE+INSERT, not UPSERT |
| P1-042 | Brak composite index (isin, sell_date) na closed_positions | Perf S3 | 4 | DONE |
| P1-043 | getRatesForDateRange() nie cachowane — 250 HTTP calls cold start | Perf S3 | 4 | DONE |
| P1-044 | insertClosedPositions individual INSERT -> batch multi-row | Perf S3 | 4 | DONE |

### QA (QA Audit S3)

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P1-045 | Same-date buy FIFO ordering non-deterministic (usort instability) | QA S3 | 4 | DONE |
| P1-046 | Revolut brak ISIN -> cross-broker FIFO nie dziala | QA S3 | 4 | DONE |
| P1-047 | Prior year losses — adapter returns empty array | QA S3 | 8 | DONE — PriorYearLossRepository (CRUD) + DoctrinePriorYearLossQueryAdapter (read-side with LossCarryForwardPolicy) |
| P1-048 | Brak testu: buy 50, sell 100 (partial consume + exception + state) | QA S3 | 4 | DONE |

### QA (QA Audit S11)

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P1-049 | Brak DoctrineImportStorageTest + DoctrinePriorYearLossCrudTest (repository-contract suite) | QA S11 | 11 | DONE — DoctrineImportStorageTest + DoctrinePriorYearLossCrudTest extend contract bases via KernelTestCase |
| P1-050 | Brak contract testów dla 5 z 7 output portów (ClosedPositionQueryPort, DividendResultQueryPort, PriorYearLossQueryPort, FifoProcessorPort, DividendProcessorPort) | QA S11 | 12 | DONE — 3 repo ports covered (ClosedPositionQuery, DividendResult write+read, PriorYearLossQuery); FifoProcessorPort + DividendProcessorPort są service ports — contract tests nie mają zastosowania |
| P1-051 | E2E nie pokrywa billing payment gate flow (plan upgrade → success/failure) | QA S11 | 12 | DONE — BillingControllerWebTest: 5 testów (webhook 400, empty payload 400, unauth redirect, invalid CSRF 403, checkout happy path redirect); fix Payment enum persistence (custom DBAL types ProductCodeType+PaymentStatusType) |
| P1-052 | date('Y') w 15+ testach bez ClockInterface — flaky po 31.12 | QA S11 | 12 | DONE — TESTING_YEAR=2026 constant + MockClock override w PriorYearLossControllerWebTest |

### Existing P1 (from S1+2)

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P1-010 | CSRF on upload endpoint | Security S1+2 | 4 | MERGED -> P0-007 |
| P1-011 | NIP value object z walidacja (check digit) | Code S1+2 | 4 | MERGED -> P1-036 |
| P1-012 | Revolut: 1 warning per import, nie per transaction | Code S1+2 | 4 | DONE |
| P1-013 | easter_date() -> easter_days() (32-bit safety) | Code S1+2 | 4 | DONE |
| P1-014 | CachedProvider cachuje po transactionDate, nie effectiveDate | Code S1+2 | 4 | DONE |
| P1-015 | Duplicate detection na import | Sprint 2 debt | 4 | DONE |
| P1-016 | Stripe billing integration | Plan | 6 | DONE |
| P1-017 | Wiring: Import -> Calculate -> Declaration (full flow) | Retro S1+2 | 7 | PARTIAL — NIP hardcoded→fixed, dividends=[], losses=[] |
| P1-018 | Redis auth + TLS (produkcja) | Security S1+2 | — | TODO |
| P1-019 | File size limit: 50MB -> 5MB | Security S1+2 | 4 | MERGED -> P1-038 |
| P1-020 | Degiro supports() false positive | Code S1+2 | 6 | DONE |
| P1-021 | equityLossDeduction nie waliduje czy > equityGainLoss | Code S1+2 | 6 | DONE |
| P1-022 | ImportController test (WebTestCase) | QA S1+2 | 5 | DONE |
| P1-023 | UTF-8 BOM handling w adapterach | QA S1+2 | 7 | DONE — all 5 adapters use CsvSanitizer::stripBom() in both supports() and parse() |
| P1-024 | ClosedPosition gainLoss invariant check | QA S1+2 | 6 | DONE |
| P1-025 | Wyrownanie testow adapterow do poziomu IBKR | QA S1+2 | 7 | DONE — all 5 adapters have 19 tests each |
| P1-026 | Audit trail tamper-proof | Security S0 | 7 | DONE — ClosedPositionImmutabilityListener blocks UPDATE/DELETE |

## P2 — Tech Debt

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P2-001 | IMPLEMENTATION_PLAN.md — usunac TypeScript blok | Review S0 | — | N/A (file does not exist) |
| P2-002 | EVENT_STORMING.md — usunac zdarzenie #117 | Review S0 | — | N/A (file does not exist) |
| P2-003 | AdapterRegistry kolejnosc niedeterministyczna (priority) | Code S1+2 | 6 | DONE |
| P2-004 | AnnualTaxCalculation = "God Aggregate" -> Basket VOs | Code S1+2 | — | MERGED -> P1-030 |
| P2-005 | UPORegistry hardcoded -> config/database | Code S1+2 | 6 | DONE |
| P2-006 | Naming collision: 2x PriorYearLoss | Code S1+2 | 6 | DONE |
| P2-007 | getRatesForDateRange() nie cachowane | Code S1+2 | — | MERGED -> P1-043 |
| P2-008 | Explicit timezone handling (Europe/Warsaw) | Code S1+2 | 6 | DONE |
| P2-009 | Property-based tests dla FIFO | QA S1+2 | 6 | DONE |
| P2-010 | AuditReport: Declaration DTO zamiast ClosedPosition coupling | Code S1+2 | — | MERGED -> P1-031 |
| P2-011 | Test PriorYearLoss walidacji constructor | QA S1+2 | 6 | DONE |
| P2-012 | Test TaxYear walidacji | QA S1+2 | 6 | DONE |
| P2-013 | Test idempotentnosci finalize() | QA S1+2 | 6 | DONE |
| P2-015 | Docker-compose: .env file zamiast hardcoded credentials | Security S1+2 | — | MERGED -> P1-033 |
| P2-016 | MIME type check: walidacja rozszerzenia .csv | Security S1+2 | 6 | DONE |
| P2-017 | Information disclosure w exception messages | Security S1+2 | 6 | DONE |
| P2-018 | CSV sanitize: dodac \n do stripowanych znakow | Security S1+2 | 5 | DONE |
| P2-019 | Rate limiting na upload endpoint (10/10min per IP) | Security S1+2 | 5 | DONE |
| P2-020 | DashboardController mock data -> wydzielic do provider | Code S3 | 6 | DONE |
| P2-021 | DeclarationController mock XML -> podlaczyc PIT38XMLGenerator | Code S3 | 6 | DONE |
| P2-022 | Revolut 500 warnings -> 1 metadata notice | Code S3 | — | MERGED -> P1-012 |
| P2-023 | UserId/TransactionId Symfony Uid dependency w Domain layer | Code S3 | 6 | DONE |
| P2-024 | CurrencyConverter static -> instancyjny serwis (testowalnosc) | Code S3 | — | MERGED -> P1-028 |
| P2-025 | DoctrineUserRepository flush() w repo -> Unit of Work w handler | Code S3 | 6 | DONE |
| P2-026 | Degiro supports() — wydzielic metody z intencja (DRY) | Code S3 | 6 | DONE |
| P2-027 | buildResult() DRY violation (4 adaptery) -> ParseResultBuilder | Code S3 | 6 | DONE |
| P2-028 | Upload CSV 50MB limit -> 5-10MB + row count limit | Security S3 | 6 | DONE |
| P2-029 | MIME: usunac application/vnd.ms-excel z dozwolonych | Security S3 | 6 | DONE |
| P2-030 | User::register() error message ujawnia email (PII) | Security S3 | 6 | DONE |
| P2-031 | NBP API brak max response size | Security S3 | 7 | DONE — 1MB limit with strlen check before json_decode |
| P2-032 | CORS config (przygotowac na API endpoints) | Security S3 | 7 | DONE — nelmio_cors.yaml prepared, deny-all default, whitelist template for /api/ |
| P2-033 | SQL aggregation — TODO comment only | Perf S3 | — | DEFERRED — relevant when >5000 positions/user. Effort: ~4h (add SQL SUM/GROUP BY queries + migrate from in-memory aggregation) |
| P2-034 | StreamedResponse — TODO comment only | Perf S3 | — | DEFERRED — relevant when CSV exports exceed 10MB. Effort: ~3h (replace Response with StreamedResponse, chunked fwrite) |
| P2-035 | ReflectionProperty cache w loadOpenPositions | Perf S3 | 6 | DONE |
| P2-036 | NBP pre-warming — TODO comment only | Perf S3 | — | DEFERRED — relevant when >10k users or >250 concurrent cold-start imports. Effort: ~3h (warm() method + in-memory cache in NBPApiClient, caller integration in FIFOMatchingService) |
| P2-037 | CsvSanitizer ltrim strips leading dash in negative numbers | QA S3 | 6 | DONE |
| P2-038 | PIT38XMLGenerator double escaping risk (htmlspecialchars + DOMDocument) | QA S3 | 6 | DONE |
| P2-039 | Test: equity loss scenario (P_24=0, P_25=loss) | QA S3 | 6 | DONE |
| P2-040 | Test: applyPriorYearLosses deduction > gain (waste detection) | QA S3 | 6 | DONE |
| P2-041 | Test: double finalize() -> LogicException | QA S3 | 6 | DONE |
| P2-042 | Test: addClosedPositions([]) empty array noop | QA S3 | 6 | DONE |
| P2-043 | IBKR parseDateTime milisecond format | QA S3 | 6 | DONE |
| P2-044 | Reconciliation: import result vs DB state verification | Tech-lead S5 | 7 | DONE — ImportToLedgerService logs processing counts via LoggerInterface, countByUserAndYear() added to ClosedPositionQueryPort |
| P2-045 | Golden dataset expansion: edge cases from real broker CSVs | Tech-lead S5 | — | TODO |
| P2-046 | .env.test ENCRYPTION_KEY looks real — replace with obvious placeholder | Security DAMA | — | TODO |
| P2-047 | Referral code from UUID timestamp prefix is mildly predictable | Security DAMA | — | TODO |
| P2-048 | ClosedPositionMother DRY: withGain/withLoss share 14-field call | Code DAMA | — | TODO |
| P2-049 | NormalizedTransactionMother: add WHT, FEE, CORPORATE_ACTION types | QA DAMA | — | TODO |
| P2-050 | MoneyMother: add JPY (zero-decimal currency edge case) | QA DAMA | — | TODO |
| P2-051 | AuthenticatedWebTestCase:59 non-deterministic timestamp | QA DAMA | — | TODO |
| P2-052 | Refactor fat controllers to Single Action Controllers (__invoke) | Guild + S11 Review | — | TODO |
| P2-055 | ImportStoragePort::store() $brokerId string → BrokerId VO | S11 Review | — | TODO |
| P2-056 | PriorYearLossCrudPort::save() 4 params → SavePriorYearLoss command | S11 Review | — | TODO |
| P2-057 | AuditTotals: string fields → BigDecimal (formatting in presentation) | S11 Review | — | TODO |
| P2-058 | PriorYearLossController::store() 90 lines → extract validator | S11 Review | — | TODO |
| P2-059 | InMemoryPriorYearLossCrud: inject ClockInterface for createdAt | S11 Review | — | TODO |
| P2-060 | CI secrets: add CI-ONLY comment, unify ENCRYPTION_KEY, permissions: contents: read | S11 Security | 11 | DONE |
| P2-061 | PIT-38 XML Schema Validation gate w CI (XSD e-Deklaracji MF) | TEST_METRICS P0 | 12 | DONE — pit38_minimal.xsd + AssertsPIT38XmlValid trait w GoldenDataset001/003/005 |
| P2-062 | Approval/Snapshot testing na PIT-38 XML output | TEST_METRICS | 12 | DONE — GoldenXMLSnapshotTest + tests/Fixtures/golden/pit38_tomasz_2025.xml |
| P2-063 | CSV Fuzzing — random/malformed input na parsery | TEST_METRICS | 12 | DONE — CsvFuzzingTest: 5 adapterow x 14 mutacji = 70 testow |
| P2-064 | Disclaimer regression test — weryfikacja obecnosci na kluczowych stronach | TEST_METRICS | 12 | DONE — DisclaimerRegressionTest: 8 testow (dashboard, declaration, losses, landing) |
| P2-065 | Auth boundary regression — systematyczne 401/403 per route | TEST_METRICS | 12 | DONE — AuthEnforcementTest uzupelniony o POST /losses/{id}/delete |
| P2-066 | PII Leak Detection — NIP/email nie w response body/logach | TEST_METRICS | 12 | DONE — PiiLeakDetectionTest: 9 testow @group security |
| P2-067 | Golden datasets: brakujace 9 scenariuszy (zero-gain, multi-broker, PIT/ZG, strata+zysk, multi-currency) | TEST_METRICS | 12 | DONE — GoldenDataset009 (zero-gain), GoldenDataset010 (cross-broker FIFO IBKR→Degiro), GoldenDataset011 (loss+gain compensation 1500 PLN net) |
| P2-068 | Property tests: FIFO properties x5 + Money x3 + TaxCalc x4 (target 12+) | TEST_METRICS | 12 | DONE — FifoPropertiesTest (5), MoneyPropertiesTest (3), TaxCalcPropertiesTest (4) = 12 properties, 854 test iterations |
| P2-069 | Chaos tests: DB/Redis/NBP/filesystem/Stripe (5 testow) | TEST_METRICS | 12 | DONE — PaymentGatewayFailureTest (2 tests), DividendProcessorFailureTest (1 test), NBPApiChaosTest +2 edge cases (429 retry exhaustion, missing rates key) |
| P2-070 | Load tests: spike + soak + concurrent CSV import (k6) | TEST_METRICS | 13 | TODO |
| P2-071 | DAST (OWASP ZAP) w nightly CI | TEST_METRICS | 15 | TODO |
| P2-072 | Drift Detection: ADR vs kod (skrypt w CI) | TEST_METRICS | 15 | TODO |
| P2-073 | Prompt + impl: Legal Review agent (#5) | AUDIT_PIPELINE | 12 | DONE — docs/agents/legal-review-agent-prompt.md (DRAFT, awaiting prompt expert review) |
| ~~P2-074~~ | Prompt + impl: Tax Advisor Review agent (#6) | AUDIT_PIPELINE | 12 | DONE — docs/agents/tax-advisor-review-agent-prompt.md: 8-step procedure, 5 pre-seeded findings (TAX-C01..TAX-V04) |
| ~~P2-075~~ | Snapshot Testing: generacja golden XML snapshots (#15) | AUDIT_PIPELINE | 12 | DONE — PIT38XmlSnapshotTest: equity-only-gain, full-pit38, equity-loss (18 golden-dataset tests total) |
| ~~P2-076~~ | Stworzyc docs/REGULATORY_MAP.md (artykul → klasa → test) | AUDIT_PIPELINE | 12 | DONE — docs/REGULATORY_MAP.md: art. 30b, 17 ust. 1d, 9 ust. 3, 63 §1 OP, UPO, art. 45 |
| ~~P2-077~~ | Prompt + impl: Audit Trail Audit agent (#14) | AUDIT_PIPELINE | 13 | DONE — docs/agents/audit-trail-review-agent-prompt.md: AT-001..AT-006, P0-010 dodany |
| ~~P2-078~~ | Simulated Pentest: generacja PHPUnit security suite (#12) | AUDIT_PIPELINE | 13 | DONE — 10 findings (2×P1, 8×P2), P1 impl in progress |
| ~~P2-109~~ | Security: IDOR — brak testu że user A nie może usunąć straty user B (PriorYearLoss) | Security Pentest S13 | 13 | DONE — PriorYearLossIdorTest |
| ~~P2-110~~ | Security: FileUploadSecurityTest — HTTP-level test dla .php/.htaccess/null-byte w nazwie pliku | Security Pentest S13 | 13 | DONE — FileUploadSecurityTest (3 testy) |
| ~~P2-111~~ | Security: WebhookSecurityTest — brak podpisu Stripe → 400 (brak testu) | Security Pentest S13 | 13 | DONE — WebhookSecurityTest (brak nagłówka + garbage signature) |
| P2-112 | Security: rate limiting trigger test (limit=1 override → 2 requesty → flash error) | Security Pentest S13 | 14 | TODO |
| ~~P2-105~~ | AT-001: ClosedPositionImmutabilityListener nie chroni przed raw DBAL delete — dodać FK constraint lub guard | Audit Trail S13 | 13 | DONE — FK ON DELETE RESTRICT na 4 tabelach (closed_positions, imported_transactions, prior_year_losses, dividend_tax_results) |
| P2-106 | AT-002: Brak tabeli dla snapshot finalizowanych kalkulacji — brak traceability XML↔liczby | Audit Trail S13 | 14 | TODO |
| P2-107 | AT-005: Brak persistent audit log (żadna tabela audit_log/event_store w 10 migracjach) | Audit Trail S13 | 14 | TODO |
| P2-108 | AT-006: Brak FK constraints na user_id w imported_transactions, prior_year_losses, closed_positions, dividend_tax_results | Audit Trail S13 | 14 | TODO |
| ~~P2-078~~ | Simulated Pentest: generacja PHPUnit security suite (#12) | AUDIT_PIPELINE | 13 | DONE — 10 findings, P1 naprawione: SecurityHeadersIntegrationTest, MagicLinkSecurityTest, BillingController CSRF |
| ~~P2-079~~ | Fuzzing: generacja PHPUnit fuzz suite dla CSV parserów (#13) | AUDIT_PIPELINE | 13 | DONE — 24 testy fuzz. P0 FIX: CurrencyCode::from→::tryFrom w IBKR+Bossa+Revolut |
| P2-080 | Prompt + impl: GDPR Audit agent (#7) | AUDIT_PIPELINE | 14 | TODO |
| P2-081 | Prompt + impl: Adversarial Review agent (#11) | AUDIT_PIPELINE | 14 | TODO |
| P2-082 | Prompt + impl: Compliance Audit agent (#9) | AUDIT_PIPELINE | 14 | TODO |
| P2-083 | Prompt + impl: Architecture Audit agent (#10, incl. Drift) | AUDIT_PIPELINE | 15 | TODO |
| P2-084 | Prompt + impl: UX Review agent (#8) | AUDIT_PIPELINE | 15 | TODO |
| P2-085 | Prompt + impl: User Story Replay agent (#16) | AUDIT_PIPELINE | 16 | TODO |
| ~~P2-086~~ | Content: prompt expert review content-writer-agent-prompt.draft.md | CONTENT_STANDARDS | 12 | DONE — docs/agents/content-writer-agent-prompt.md: Q0-Q3 scale, CW-001..CW-006, hallucination guardrails |
| ~~P2-087~~ | Content: weryfikacja placeholderów <!-- Screenshot --> we wszystkich artykułach | CONTENT_STANDARDS | 12 | DONE — 6 placeholderów niezrealizowanych (4 artykuły), /public/images/blog/ nie istnieje — patrz docs/content-research-notes.md |
| P2-088 | Content: artykuł XTB PIT-38 2027 (pełny pipeline: brief → research → draft → review) | CONTENT_STANDARDS | 13 | TODO |
| P2-089 | Content: artykuł eToro PIT-38 2027 | CONTENT_STANDARDS | 13 | TODO |
| P2-090 | Content: artykuł Trading 212 PIT-38 2027 | CONTENT_STANDARDS | 14 | TODO |
| P2-091 | Content: artykuł mBank eMakler PIT-38 2027 | CONTENT_STANDARDS | 14 | TODO |
| ~~P2-092~~ | Content: research — czy XTB stosuje kurs NBP z dnia czy poprzedzającego? | CONTENT_STANDARDS | 12 | DONE — XTB wydaje PIT-8C (PLN gotowe), brak XtbAdapter w kodzie — patrz docs/content-research-notes.md |
| P2-093 | Content: ETF irlandzkie (VWCE) i podwójny WHT — dedykowany artykuł lub sekcja | CONTENT_STANDARDS | 14 | TODO |
| P2-094 | E2E: DashboardImportFlowTest — zastąpić szerokie str_contains('0') konkretnym selektorem empty-state | S11 Code Review | 12 | DONE — assertSelectorTextContains('h2', 'Brak danych') |
| P2-095 | E2E: PublicPagesNavigationFlowTest — reset klienta między iteracjami pętli | S11 Code Review | 12 | DONE — @dataProvider, każda trasa ma własny izolowany klient |
| P2-096 | CI: rozważyć sekwencyjność Stage 3 po Stage 2 lub udokumentować intencję równoległości | S11 Code Review | 12 | DONE — dodano komentarz w ci.yml wyjaśniający intencję równoległości |
| P2-097 | Contract: NBPApiConsumerTest withOptions — zweryfikować czy NBPApiClient wywołuje withOptions() | S11 Code Review | 12 | N/A — NBPApiClient nie wywołuje withOptions(); stub no-op poprawny (satisfies HttpClientInterface) |
| ~~P2-098~~ | CI: dodać grep CI check dla @return array{ w Application/Domain (automated coding standard check) | S11 Code Review | 13 | DONE — Stage 1 hard-gate, zero violations baseline |
| ~~P2-099~~ | Chaos: brakujacy test failure mode dividend processor (DividendProcessorPort throws) | QA S11 | 12 | DONE — DividendProcessorFailureTest (Sprint 12) |
| ~~P2-100~~ | E2E: brakuje magic link verify positive flow (klik w link → zalogowany) | QA S11 | 12 | DONE — MagicLinkVerifyFlowTest: valid/expired/invalid token paths |
| ~~P2-101~~ | E2E: brakuje declaration export happy path (pobierz XML) | QA S11 | 13 | DONE — DeclarationExportFlowTest: auth/404/no-data/happy-path (4 testy). BUG FIX: users.nip VARCHAR(10→255) |
| P2-103 | CONTENT BUG: artykuł kalkulator-podatku-gieldowego-porownanie.md twierdzi że XTB CSV jest wspierany — brak XtbAdapter w kodzie; usunąć lub dopisać XtbAdapter | Content Research | 12 | BLOCKED — user doda XtbAdapter, bez niego nie ruszamy |
| P2-104 | CONTENT: 6 placeholderów screenshot niezrealizowanych — dodać screenshoty lub usunąć placeholdery (4 artykuły) | Content Research | 13 | TODO |
| ~~P2-102~~ | phpunit.xml.dist: dodac group exclusion dla e2e (analogicznie do canary/chaos) | QA S11 | 12 | DONE |
| P2-053 | DeclarationService::resolveUserProfile → UserProfile DTO | Guild | 11 | DONE |
| P2-054 | AuditReportDataBuilder::calculateTotals → AuditTotals DTO | Guild | 11 | DONE |

## P3 — Nice to Have

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P3-001 | Canary test: live NBP API format check (CI nightly) | ADR-019 | — | TODO |
| P3-002 | Community reporting: "format nie dziala" button | ADR-019 | — | TODO |
| P3-003 | Open-source adapter SDK | ADR-019 | — | TODO |
| P3-009 | Blog: artykul rozliczenie XTB PIT-38 | SEO Audit | — | TODO |
| P3-010 | Blog: artykul rozliczenie eToro PIT-38 | SEO Audit | — | TODO |
| P3-011 | Blog: artykul rozliczenie Trading 212 PIT-38 | SEO Audit | — | TODO |
| P3-012 | Blog: artykul rozliczenie mBank eMakler PIT-38 | SEO Audit | — | TODO |
| P3-013 | SEO: breadcrumb schema na stronach blog | SEO Audit | — | TODO |
| P3-014 | SEO: HowTo schema na landing "Jak to dziala" | SEO Audit | — | TODO |
| P3-015 | SEO: internal linking miedzy artykulami blogu | SEO Audit | — | TODO |
| P3-016 | i18n: wsparcie jezyka UA/EN dla imigrantow (ukrainski, bialoruski, angielski) — rozważyć na sezon PIT 2027 | Future | — | TODO |
| P3-004 | Test very large amounts (10M PLN) | QA S1+2 | 6 | DONE |
| P3-005 | Test very small amounts (0.01 PLN) | QA S1+2 | 6 | DONE |
| P3-006 | CSV with only headers, no data | QA S1+2 | 6 | DONE |
| P3-007 | DividendCountrySummary::add() error test | QA S1+2 | 6 | DONE |
| P3-008 | Integration test: AdapterRegistry + real adapters | QA S1+2 | 7 | DONE — AdapterRegistryIntegrationTest: detect+parse for all 5 fixtures, broker coverage assertion |

## BLOCKED — External Dependencies

| ID | Opis | Blocked by | Status |
|---|---|---|---|
| BLK-001 | XTB adapter | Real CSV od PO | WAITING |
| BLK-002 | mBank eMakler adapter | Real CSV od PO | WAITING |
| BLK-003 | Opinia prawna: narzedzie vs doradztwo | Kancelaria FinTech | WAITING |
| BLK-004 | DPIA (GDPR Art. 35) | Audytor zewnetrzny | WAITING |

---

## DONE

| ID | Opis | Sprint | Done |
|---|---|---|---|
| ~~P0-004~~ | Golden dataset test #1 (Tomasz) | 3 | 2026-04-02 |
| ~~P0-005~~ | CalculateAnnualTaxHandler test | 3 | 2026-04-02 |
| ~~P0-006~~ | Zero quantity guard | 3 | 2026-04-02 |
| ~~P1-001~~ | Symfony kernel bootstrap | 3 | 2026-04-02 |
| ~~P1-002~~ | Doctrine setup + XML mappings | 3 | 2026-04-02 |
| ~~P1-004~~ | Dashboard UI | 3 | 2026-04-02 |
| ~~P1-005~~ | PIT-38 preview w UI | 3 | 2026-04-02 |
| ~~P1-006~~ | Cross-year FIFO test (Golden #2) | 3 | 2026-04-02 |
| ~~P1-007~~ | Fractional shares test | 3 | 2026-04-02 |
| ~~P1-008~~ | XXE defense | 3 | 2026-04-02 |
| ~~P1-009~~ | CsvSanitizer trait DRY | 3 | 2026-04-02 |
| ~~B-01~~ | Fix commission allocation | Pre-sprint | 2026-04-02 |
| ~~B-02~~ | Money.toPLN currency guard | Pre-sprint | 2026-04-02 |
| ~~B-03~~ | ADR-017 Multi-Year FIFO | Pre-sprint | 2026-04-02 |
| ~~B-04~~ | Fix zaokraglanie art. 63 par.1 | Pre-sprint | 2026-04-02 |
| ~~B-05~~ | closedPositions append-only | Pre-sprint | 2026-04-02 |
| ~~B-11~~ | Money::of() nie zaokragla | Pre-sprint | 2026-04-02 |
| ~~ADR-012~~ | PII Encryption | Pre-sprint | 2026-04-02 |
| ~~ADR-013~~ | Data Retention & GDPR | Pre-sprint | 2026-04-02 |
| ~~ADR-014~~ | Secrets Management | Pre-sprint | 2026-04-02 |
| ~~ADR-015~~ | Authentication Security | Pre-sprint | 2026-04-02 |
| ~~ADR-016~~ | Timezone Handling | Pre-sprint | 2026-04-02 |
| ~~ADR-017~~ | Multi-Year FIFO | Pre-sprint | 2026-04-02 |
| ~~ADR-018~~ | CSV Upload Security | Pre-sprint | 2026-04-02 |
| ~~ADR-019~~ | Broker Adapter Versioning | Pre-sprint | 2026-04-02 |
| ~~P1-003~~ | Auth magic link login | 5 | 2026-04-02 |
| ~~P1-022~~ | ImportController WebTestCase | 5 | 2026-04-02 |
| ~~P2-018~~ | CSV sanitize: \n added to strip set | 5 | 2026-04-02 |
| ~~P2-019~~ | Rate limiting on upload endpoint (10/10min per IP) | 5 | 2026-04-02 |
| ~~P1-016~~ | Stripe billing integration | 6 | 2026-04-02 |
| ~~P1-020~~ | Degiro supports() false positive — negative tests added | 6 | 2026-04-02 |
| ~~P1-021~~ | equityLossDeduction clamping in AnnualTaxCalculationService | 6 | 2026-04-02 |
| ~~P1-024~~ | ClosedPosition gainLoss invariant — reconciliation check in finalize | 6 | 2026-04-02 |
| ~~P2-003~~ | AdapterRegistry priority() method added | 6 | 2026-04-02 |
| ~~P2-005~~ | UPORegistry config — upo_rates.yaml | 6 | 2026-04-02 |
| ~~P2-006~~ | PriorYearLoss renamed to PriorYearLossEntry | 6 | 2026-04-02 |
| ~~P2-008~~ | PolishTimezone singleton for Europe/Warsaw | 6 | 2026-04-02 |
| ~~P2-009~~ | FIFOPropertyTest.php — property-based tests | 6 | 2026-04-02 |
| ~~P2-011~~ | LossDeductionRange validation guards + tests | 6 | 2026-04-02 |
| ~~P2-012~~ | TaxYear validation (2000-2100 range) | 6 | 2026-04-02 |
| ~~P2-013~~ | Double finalize test (testDoubleFinalizationThrowsLogicException) | 6 | 2026-04-02 |
| ~~P2-016~~ | MIME .csv extension pathinfo check | 6 | 2026-04-02 |
| ~~P2-017~~ | PII in exceptions — generic message | 6 | 2026-04-02 |
| ~~P2-020~~ | Dashboard mock removal — zeroed demo mode | 6 | 2026-04-02 |
| ~~P2-021~~ | Declaration mock XML removal — wired PIT38XMLGenerator | 6 | 2026-04-02 |
| ~~P2-023~~ | UserId/TransactionId ADR docblock added | 6 | 2026-04-02 |
| ~~P2-025~~ | flush() moved to Application handlers | 6 | 2026-04-02 |
| ~~P2-026~~ | Degiro intent methods (hasEnglishHeaders etc.) | 6 | 2026-04-02 |
| ~~P2-027~~ | ParseResultBuilder trait extracted | 6 | 2026-04-02 |
| ~~P2-028~~ | CSV file size limit 50->10MB | 6 | 2026-04-02 |
| ~~P2-029~~ | Removed vnd.ms-excel from allowed MIME types | 6 | 2026-04-02 |
| ~~P2-030~~ | PII in User::register — generic message | 6 | 2026-04-02 |
| ~~P2-033~~ | SQL aggregation TODO comment added | 6 | 2026-04-02 |
| ~~P2-034~~ | StreamedResponse TODO comment added | 6 | 2026-04-02 |
| ~~P2-035~~ | ReflectionProperty replaced by reconstitute | 6 | 2026-04-02 |
| ~~P2-036~~ | NBP pre-warming TODO comment added | 6 | 2026-04-02 |
| ~~P2-037~~ | CsvSanitizer dash+digit preserved for negative numbers | 6 | 2026-04-02 |
| ~~P2-038~~ | Double escaping verified — no issue | 6 | 2026-04-02 |
| ~~P2-039~~ | Equity loss test in PIT38XMLGeneratorTest | 6 | 2026-04-02 |
| ~~P2-040~~ | Loss deduction > gain — clamping + tests | 6 | 2026-04-02 |
| ~~P2-041~~ | Double finalize test | 6 | 2026-04-02 |
| ~~P2-042~~ | Empty addClosedPositions test | 6 | 2026-04-02 |
| ~~P2-043~~ | IBKR millisecond format handled | 6 | 2026-04-02 |
| ~~P3-004~~ | ExtremeAmountsTest — 10M PLN | 6 | 2026-04-02 |
| ~~P3-005~~ | ExtremeAmountsTest — 0.01 PLN | 6 | 2026-04-02 |
| ~~P3-006~~ | HeadersOnlyCsvTest | 6 | 2026-04-02 |
| ~~P3-007~~ | DividendCountrySummaryTest — different countries | 6 | 2026-04-02 |
| ~~P1-023~~ | UTF-8 BOM handling — all adapters use CsvSanitizer::stripBom() | 7 | 2026-04-02 |
| ~~P1-025~~ | Adapter test alignment — all 5 adapters at 19 tests | 7 | 2026-04-02 |
| ~~P1-026~~ | Audit trail tamper-proof — ClosedPositionImmutabilityListener | 7 | 2026-04-02 |
| ~~P2-031~~ | NBP API 1MB response size limit | 7 | 2026-04-02 |
| ~~P2-032~~ | CORS config — deny-all default prepared | 7 | 2026-04-02 |
| ~~P2-044~~ | Reconciliation logging in ImportToLedgerService | 7 | 2026-04-02 |
| ~~P3-008~~ | AdapterRegistryIntegrationTest — detect+parse all fixtures | 7 | 2026-04-02 |
| ~~BUG~~ | DegiroTransactionsAdapter.supports() false-positive on Account Statement CSV — added Quantity/Exchange rate check | 7 | 2026-04-02 |
