# Plan Implementacji: TaxPilot.pl

## Metadata

| | |
|---|---|
| **Data** | 2026-04-03 (dzień po Event Storming) |
| **Wejście** | EVENT_STORMING.md — 118 zdarzeń, 31 hotspotów, 8 BC, 25 user stories |
| **Decyzja** | CONDITIONAL GO — warunki brzegowe muszą być spełnione przed development |
| **Zespół** | Fully Featured AI Agent Team |
| **Moderacja** | Mariusz Gil (architektura), Marek [senior-dev] (tech lead) |

---

## Część 1: Meeting Implementacyjny — "Jak to zbudować?"

### Otwarcie — Mariusz Gil

> "Mamy wynik Event Stormingu. Wiemy CO budujemy. Dzisiaj ustalamy JAK. Trzy pytania:
> 1. W jakiej kolejności?
> 2. Jaki stack i architektura?
> 3. Kto co robi i kiedy mamy MVP?
>
> Ale najpierw — warunki brzegowe. Zanim napiszemy linię kodu, muszą być spełnione P0 z Action Items."

---

## Część 2: Pre-Development Gate (Tydzień 0-2)

### Warunki brzegowe — status i plan realizacji

| # | Warunek | Owner | Plan | Target |
|---|---|---|---|---|
| G-001 | Opinia prawna: narzędzie vs. doradztwo | Mec. Wiśniewska | Zlecenie kancelarii specjalizującej się w FinTech. Konkretne pytania: (1) czy automatyczne obliczenie FIFO to doradztwo? (2) czy pokazanie opcji odpisu straty bez rekomendacji to doradztwo? (3) jakie disclaimery są wymagane? | 2026-04-15 |
| G-002 | Walidacja rynku: landing page + 1000 signups | Paweł + Zofia | Landing page na Vercel/Next.js. Copy: "Rozlicz PIT-38 z zagranicznych brokerów w 15 minut". Formularz: email + z jakiego brokera korzystasz + ile transakcji rocznie. Kampania: Reddit r/polskagielda, Facebook grupy inwestorów, Twitter/X #PIT38. Budget: 2000 PLN na ads. | 2026-04-20 |
| G-003 | Golden dataset: 20 zestawów testowych | Tomasz [DP] | Tomasz anonimizuje 20 realnych rozliczeń (różne scenariusze: proste akcje, multi-broker FIFO, dywidendy z 5 krajów, straty z lat poprzednich, spin-off). Format: CSV transakcji + oczekiwany PIT-38 + komentarz edge case. | 2026-04-20 |
| G-004 | Ubezpieczenie OC | Łukasz [risk] | Zapytania do 3 ubezpieczycieli (Warta, PZU, Allianz) o OC dla narzędzia kalkulacyjnego FinTech. Szacunek: 5-15k PLN/rok przy limicie 500k PLN. | 2026-04-15 |
| G-005 | Rejestracja spółki + regulamin | Łukasz + Mec. Wiśniewska | Sp. z o.o. Regulamin z klauzulą: "Aplikacja jest narzędziem kalkulacyjnym. Nie stanowi doradztwa podatkowego. Użytkownik ponosi odpowiedzialność za poprawność danych." | 2026-04-20 |

**Marek [senior-dev]:**
> "Czekając na gate'y, nie siedzimy bezczynnie. Tydzień 0-2 to PoC-e i infrastruktura bazowa. Ale NIE commitujemy się do feature'ów."

---

## Część 3: Strategia Implementacji

### Podejście: Vertical Slice + Walking Skeleton

**Mariusz Gil:**

> "Nie budujemy warstwa po warstwie (database → backend → frontend). Budujemy PIONOWO — cienki slice przez wszystkie warstwy. Pierwszy slice: 'User wgrywa CSV z IBKR, system oblicza FIFO dla jednej akcji, wyświetla wynik'. Od CSV do wyniku na ekranie. Cienki, ale END-TO-END."

**Marek [senior-dev]:**

> "Walking Skeleton — działający system z minimum logiki. Deploy na produkcję od dnia 1. CI/CD od dnia 1. Monitoring od dnia 1. Potem dobudowujemy mięśnie na szkielet."

### Fazy implementacji

```
          Tydzień 0-2          Tydzień 3-6           Tydzień 7-10         Tydzień 11-14
        ┌─────────────┐    ┌──────────────────┐    ┌─────────────────┐    ┌──────────────┐
        │  PRE-DEV    │    │  WALKING         │    │  CORE           │    │  HARDENING   │
        │  GATE       │    │  SKELETON        │    │  FEATURES       │    │  + LAUNCH    │
        │             │    │                  │    │                 │    │              │
        │ • Opinia    │    │ • 1 broker CSV   │    │ • 3 brokerzy    │    │ • Pentest    │
        │   prawna    │    │ • FIFO basic     │    │ • Dywidendy+UPO │    │ • Load test  │
        │ • Landing   │    │ • NBP basic      │    │ • Straty lat    │    │ • Beta users │
        │   page      │    │ • PIT-38 preview │    │ • PIT-38 XML    │    │ • Fixes      │
        │ • Golden    │    │ • Basic UI       │    │ • PIT/ZG        │    │ • Docs       │
        │   dataset   │    │ • Auth           │    │ • Audit trail   │    │ • Launch!    │
        │ • PoC-e     │    │ • CI/CD          │    │ • Billing       │    │              │
        └─────────────┘    └──────────────────┘    └─────────────────┘    └──────────────┘
              ▲                     ▲                       ▲                     ▲
           GO/NO-GO            Tomasz review           Tomasz review        Public Beta
           decision            golden dataset          full scenarios       (luty 2027)
```

**Timeline target: Public Beta = luty 2027** (początek sezonu PIT-38 za rok 2026)

---

## Część 4: Architektura Techniczna

### Decyzje architektoniczne — ADR

#### ADR-001: Monorepo + Modular Monolith (nie microservices)

**Marek [senior-dev]:**

> "Wiem że ES dał nam 8 Bounded Contexts. ALE — nie zaczynamy od microservices. Zaczynamy od modular monolith. Moduły = bounded contexts, ale w jednym deploymencie. Dlaczego? Bo mamy 3-4 devów, nie 30. Microservices dla małego teamu = distributed monolith."

**Mariusz Gil:**

> "Zgadzam się. Modular monolith z czystymi granicami między modułami. Komunikacja przez eventy IN-PROCESS. Jak urośniecie — wyciągacie moduł do osobnego serwisu. Ale nie wcześniej niż będzie ku temu powód."

**Decyzja:**
- Monorepo
- Modular monolith
- Każdy Bounded Context = osobny moduł z publicznym API (interface)
- Komunikacja: in-process event bus (gotowy na migrację do async)
- Baza: shared PostgreSQL, osobne schematy per moduł

#### ADR-002: Stack technologiczny — Symfony Ecosystem

**Dyskusja zespołu:**

> **Marek:** "PHP 8.4 + Symfony 7.2. Mature ecosystem, Messenger jako command/query bus, Doctrine ORM. Domain layer czysty — zero zależności od frameworka."
>
> **Aleksandra [perf]:** "PHP 8.4 z JIT da radę z FIFO. brick/math BigDecimal dla precyzji finansowej. 10k transakcji FIFO < 2 sekundy."
>
> **Paweł [front]:** "Twig + Hotwire (Turbo + Stimulus). Nie SPA — to apka formularzowa. SSR, SEO free, mniej complexity."
>
> **Bartek [devops]:** "Docker Compose local = prod parity. AWS ECS Fargate prod. Terraform. GitHub Actions."
>
> **Michał P. [security]:** "Symfony Security z custom magic link authenticator. NIE hasła — mniej attack surface."

**Stack finalny:**

| Warstwa | Technologia | Uzasadnienie |
|---|---|---|
| **Język** | PHP 8.4+ | Strict types, readonly classes, enums, fibers |
| **Framework** | Symfony 7.2+ | HTTP, Messenger, Security, Validator, Serializer |
| **Frontend** | Twig + Turbo + Stimulus (Hotwire) | Server-rendered, interactive fragments, nie SPA |
| **Styling** | Tailwind CSS 4.x | Utility-first, szybki development |
| **Command/Query Bus** | Symfony Messenger | CQRS — command bus + query bus, async capable |
| **Domain Math** | brick/math BigDecimal | Precyzja finansowa, NIGDY float |
| **ORM** | Doctrine ORM 3.x | Persistence, migrations. Domain NIE zależy od Doctrine. |
| **Database** | PostgreSQL 17 | ACID, JSONB, partial indexes, encryption |
| **Cache** | Redis + Symfony Cache | Kursy NBP, sesje, pre-computed results |
| **Queue** | Symfony Messenger + Redis transport | Async: import CSV, pre-compute |
| **Auth** | Symfony Security + custom magic link | Email login, session-based |
| **Payments** | Stripe PHP SDK | Subscriptions, webhooks, obsługuje PLN |
| **File Storage** | Flysystem + AWS S3 (encrypted) | CSV uploads, generated PDFs |
| **Testing** | PHPUnit + Infection (mutation testing) | TDD, golden dataset, property-based |
| **Static Analysis** | PHPStan (level max) + PHP-CS-Fixer | Type safety, coding standards |
| **Infra** | Docker Compose (dev) + AWS ECS Fargate (prod) | Managed, auto-scaling |
| **IaC** | Terraform | Reproducible infrastructure |
| **CI/CD** | GitHub Actions | Lint → PHPStan → Tests → Build → Deploy |
| **Monitoring** | Grafana Cloud (Loki + Prometheus + Tempo) | Logs, metrics, traces |
| **Error Tracking** | Sentry | Real-time error reporting |

> **Pełna dokumentacja architektury: [ARCHITECTURE.md](ARCHITECTURE.md)**
> Clean Architecture, CQRS, DDD, domain model w PHP, Doctrine XML mapping, testing strategy.

#### ADR-003: Domain Model — Core Design

**Mariusz Gil prowadzi sesję modelowania:**

> "Tax Calculation Engine to core domain. Tu nie będzie CRUDa. Tu będzie prawdziwy model domenowy."

```typescript
// === UBIQUITOUS LANGUAGE (types) ===

// Instrument identyfikowany przez ISIN, klasyfikowany podatkowo
type TaxCategory = 'EQUITY' | 'DERIVATIVE' | 'CRYPTO' | 'DIVIDEND';

// Transakcja znormalizowana — niezależna od formatu brokera
interface NormalizedTransaction {
  id: TransactionId;
  isin: ISIN | null;            // null dla krypto
  cryptoSymbol: string | null;  // null dla tradycyjnych
  type: 'BUY' | 'SELL' | 'DIVIDEND' | 'CORPORATE_ACTION';
  date: LocalDate;              // data transakcji
  quantity: Decimal;
  pricePerUnit: Money;          // w walucie oryginalnej
  commission: Money;
  broker: BrokerId;
  rawData: Record<string, unknown>; // oryginalny wiersz CSV
}

// Money — ZAWSZE z walutą, NIGDY float
interface Money {
  amount: Decimal;  // decimal.js, nie number!
  currency: CurrencyCode;
}

// Kurs NBP — value object
interface NBPRate {
  currency: CurrencyCode;
  rate: Decimal;           // kurs średni
  effectiveDate: LocalDate; // data publikacji tabeli
  tableNumber: string;      // np. "061/A/NBP/2025"
}

// === CORE AGGREGATE: TaxPositionLedger ===
// Per ISIN (cross-broker!), per user, per rok podatkowy
// Odpowiada za FIFO matching

interface TaxPositionLedger {
  userId: UserId;
  instrumentId: ISIN | CryptoSymbol;
  taxYear: TaxYear;
  taxCategory: TaxCategory;

  // Kolejka FIFO — pozycje otwarte (niezamknięte)
  openPositions: OpenPosition[];  // sorted by date ASC

  // Zamknięte pozycje — wynik obliczony
  closedPositions: ClosedPosition[];

  // Commands
  registerBuy(tx: NormalizedTransaction, nbpRate: NBPRate): void;
  registerSell(tx: NormalizedTransaction, nbpRate: NBPRate): FIFOMatchResult[];
  registerDividend(tx: NormalizedTransaction, nbpRate: NBPRate, whtRate: Decimal): void;
}

interface OpenPosition {
  transactionId: TransactionId;
  date: LocalDate;
  remainingQuantity: Decimal;
  pricePerUnit: Money;
  commissionPerUnit: Money;
  costInPLN: Decimal;       // przeliczone kursem NBP z dnia-1
  nbpRate: NBPRate;          // użyty kurs — audit trail
  broker: BrokerId;
}

interface ClosedPosition {
  buyTransactionId: TransactionId;
  sellTransactionId: TransactionId;
  quantity: Decimal;
  costBasisPLN: Decimal;     // koszt nabycia w PLN
  proceedsPLN: Decimal;      // przychód w PLN
  commissionsPLN: Decimal;   // prowizje (buy + sell) w PLN
  gainLossPLN: Decimal;      // zysk/strata
  buyNBPRate: NBPRate;       // audit trail
  sellNBPRate: NBPRate;      // audit trail
}

// === AGGREGATE: AnnualTaxCalculation ===
// Podsumowanie roczne per user

interface AnnualTaxCalculation {
  userId: UserId;
  taxYear: TaxYear;

  // Sekcja C PIT-38 — odpłatne zbycie papierów
  equityAndDerivatives: {
    totalProceeds: Decimal;      // przychód
    totalCostBasis: Decimal;     // koszty uzyskania
    totalCommissions: Decimal;   // prowizje
    gainLoss: Decimal;           // dochód/strata
    priorYearLossDeduction: Decimal; // odliczenie strat
    taxableIncome: Decimal;      // podstawa opodatkowania
    tax: Decimal;                // 19%
  };

  // Sekcja D PIT-38 — dywidendy zagraniczne
  foreignDividends: {
    perCountry: Map<CountryCode, {
      grossDividendPLN: Decimal;
      whtPaidPLN: Decimal;
      polishTaxDue: Decimal;    // 19% - WHT (min 0)
    }>;
    totalTaxDue: Decimal;
  };

  // Osobny koszyk — kryptowaluty
  crypto: {
    totalProceeds: Decimal;
    totalCostBasis: Decimal;
    gainLoss: Decimal;
    priorYearLossDeduction: Decimal;
    taxableIncome: Decimal;
    tax: Decimal;
  };

  // Suma
  totalTaxDue: Decimal;
}
```

**Michał W. [QA]:**

> "Ten model to jednocześnie spec dla testów. Każdy interface = test suite. Każdy edge case z golden dataset = test case."

**Mariusz Gil:**

> "Zwróćcie uwagę: `NBPRate` jest trzymany przy każdej pozycji. To jest audit trail by design, nie by afterthought. Jeśli US zapyta 'jaki kurs użyliście' — mamy odpowiedź w modelu, nie w logach."

#### ADR-004: Anti-Corruption Layer — Broker Adapters

```typescript
// Każdy broker = osobny adapter implementujący ten sam interface
// Adapter tłumaczy format brokera na NormalizedTransaction

interface BrokerAdapter {
  brokerId: BrokerId;
  supportedFormats: string[];  // np. ['ibkr-activity-v2', 'ibkr-flex-v3']

  detectFormat(file: Buffer): FormatDetectionResult;
  parse(file: Buffer, format: string): ParseResult;
}

interface ParseResult {
  transactions: NormalizedTransaction[];
  errors: ParseError[];         // wiersze których nie udało się sparsować
  warnings: ParseWarning[];     // np. "nieznany instrument, domyślnie EQUITY"
  metadata: {
    broker: string;
    format: string;
    dateRange: { from: LocalDate; to: LocalDate };
    totalRows: number;
    parsedRows: number;
    currency: CurrencyCode;
  };
}

// Adaptery — v1
// adapters/ibkr/IBKRActivityAdapter.ts
// adapters/degiro/DegiroTransactionsAdapter.ts
// adapters/xtb/XTBHistoryAdapter.ts
```

**Ania [data]:**

> "Kluczowa decyzja: adaptery są STATELESS i PURE. Biorą plik, zwracają wynik. Żadnych side effectów. Dzięki temu:
> - Łatwe do testowania (input → output)
> - Łatwe do dodawania nowych brokerów
> - Wersjonowane (IBKRActivityV2Adapter, IBKRActivityV3Adapter)
> - Community-driven w przyszłości (open source adaptery?)"

#### ADR-005: Strategia testowania

**Michał W. [QA] + Kasia [QA] prezentują:**

```
                    ┌─────────────────────────────┐
                    │  Golden Dataset Tests (E2E)  │  ← 20 zestawów od Tomasza
                    │  CSV → obliczenie → PIT-38   │  ← "prawda" doradcy podatkowego
                    └──────────────┬──────────────┘
                                   │
                    ┌──────────────┴──────────────┐
                    │  Integration Tests           │  ← moduł ↔ moduł
                    │  Import → Calc → Declaration │  ← z realną DB (testcontainers)
                    └──────────────┬──────────────┘
                                   │
           ┌───────────────────────┼───────────────────────┐
           │                       │                       │
    ┌──────┴──────┐    ┌───────────┴──────────┐    ┌──────┴──────┐
    │  Unit Tests  │    │  Property-Based Tests │    │  Adapter    │
    │  FIFO logic  │    │  "dla dowolnych N     │    │  Tests      │
    │  NBP lookup  │    │   transakcji, FIFO    │    │  IBKR CSV   │
    │  Tax calc    │    │   sumuje do 100%      │    │  Degiro CSV │
    │  Money ops   │    │   quantity"           │    │  XTB CSV    │
    └─────────────┘    └──────────────────────┘    └─────────────┘
```

> **Kasia:** "TDD od dnia zero. Red → Green → Refactor. Ale najważniejsze testy to Golden Dataset — bo to jest 'prawda' doradcy podatkowego. Jeśli nasz system daje inny wynik niż Tomasz — my mamy bug, nie Tomasz."

> **Michał W.:** "Property-based testing dla FIFO:
> - Suma zamkniętych quantity ≤ suma kupionych quantity
> - Każda sprzedaż konsumuje najstarszy zakup
> - Zysk/strata w PLN = przychód_PLN - koszt_PLN - prowizje_PLN (dokładnie, co do grosza)
> - Żadna transakcja nie jest liczona dwukrotnie"

> **Mariusz Gil:** "Dodajcie jeszcze: snapshot testy. Dla każdego zestawu z golden dataset — zapisany expected output. Jeśli zmienicie logikę i output się zmieni — test czerwony. Musicie świadomie zaakceptować zmianę. To chroni przed regresją."

#### ADR-006: Struktura projektu (monorepo)

```
taxpilot/
├── apps/
│   ├── web/                          # Next.js frontend
│   │   ├── app/
│   │   │   ├── (auth)/               # login, register
│   │   │   ├── (dashboard)/          # główny UI
│   │   │   │   ├── import/           # upload CSV
│   │   │   │   ├── transactions/     # lista transakcji
│   │   │   │   ├── calculation/      # wynik obliczeń, drill-down
│   │   │   │   ├── declaration/      # podgląd PIT-38, export
│   │   │   │   └── settings/         # profil, brokerzy, straty
│   │   │   └── (marketing)/          # landing page
│   │   └── components/
│   │
│   └── api/                          # Fastify backend
│       ├── src/
│       │   ├── modules/
│       │   │   ├── identity/         # BC: Identity & Access
│       │   │   ├── broker-import/    # BC: Broker Integration
│       │   │   │   ├── adapters/
│       │   │   │   │   ├── ibkr/
│       │   │   │   │   ├── degiro/
│       │   │   │   │   └── xtb/
│       │   │   │   ├── domain/
│       │   │   │   └── api/
│       │   │   ├── instrument/       # BC: Instrument Registry
│       │   │   ├── exchange-rate/    # BC: Exchange Rate Service
│       │   │   ├── tax-calc/         # BC: Tax Calculation Engine ⭐
│       │   │   │   ├── domain/
│       │   │   │   │   ├── model/
│       │   │   │   │   │   ├── TaxPositionLedger.ts
│       │   │   │   │   │   ├── AnnualTaxCalculation.ts
│       │   │   │   │   │   ├── FIFOMatchingPolicy.ts
│       │   │   │   │   │   ├── PriorYearLossRegister.ts
│       │   │   │   │   │   └── Money.ts (value object)
│       │   │   │   │   ├── service/
│       │   │   │   │   │   └── TaxCalculationService.ts
│       │   │   │   │   └── event/
│       │   │   │   │       └── TaxCalculated.ts
│       │   │   │   ├── infra/
│       │   │   │   │   └── PostgresTaxPositionRepository.ts
│       │   │   │   └── api/
│       │   │   │       └── TaxCalcRoutes.ts
│       │   │   ├── declaration/      # BC: Tax Declaration
│       │   │   ├── audit/            # BC: Audit & Reporting
│       │   │   └── billing/          # BC: Billing & Subscription
│       │   ├── shared/
│       │   │   ├── kernel/           # shared value objects (Money, ISIN, etc.)
│       │   │   ├── event-bus/        # in-process event bus
│       │   │   └── middleware/       # auth, logging, error handling
│       │   └── config/
│       └── test/
│           ├── unit/
│           ├── integration/
│           ├── property/
│           └── golden-dataset/       # 20 zestawów od Tomasza
│               ├── dataset-001-simple-equities/
│               ├── dataset-002-multi-broker-fifo/
│               ├── dataset-003-dividends-5-countries/
│               └── ...
│
├── packages/
│   ├── shared-types/                 # TypeScript types shared FE↔BE
│   ├── tax-rules/                    # reguły podatkowe (versioned per year)
│   │   ├── 2025/
│   │   └── 2026/
│   └── nbp-client/                   # klient API NBP
│
├── infra/
│   ├── terraform/
│   │   ├── modules/
│   │   ├── environments/
│   │   │   ├── staging/
│   │   │   └── production/
│   │   └── main.tf
│   └── docker/
│       ├── Dockerfile.api
│       ├── Dockerfile.web
│       └── docker-compose.yml
│
├── docs/
│   ├── adr/                          # Architecture Decision Records
│   ├── domain/                       # Ubiquitous Language glossary
│   └── api/                          # OpenAPI spec
│
├── Makefile                          # shortcuts: make test, make dev, etc.
├── turbo.json                        # Turborepo config
└── package.json
```

**Bartek [devops]:**

> "Turborepo dla monorepo. `make dev` startuje cały stack (docker-compose z Postgres + Redis + app). `make test` puszcza wszystkie testy. `make test-golden` puszcza tylko golden dataset. Żadnych tajemnych komend."

---

## Część 5: Sprint Plan — Faza Walking Skeleton (Tydzień 3-6)

### Sprint 1 (Tydzień 3-4): "CSV to Number"

**Cel: User wgrywa CSV z IBKR → widzi obliczony zysk/stratę dla jednej akcji w USD.**

| Story | Owner | Opis | Definition of Done |
|---|---|---|---|
| **Infra: monorepo + CI/CD** | Bartek | Turborepo, GitHub Actions (lint + test + build), Docker Compose dev | `make dev` działa, pipeline zielony |
| **Auth: magic link login** | Marek | NextAuth.js, email magic link, PostgreSQL session | User loguje się emailem |
| **Import: IBKR CSV parser** | Ania | Adapter IBKR Activity Statement → NormalizedTransaction | 10 plików testowych parsuje poprawnie |
| **Calc: FIFO basic** | Marek | TaxPositionLedger z FIFO matching, pure TypeScript | Testy jednostkowe + 3 golden datasets pass |
| **Calc: NBP rate fetch** | Ania | nbp-client package, cache w Redis | Pobiera kurs, cache działa, fallback na poprzedni dzień |
| **Calc: Money value object** | Marek | Decimal.js, immutable, Currency-aware arithmetic | Property tests: a + b - b = a, precision 2 dp |
| **UI: Upload + Results** | Paweł + Zofia | Upload CSV → progress → tabela transakcji → zysk/strata | Działa E2E w przeglądarce |
| **Security: encryption at rest** | Michał P. | Postgres TDE, S3 SSE, column-level encryption for NIP | Dane zaszyfrowane, key w AWS KMS |

**Kasia [QA] na koniec sprintu:**
> "Sprint review: biorę plik Tomasza, wgrywam, i patrzę czy wynik się zgadza. Jeśli tak — green. Jeśli nie — sprint nieudany."

### Sprint 2 (Tydzień 5-6): "Multi-Broker + Dividends"

**Cel: 3 brokerzy, dywidendy zagraniczne, PIT-38 preview.**

| Story | Owner | Opis | Definition of Done |
|---|---|---|---|
| **Import: Degiro adapter** | Ania | Parser Degiro Transactions CSV | Testy + golden dataset Degiro |
| **Import: XTB adapter** | Ania | Parser XTB History CSV | Testy + golden dataset XTB |
| **Calc: Cross-broker FIFO** | Marek | TaxPositionLedger per ISIN (nie per broker) | Golden dataset multi-broker pass |
| **Calc: Dividend tax** | Marek | DividendTaxCalculator, UPO rates table | Dywidenda USA/UK/DE obliczona poprawnie |
| **Calc: PIT/ZG per country** | Marek | Generowanie danych per kraj | Dane gotowe dla 5 krajów |
| **UI: PIT-38 preview** | Paweł + Zofia | Formularz PIT-38 z wypełnionymi polami, drill-down | User widzi PIT-38 z danymi |
| **UI: Transaction list** | Zofia | Filtrowanie, sortowanie, broker badge | Responsive, accessible |
| **Audit: basic trail** | Marek | Każda kalkulacja zapisana z kursami NBP | Drill-down do pojedynczej transakcji |

---

## Część 6: Faza Core Features (Tydzień 7-10)

### Sprint 3 (Tydzień 7-8): "Declaration Ready"

| Story | Owner |
|---|---|
| PIT-38 XML export (e-Deklaracje schema) | Ania |
| PIT/ZG XML per kraj | Ania |
| PDF export (audit trail report) | Paweł |
| Prior year loss — input + calculation | Marek |
| PIT-8C upload + comparison | Ania |
| Error handling UX — co gdy brakuje danych | Zofia |
| Duplicate detection na import | Marek |
| Rate limiting + abuse prevention | Michał P. |

### Sprint 4 (Tydzień 9-10): "Billing + Polish"

| Story | Owner |
|---|---|
| Stripe integration (3 plany) | Paweł |
| Paywall: obliczenie free, export paid | Paweł |
| Onboarding wizard | Zofia |
| Dashboard — roczne podsumowanie | Zofia |
| Manual transaction add/edit | Paweł |
| Email notifications (reminder 30.04) | Ania |
| GDPR: data export + deletion | Michał P. |
| Performance optimization (pre-compute) | Aleksandra |

---

## Część 7: Faza Hardening (Tydzień 11-14)

### Sprint 5 (Tydzień 11-12): "Security + Load"

| Zadanie | Owner | Opis |
|---|---|---|
| Penetration testing | Michał P. | Zewnętrzna firma pentest lub crowd-security |
| Load testing | Aleksandra | k6: 5000 concurrent users, 50k transakcji |
| OWASP top 10 audit | Michał P. | Checklist OWASP, automated scanning |
| Disaster recovery test | Sylwester | Restore z backup, RTO < 4h |
| Monitoring dashboards | Sylwester | Grafana: obliczenia/s, error rate, p95 latency |
| Alerting rules | Sylwester | PagerDuty: error spike, API NBP down, DB connection |

### Sprint 6 (Tydzień 13-14): "Beta + Launch"

| Zadanie | Owner | Opis |
|---|---|---|
| Beta invites (100 z waitlist) | Łukasz | Osoby z waitlist, zróżnicowane profile |
| Beta feedback loop | Kasia + Zofia | In-app feedback widget, weekly calls |
| Bug fixing from beta | Marek + Paweł | P0 immediate, P1 w sprincie |
| Tomasz validation | Tomasz [DP] | Doradca podatkowy weryfikuje 10 obliczeń beta |
| Legal review final | Mec. Wiśniewska | Regulamin, disclaimer, cookie policy |
| Launch checklist | Bartek | DNS, SSL, monitoring, runbook |
| **PUBLIC BETA LAUNCH** | ALL | **Luty 2027** |

---

## Część 8: Team Allocation

### Kto co robi — swim lanes

```
Tydzień:  0-2          3-4           5-6           7-8          9-10        11-14
          PRE-DEV      SPRINT 1      SPRINT 2      SPRINT 3     SPRINT 4    HARDENING

Marek     PoC FIFO     FIFO+Money    Cross-FIFO    Losses       Dedup       Bug fixes
          [senior-dev] Auth          Dividends     PIT-8C cmp              

Ania      PoC NBP      IBKR parser   Degiro+XTB    PIT-38 XML   Emails      Support
          [data]       NBP client    parser        PIT/ZG                  

Paweł     Landing      Upload UI     PIT-38 prev   PDF export   Stripe      Bug fixes
          [front]      page          TX list       Audit UI     Billing    

Zofia     Landing      Results UI    TX filters    Error UX     Onboard     Beta UX
          [front]      UX            Dashboard     Wizard       Dashboard  

Bartek    Terraform    CI/CD         Staging env   CDN+Cache    Prod env    Launch
          [devops]     Docker        Pipeline      S3 lifecycle           

Michał P. Threat       Encryption    Auth hardening Rate limit  GDPR       Pentest
          [security]   model         CSP headers   WAF          Deletion   

Kasia     Test plan    Golden tests  Multi-broker  E2E suite    Edge cases  Beta QA
          [QA]         FIFO tests    tests         Regression              

Michał W. Test infra   Testcontainer Property      CI test      Perf test   Load test
          [QA]         setup         tests         optimization automation

Aleksandra Benchmark   -             -             Pre-compute  Optimize    Load test
          [perf]       plan                        design       queries    

Sylwester Monitoring   Basic         Alerting      DR plan      DR test     Monitoring
          [SRE]        plan          monitoring    Runbook      SLA setup   dashboards

Łukasz    Insurance    -             Market        -            Beta plan   Beta mgmt
          [risk]       Legal         validation                           
```

---

## Część 9: Definition of Done — globalny

Każdy feature jest DONE gdy:

- [ ] Kod przechodzi code review (agent `code-reviewer`)
- [ ] Testy jednostkowe napisane PRZED implementacją (TDD)
- [ ] Golden dataset testy przechodzą (jeśli dotyczy kalkulacji)
- [ ] Security review (jeśli dotyczy danych użytkownika, auth, API)
- [ ] Performance benchmark (jeśli dotyczy kalkulacji lub query)
- [ ] UI accessible (WCAG 2.1 AA)
- [ ] Dokumentacja API (jeśli nowy endpoint)
- [ ] Monitoring/alerting skonfigurowane (jeśli nowy serwis/endpoint)
- [ ] Disclaimer widoczny (jeśli dotyczy obliczeń podatkowych)

---

## Część 10: Ryzyka implementacyjne — mitigation plan

| Ryzyko | Prawdop. | Mitigation |
|---|---|---|
| Golden dataset ma błędy (Tomasz się pomylił) | M | Cross-check z drugim doradcą podatkowym |
| IBKR zmienia format CSV w trakcie rozwoju | M | Monitoring, versioned adapters, user reporting |
| Schema XML e-Deklaracje się zmieni | L | Monitoring strony MF, abstrakcja nad schema |
| Sezon podatkowy 2027 — za mało czasu | H | Scope v1 zamrożony, no scope creep, cut features not quality |
| Stripe nie obsługuje polskich regulacji | L | Alternatywa: Przelewy24/PayU — ale Stripe działa w PL |
| Team burnout przed sezonem | M | Sustainable pace, no crunch, hire contractor jeśli trzeba |
| Opinia prawna negatywna (to jest doradztwo) | L | Pivot: eksport danych dla doradców (B2B), nie kalkulator (B2C) |

---

## Część 11: KPI — jak mierzymy sukces

### Pre-Launch (do luty 2027)

| KPI | Target |
|---|---|
| Waitlist signups | 1 000 |
| Golden dataset accuracy | 100% (20/20 poprawnie) |
| Pentest findings P0/P1 | 0 |
| Load test: 5k concurrent | Pass |
| PIT-38 XML validated by e-Deklaracje | Pass |

### Post-Launch (sezon 2027: luty-kwiecień)

| KPI | Target |
|---|---|
| Registered users | 5 000 |
| Paid conversions | 500 (10% conversion) |
| PIT-38 exports | 400 |
| Average accuracy (user-reported) | >98% |
| NPS | >40 |
| Support tickets / user | <0.5 |
| Uptime w sezonie | 99.9% |

### Year 1 (do grudzień 2027)

| KPI | Target |
|---|---|
| Total users | 15 000 |
| Paying users | 3 000 |
| ARR | 300 000 PLN |
| Supported brokers | 8+ |
| Doradcy na planie Advisor | 50 |

---

## Część 12: Zamknięcie — komentarze zespołu

**Mariusz Gil:**
> "Architektura jest prosta — i to jest jej siła. Modular monolith, czyste bounded contexts, pure domain logic. Nie dodawajcie complexity dopóki nie będzie bólu. Jedyne co ma być złożone — to Tax Calculation Engine. Reszta ma być boring."

**Marek [senior-dev]:**
> "Kluczowe: decimal.js WSZĘDZIE gdzie są pieniądze. Ani razu `number`. Nawet w testach. To jest niekompromisowe."

**Kasia [QA]:**
> "Golden dataset to nasz north star. Jeśli Tomasz mówi '1 926,98 PLN' a my dajemy '1 927,00 PLN' — to jest bug. Grosze mają znaczenie."

**Michał P. [security]:**
> "Encryption from day 1. Nie 'dodamy potem'. AWS KMS, column-level encryption na NIP, audit log niemutowalny. Dane finansowe to nie jest zabawa."

**Łukasz [risk]:**
> "Mamy plan B jeśli opinia prawna będzie negatywna: pivot na B2B tool for tax advisors. Nie budujemy w ciemno."

**Brandolini** (telefonicznie z Włoch):
> "I remember this project. You have a strong core domain and a clear bounded context map. Don't let the supporting contexts eat your time. 80% of your effort should go into Tax Calculation Engine. The rest is plumbing. Good luck."

---

## Appendix A: Glossary (Ubiquitous Language)

| Termin | Definicja | Kontekst |
|---|---|---|
| **FIFO** | First In First Out — metoda rozliczania sprzedaży papierów wartościowych. Sprzedawane są te kupione najwcześniej. | Tax Calc |
| **Koszt nabycia (Cost Basis)** | Cena zakupu + prowizja, przeliczone na PLN kursem NBP z dnia-1. | Tax Calc |
| **Przychód (Proceeds)** | Cena sprzedaży, przeliczona na PLN kursem NBP z dnia-1. | Tax Calc |
| **WHT (Withholding Tax)** | Podatek pobrany u źródła od dywidendy za granicą. | Dividends |
| **UPO** | Umowa o Unikaniu Podwójnego Opodatkowania. | Dividends |
| **PIT-38** | Roczne zeznanie podatkowe dla dochodów kapitałowych. | Declaration |
| **PIT-8C** | Informacja o dochodach z kapitałów pieniężnych, wystawiana przez polskiego brokera. | Audit |
| **PIT/ZG** | Załącznik o zagranicznych dochodach — osobny per kraj. | Declaration |
| **Kurs NBP** | Średni kurs waluty ogłaszany przez NBP. Używany z ostatniego dnia roboczego PRZED transakcją. | Exchange Rate |
| **Podatek Belki** | Potoczna nazwa 19% podatku od dochodów kapitałowych (art. 30b ustawy o PIT). | Tax Calc |
| **Golden Dataset** | 20 anonimizowanych zestawów testowych od doradcy podatkowego, z oczekiwanym wynikiem. | QA |
| **ACL (Anti-Corruption Layer)** | Warstwa tłumacząca format zewnętrzny (CSV brokera) na model domenowy. | Broker Import |
| **Walking Skeleton** | Minimalny, działający system przechodzący przez wszystkie warstwy end-to-end. | Architecture |
| **TaxPositionLedger** | Główny agregat. Per ISIN, per user. Zarządza kolejką FIFO i oblicza zysk/stratę. | Tax Calc |

---

*Dokument przygotowany przez Fully Featured AI Agent Team, 2026-04-03.*
*Architektura: Mariusz Gil. Tech Lead: Marek [senior-dev].*
*Kontynuacja: EVENT_STORMING.md*
