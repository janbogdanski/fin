# Architektura Systemu: TaxPilot

## Metadata

| | |
|---|---|
| **Data** | 2026-04-03 |
| **Autorzy** | Marek [senior-dev], Mariusz Gil (konsultacja), Fully Featured AI Agent Team |
| **Input** | EVENT_STORMING.md, IMPLEMENTATION_PLAN.md |
| **Status** | ACCEPTED |

---

## 1. Principia — fundamenty architektoniczne

### 1.1. Podejście

| Zasada | Jak stosujemy | Dlaczego |
|---|---|---|
| **DDD (Domain-Driven Design)** | Bounded Contexts z ES, Aggregaty, Value Objects, Domain Events, Ubiquitous Language | Core domain (Tax Calculation Engine) jest złożony — prawo podatkowe, FIFO, kursy walut. Bez DDD to będzie Big Ball of Mud w 6 miesięcy. |
| **Clean Architecture** | Warstwy: Domain → Application → Infrastructure → UI. Zależności TYLKO do wewnątrz. | Domain layer nie zależy od Symfony, Doctrine, HTTP. Można testować bez frameworka. Można podmienić infrastrukturę. |
| **CQRS** | Oddzielone Command (zapis/mutacja) i Query (odczyt). Nie event sourcing — nie potrzebujemy. | Obliczenia podatkowe (write) mają inną strukturę niż wyświetlanie wyników (read). CQRS daje osobne modele optimized for purpose. |
| **TDD** | Red → Green → Refactor. Golden dataset jako acceptance tests. Property-based tests dla FIFO. | Obliczenia podatkowe MUSZĄ być poprawne co do grosza. Testy są specyfikacją. Bez testów = bez pewności. |
| **Hexagonal Architecture (Ports & Adapters)** | Porty = interfejsy w domenie. Adaptery = implementacje infrastrukturalne. | Broker adaptery (CSV parsery) to klasyczne adaptery. NBP API to adapter. Doctrine repository to adapter. Domena nie wie o nich. |

### 1.2. Czym to NIE jest

- **NIE event sourcing** — za duży overhead dla tego rozmiaru. Standardowy state-based persistence. Audit trail robimy osobno.
- **NIE microservices** — modular monolith. Jeden deployment, moduły z czystymi granicami.
- **NIE over-engineered** — CQRS bez event store, bez projection rebuilding, bez saga. Proste command/query handlers.

---

## 2. Stack technologiczny

### Dyskusja zespołu

> **Marek [senior-dev]:** "Symfony to naturalny wybór. Ma Messenger (command/query bus), Security, Validator, Serializer. Nie trzeba wymyślać koła. Ale — domena MUSI być czysta. Zero use'ów Symfony w `Domain/`."
>
> **Mariusz Gil:** "Symfony Messenger jako command bus + query bus to sprawdzony pattern. Ale pilnujcie jednego: HandleMessage nie powinien zawierać logiki biznesowej. Handler woła serwis domenowy. Handler to adapter, nie domena."
>
> **Aleksandra [perf]:** "PHP 8.4 z JIT da radę z obliczeniami FIFO. brick/math dla precyzji. Sprawdzałam — 10k transakcji FIFO matching < 2 sekundy na PHP 8.4."
>
> **Michał P. [security]:** "Symfony Security z custom authenticator na magic link. Doctrine encryption extension dla NIP. Nie robimy own crypto."

### Finalny stack

| Warstwa | Technologia | Wersja | Rola |
|---|---|---|---|
| **Język** | PHP | 8.4+ | Strict types, readonly classes, enums, fibers |
| **Framework** | Symfony | 7.2+ | HTTP, Messenger, Security, Validator, Serializer |
| **Command/Query Bus** | Symfony Messenger | (builtin) | CQRS — command bus + query bus, async capable |
| **Domain math** | brick/math | 0.12+ | BigDecimal — precyzja obliczeń finansowych, NIGDY float |
| **ORM** | Doctrine ORM | 3.x | Persistence, migrations. Ale domena NIE zależy od Doctrine. |
| **Database** | PostgreSQL | 17 | ACID, JSONB, partial indexes |
| **Cache** | Redis + Symfony Cache | - | Kursy NBP, sesje, pre-computed results |
| **Queue** | Symfony Messenger + Redis transport | - | Async: import CSV, pre-compute calculations |
| **Template** | Twig + Stimulus + Turbo (Hotwire) | - | Server-rendered z interaktywnością. NIE SPA. |
| **CSS** | Tailwind CSS | 4.x | Utility-first, szybki development |
| **Testing** | PHPUnit + Infection (mutation) | - | Unit, integration, golden dataset, mutation testing |
| **Static Analysis** | PHPStan (level max) + Rector | - | Type safety, automated refactoring |
| **API** | Symfony + API Platform (opcja) | - | REST endpoints, OpenAPI spec |
| **File Storage** | Flysystem (local v1 / S3 v2) | - | CSV uploads, PDFs, encrypted |
| **Auth** | Symfony Security + custom magic link | - | Email login, session-based (ADR-015) |
| **Payments** | Stripe PHP SDK | - | Subscriptions, webhooks |
| **NIP Encryption** | sodium_crypto_secretbox | - | Column-level, blind index HMAC (ADR-012) |
| **Infra (dev)** | Docker + Docker Compose | - | Dev environment |
| **Infra (prod v1)** | MyDevil.net + Cloudflare | - | Shared hosting, ~85 PLN/mies (ADR-009) |
| **Infra (prod v2)** | AWS ECS Fargate + RDS | - | Migracja gdy >5k userów |
| **Deploy** | Deployer (SSH) | - | `make deploy` → prod w 30s |
| **CI/CD** | GitHub Actions | - | Lint → PHPStan → Tests → Build → Deploy |
| **Monitoring** | Sentry + UptimeRobot | - | Errors, uptime, alerting |

### Dlaczego Twig+Hotwire zamiast SPA (React/Vue)?

> **Paweł [front-engineer]:** "To jest apka formularzowa — upload CSV, tabele transakcji, formularz PIT-38. Nie potrzebujemy heavy SPA. Twig renderuje HTML, Turbo Frames daje dynamiczne fragmenty (drill-down, filtry), Stimulus daje JS behavior (file upload progress, interactive tables). Mniej kodu, mniej complexity, SSR out of the box, SEO free."
>
> **Mariusz Gil:** "Mniej ruchomych części. Jeden deployment. Jeden stack. Jeśli za rok okaże się że potrzebujemy React — frontend jest za Turbo Frames, więc możemy podmienić fragment po fragmencie."
>
> **Zofia [front-engineer]:** "Accessibility jest prostsza z server-rendered HTML. WCAG compliance z Twig to standard. Z SPA — trzeba walczyć."

---

## 3. Clean Architecture — warstwy

```
┌─────────────────────────────────────────────────────────┐
│                    Infrastructure                        │
│  Symfony Controllers, Doctrine Repositories, CLI,        │
│  Twig Templates, Messenger Handlers, External APIs       │
│                                                          │
│  ┌─────────────────────────────────────────────────┐    │
│  │                 Application                      │    │
│  │  Command Handlers, Query Handlers,               │    │
│  │  Application Services, DTOs, Ports (interfaces)  │    │
│  │                                                   │    │
│  │  ┌──────────────────────────────────────────┐    │    │
│  │  │              Domain                       │    │    │
│  │  │  Entities, Value Objects, Aggregates,     │    │    │
│  │  │  Domain Services, Domain Events,          │    │    │
│  │  │  Repository Interfaces, Policies          │    │    │
│  │  │                                           │    │    │
│  │  │  ★ ZERO zależności od Symfony/Doctrine ★  │    │    │
│  │  └──────────────────────────────────────────┘    │    │
│  └─────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────┘
```

### Reguła zależności (Dependency Rule)

```
Infrastructure → Application → Domain
                                  ↑
                    NIGDY w drugą stronę
```

- **Domain** nie importuje NICZEGO z Application ani Infrastructure
- **Application** importuje Domain, NIE importuje Infrastructure
- **Infrastructure** importuje wszystko (implementuje porty z Application/Domain)

### Co mieszka w każdej warstwie

#### Domain Layer (`src/TaxCalc/Domain/`)

```
Zawiera:
  ✅ Entities (TaxPositionLedger, AnnualTaxCalculation)
  ✅ Value Objects (Money, ISIN, CurrencyCode, TaxYear, NBPRate)
  ✅ Aggregates (TaxPositionLedger jest aggregate root)
  ✅ Domain Events (TaxCalculated, FIFOMatchCompleted, DividendTaxCalculated)
  ✅ Domain Services (FIFOMatchingService, DividendTaxService)
  ✅ Policies (CryptoSeparationPolicy, LossCarryForwardPolicy)
  ✅ Repository Interfaces (TaxPositionLedgerRepositoryInterface)
  ✅ Exceptions (InsufficientSharesException, InvalidFIFOStateException)

NIE zawiera:
  ❌ Doctrine annotations/attributes
  ❌ Symfony use statements
  ❌ HTTP/request/response concepts
  ❌ Database queries
  ❌ External API calls
```

#### Application Layer (`src/TaxCalc/Application/`)

```
Zawiera:
  ✅ Commands + Command Handlers (CalculateAnnualTax, ImportTransactions)
  ✅ Queries + Query Handlers (GetTaxSummary, GetFIFOMatching)
  ✅ DTOs (TaxSummaryDTO, TransactionDTO)
  ✅ Port Interfaces (NBPRateProviderInterface, BrokerFileParserInterface)
  ✅ Application Services (orchestration)
  ✅ Event Subscribers (application-level reactions to domain events)

NIE zawiera:
  ❌ Doctrine/Symfony specifics
  ❌ HTTP concepts
  ❌ Business rules (te są w Domain)
```

#### Infrastructure Layer (`src/TaxCalc/Infrastructure/`)

```
Zawiera:
  ✅ Doctrine Repositories (implementing domain interfaces)
  ✅ Doctrine Entity Mappings (XML/PHP, NIE w Domain entities)
  ✅ Symfony Messenger configuration
  ✅ Controller / API endpoints
  ✅ Twig templates
  ✅ External API clients (NBP API, Stripe)
  ✅ CSV Broker Adapters (IBKR, Degiro, XTB)
  ✅ File storage (Flysystem S3)
  ✅ CLI commands
```

---

## 4. CQRS — Command/Query Separation

### Command side (Write)

```php
// Command — co chcemy zrobić (DTO, nic więcej)
final readonly class CalculateAnnualTaxCommand
{
    public function __construct(
        public UserId $userId,
        public TaxYear $taxYear,
    ) {}
}

// Handler — orchestration (NIE logika biznesowa)
final readonly class CalculateAnnualTaxHandler
{
    public function __construct(
        private TaxPositionLedgerRepository $ledgerRepository,
        private NBPRateProvider $nbpRateProvider,
        private AnnualTaxCalculationRepository $calculationRepository,
        private EventBus $eventBus,
    ) {}

    public function __invoke(CalculateAnnualTaxCommand $command): void
    {
        $ledgers = $this->ledgerRepository->findByUserAndYear(
            $command->userId,
            $command->taxYear,
        );

        $calculation = AnnualTaxCalculation::create(
            $command->userId,
            $command->taxYear,
        );

        foreach ($ledgers as $ledger) {
            $results = $ledger->calculateTax($this->nbpRateProvider);
            $calculation->addResults($results);
        }

        $calculation->applyPriorYearLosses();

        $this->calculationRepository->save($calculation);
        $this->eventBus->dispatch(...$calculation->pullDomainEvents());
    }
}
```

### Query side (Read)

```php
// Query — co chcemy odczytać
final readonly class GetTaxSummaryQuery
{
    public function __construct(
        public UserId $userId,
        public TaxYear $taxYear,
    ) {}
}

// Result — dedykowany DTO dla widoku (nie entity!)
final readonly class TaxSummaryResult
{
    public function __construct(
        public string $totalProceeds,      // string z BigDecimal
        public string $totalCostBasis,
        public string $totalGainLoss,
        public string $totalTaxDue,
        public array $perInstrumentType,   // breakdown
        public array $perCountryDividends, // PIT/ZG data
        public string $cryptoGainLoss,
        public string $cryptoTaxDue,
    ) {}
}

// Handler — może czytać bezpośrednio z DB (nie musi przez repo)
final readonly class GetTaxSummaryHandler
{
    public function __construct(
        private Connection $connection, // Doctrine DBAL — raw query OK
    ) {}

    public function __invoke(GetTaxSummaryQuery $query): TaxSummaryResult
    {
        // Optimized read query, może być denormalizowany view
        // NIE przechodzi przez domain model
    }
}
```

### Bus configuration (Symfony Messenger)

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        buses:
            command.bus:
                middleware:
                    - doctrine_transaction  # każdy command w transakcji
                    - validation
            query.bus:
                middleware:
                    - validation

        transports:
            async:
                dsn: 'redis://redis:6379/messages'

        routing:
            # Async commands (heavy operations)
            'App\BrokerImport\Application\Command\ImportTransactionsCommand': async
            'App\TaxCalc\Application\Command\PreComputeTaxCommand': async
```

> **Mariusz Gil:** "Command bus = synchronous by default, async dla ciężkich operacji (import CSV). Query bus = ZAWSZE synchronous. Proste. Nie komplikujcie."

---

## 5. DDD — Domain Model w szczegółach

### 5.1. Value Objects

```php
// src/TaxCalc/Domain/ValueObject/Money.php
final readonly class Money
{
    private function __construct(
        private BigDecimal $amount,
        private CurrencyCode $currency,
    ) {}

    /**
     * Factory z precyzją źródłową — NIE zaokrągla.
     * Zaokrąglanie do scale 2 dopiero przy: persistence, display, PIT-38.
     * @see ADR-006: "Rounding dopiero na końcu"
     */
    public static function of(string|BigDecimal $amount, CurrencyCode $currency): self
    {
        return new self(
            BigDecimal::of($amount),
            $currency,
        );
    }

    /**
     * Zaokrąglenie do groszy — wywoływać TYLKO na granicach (zapis, wyświetlanie, PIT-38).
     */
    public function rounded(): self
    {
        return new self(
            $this->amount->toScale(2, RoundingMode::HALF_UP),
            $this->currency,
        );
    }

    public static function zero(CurrencyCode $currency): self
    {
        return new self(BigDecimal::zero()->toScale(2), $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount->plus($other->amount), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount->minus($other->amount), $this->currency);
    }

    public function multiply(BigDecimal|string $factor): self
    {
        return new self(
            $this->amount->multipliedBy($factor)->toScale(2, RoundingMode::HALF_UP),
            $this->currency,
        );
    }

    /**
     * Przeliczenie na PLN kursem NBP.
     * WALIDUJE zgodność waluty Money z walutą kursu NBP.
     * NIE zaokrągla — intermediate precision. Wywołaj ->rounded() na granicy.
     *
     * @throws CurrencyMismatchException gdy waluta Money ≠ waluta NBPRate
     */
    public function toPLN(NBPRate $rate): self
    {
        if ($this->currency->equals(CurrencyCode::PLN)) {
            return $this;
        }

        if (!$this->currency->equals($rate->currency)) {
            throw new CurrencyMismatchException($this->currency, $rate->currency);
        }

        return new self(
            $this->amount->multipliedBy($rate->rate),
            CurrencyCode::PLN,
        );
    }

    public function isNegative(): bool
    {
        return $this->amount->isNegative();
    }

    public function amount(): BigDecimal
    {
        return $this->amount;
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if (!$this->currency->equals($other->currency)) {
            throw new CurrencyMismatchException($this->currency, $other->currency);
        }
    }
}
```

```php
// src/Shared/Domain/ValueObject/ISIN.php
final readonly class ISIN
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));

        if (!preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', $normalized)) {
            throw new InvalidISINException($value);
        }

        return new self($normalized);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function countryCode(): string
    {
        return substr($this->value, 0, 2);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
```

```php
// src/TaxCalc/Domain/ValueObject/NBPRate.php
final readonly class NBPRate
{
    private function __construct(
        private CurrencyCode $currency,
        private BigDecimal $rate,
        private \DateTimeImmutable $effectiveDate,
        private string $tableNumber,
    ) {}

    public static function create(
        CurrencyCode $currency,
        BigDecimal $rate,
        \DateTimeImmutable $effectiveDate,
        string $tableNumber,
    ): self {
        if ($rate->isNegative() || $rate->isZero()) {
            throw new \InvalidArgumentException("NBP rate must be positive, got: {$rate}");
        }

        if (!preg_match('/^\d{3}\/[ABC]\/NBP\/\d{4}$/', $tableNumber)) {
            throw new \InvalidArgumentException("Invalid NBP table number: {$tableNumber}");
        }

        return new self($currency, $rate, $effectiveDate, $tableNumber);
    }

    public function currency(): CurrencyCode { return $this->currency; }
    public function rate(): BigDecimal { return $this->rate; }
    public function effectiveDate(): \DateTimeImmutable { return $this->effectiveDate; }
    public function tableNumber(): string { return $this->tableNumber; }
}
```

### 5.2. Aggregate Root — TaxPositionLedger

```php
// src/TaxCalc/Domain/Model/TaxPositionLedger.php

/**
 * Aggregate Root.
 * Per ISIN × User (BEZ TaxYear — FIFO jest ciągłe cross-year!)
 * Cross-broker! (FIFO per instrument, nie per broker)
 * Odpowiada za FIFO matching i obliczanie zysku/straty.
 *
 * UWAGA: closedPositions NIE są ładowane do agregatu (append-only).
 * Aggregate operuje TYLKO na openPositions (FIFO queue).
 * ClosedPositions są persisted osobno przez repozytorium.
 *
 * @see ADR-017 (Multi-Year FIFO)
 */
final class TaxPositionLedger
{
    private UserId $userId;
    private ISIN $isin;
    private TaxCategory $taxCategory;

    /** @var list<OpenPosition> sorted by date ASC — FIFO queue (jedyne co ładujemy) */
    private array $openPositions = [];

    /** @var list<ClosedPosition> nowo utworzone w tej sesji — do zapisu, NIE ładowane z DB */
    private array $newClosedPositions = [];

    /** @var list<DomainEvent> */
    private array $domainEvents = [];

    public static function create(
        UserId $userId,
        ISIN $isin,
        TaxCategory $taxCategory,
    ): self {
        $ledger = new self();
        $ledger->userId = $userId;
        $ledger->isin = $isin;
        $ledger->taxCategory = $taxCategory;

        return $ledger;
    }

    public function registerBuy(
        TransactionId $txId,
        \DateTimeImmutable $date,
        BigDecimal $quantity,
        Money $pricePerUnit,
        Money $commission,
        BrokerId $broker,
        NBPRate $nbpRate,
    ): void {
        $costInPLN = $pricePerUnit
            ->multiply($quantity)
            ->toPLN($nbpRate);

        $commissionInPLN = $commission->toPLN($nbpRate);

        // Pre-compute per-unit costs — używane przy partial sells (B-01 fix)
        $commissionPerUnitPLN = $commissionInPLN->amount()
            ->dividedBy($quantity, 8, RoundingMode::HALF_UP);

        $this->openPositions[] = new OpenPosition(
            transactionId: $txId,
            date: $date,
            originalQuantity: $quantity,        // B-01: ZAWSZE oryginalna ilość
            remainingQuantity: $quantity,
            pricePerUnit: $pricePerUnit,
            costPerUnitPLN: $costInPLN->amount()->dividedBy($quantity, 8, RoundingMode::HALF_UP),
            commissionPerUnitPLN: $commissionPerUnitPLN, // B-01: prowizja per unit
            nbpRate: $nbpRate,
            broker: $broker,
        );

        // utrzymuj sortowanie FIFO
        usort($this->openPositions, fn (OpenPosition $a, OpenPosition $b) =>
            $a->date <=> $b->date
        );
    }

    /**
     * @return list<ClosedPosition> — wynik FIFO matching
     */
    public function registerSell(
        TransactionId $txId,
        \DateTimeImmutable $date,
        BigDecimal $quantity,
        Money $pricePerUnit,
        Money $commission,
        BrokerId $broker,
        NBPRate $nbpRate,
    ): array {
        $remainingToSell = $quantity;
        $matched = [];

        $proceedsPerUnit = $pricePerUnit->toPLN($nbpRate);
        $sellCommissionPLN = $commission->toPLN($nbpRate);

        // proporcjonalny rozkład prowizji sell
        $commissionPerUnit = $sellCommissionPLN->amount()
            ->dividedBy($quantity, 4, RoundingMode::HALF_UP);

        while ($remainingToSell->isPositive()) {
            $oldest = $this->findOldestOpenPosition();

            if ($oldest === null) {
                throw new InsufficientSharesException(
                    $this->isin,
                    $remainingToSell,
                );
            }

            $matchQuantity = BigDecimal::min($remainingToSell, $oldest->remainingQuantity);

            // B-01 FIX: prowizja buy per unit — pre-computed w registerBuy
            // Używa originalQuantity jako denominator, nie remainingQuantity
            $costBasisPLN = $oldest->costPerUnitPLN
                ->multipliedBy($matchQuantity);

            $buyCommissionPLN = $oldest->commissionPerUnitPLN
                ->multipliedBy($matchQuantity);

            $proceedsPLN = $proceedsPerUnit->amount()
                ->multipliedBy($matchQuantity);

            $sellCommPLN = $commissionPerUnit
                ->multipliedBy($matchQuantity);

            // B-11: intermediate precision — zaokrąglenie dopiero w ClosedPosition
            $gainLoss = $proceedsPLN
                ->minus($costBasisPLN)
                ->minus($buyCommissionPLN)
                ->minus($sellCommPLN);

            $closed = new ClosedPosition(
                buyTransactionId: $oldest->transactionId,
                sellTransactionId: $txId,
                isin: $this->isin,
                quantity: $matchQuantity,
                costBasisPLN: $costBasisPLN,
                proceedsPLN: $proceedsPLN,
                buyCommissionPLN: $buyCommissionPLN,
                sellCommissionPLN: $sellCommPLN,
                gainLossPLN: $gainLoss,
                buyDate: $oldest->date,
                sellDate: $date,
                buyNBPRate: $oldest->nbpRate,
                sellNBPRate: $nbpRate,
                buyBroker: $oldest->broker,
                sellBroker: $broker,
            );

            $matched[] = $closed;
            $this->newClosedPositions[] = $closed; // B-05: append-only, NIE ładowane z DB

            // aktualizuj FIFO queue
            $oldest->reduceQuantity($matchQuantity);
            if ($oldest->remainingQuantity->isZero()) {
                $this->removeOpenPosition($oldest);
            }

            $remainingToSell = $remainingToSell->minus($matchQuantity);
        }

        $this->domainEvents[] = new FIFOMatchCompleted(
            $this->userId,
            $this->isin,
            $txId,
            count($matched),
        );

        return $matched;
    }

    /**
     * Nowo utworzone ClosedPositions (do zapisu przez repozytorium).
     * @return list<ClosedPosition>
     */
    public function flushNewClosedPositions(): array
    {
        $new = $this->newClosedPositions;
        $this->newClosedPositions = [];
        return $new;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    private function findOldestOpenPosition(): ?OpenPosition
    {
        foreach ($this->openPositions as $position) {
            if ($position->remainingQuantity->isPositive()) {
                return $position;
            }
        }
        return null;
    }

    private function removeOpenPosition(OpenPosition $target): void
    {
        $this->openPositions = array_values(
            array_filter(
                $this->openPositions,
                fn (OpenPosition $p) => $p !== $target,
            ),
        );
    }
}
```

### 5.3. Domain Service — DividendTaxService

```php
// src/TaxCalc/Domain/Service/DividendTaxService.php
final readonly class DividendTaxService
{
    private const string POLISH_TAX_RATE = '0.19';

    public function calculate(
        Money $grossDividend,
        CurrencyCode $sourceCurrency,
        NBPRate $nbpRate,
        CountryCode $sourceCountry,
        BigDecimal $actualWHTRate,    // faktycznie pobrany % u źródła
        UPORegistry $upoRegistry,
    ): DividendTaxResult {
        $grossPLN = $grossDividend->toPLN($nbpRate);

        $whtPaidPLN = Money::of(
            $grossPLN->amount()->multipliedBy($actualWHTRate),
            CurrencyCode::PLN,
        );

        $polishTax = Money::of(
            $grossPLN->amount()->multipliedBy(self::POLISH_TAX_RATE),
            CurrencyCode::PLN,
        );

        // Dopłata = max(0, 19% - WHT)
        $taxDuePL = Money::of(
            BigDecimal::max(
                BigDecimal::zero(),
                $polishTax->amount()->minus($whtPaidPLN->amount()),
            ),
            CurrencyCode::PLN,
        );

        $upoRate = $upoRegistry->getRate($sourceCountry);

        return new DividendTaxResult(
            grossDividendPLN: $grossPLN,
            whtPaidPLN: $whtPaidPLN,
            whtRate: $actualWHTRate,
            upoRate: $upoRate,
            polishTaxDue: $taxDuePL,
            sourceCountry: $sourceCountry,
            nbpRate: $nbpRate,
        );
    }
}
```

### 5.4. Policy — LossCarryForwardPolicy

```php
// src/TaxCalc/Domain/Policy/LossCarryForwardPolicy.php

/**
 * Art. 9 ust. 3 ustawy o PIT:
 * - Strata do odliczenia przez 5 lat
 * - Max 50% straty w jednym roku
 * - Krypto straty OSOBNO od equity strat
 */
final readonly class LossCarryForwardPolicy
{
    private const int MAX_CARRY_FORWARD_YEARS = 5;
    private const string MAX_ANNUAL_DEDUCTION_RATE = '0.50';

    /**
     * Oblicza maksymalny możliwy odpis straty.
     * NIE rekomenduje kwoty — to byłoby doradztwo podatkowe.
     * Zwraca dostępny zakres: 0 do maxDeduction.
     */
    public function availableDeduction(
        PriorYearLoss $loss,
        TaxYear $currentYear,
    ): LossDeductionRange {
        $yearsElapsed = $currentYear->value - $loss->taxYear->value;

        if ($yearsElapsed > self::MAX_CARRY_FORWARD_YEARS || $yearsElapsed < 1) {
            return LossDeductionRange::zero($loss->taxCategory);
        }

        $maxDeduction = $loss->remainingAmount
            ->multipliedBy(self::MAX_ANNUAL_DEDUCTION_RATE)
            ->toScale(2, RoundingMode::DOWN);  // zaokrąglaj w dół — na korzyść fiskusa

        return new LossDeductionRange(
            taxCategory: $loss->taxCategory,
            lossYear: $loss->taxYear,
            originalAmount: $loss->originalAmount,
            remainingAmount: $loss->remainingAmount,
            maxDeductionThisYear: $maxDeduction,
            expiresInYear: TaxYear::of($loss->taxYear->value + self::MAX_CARRY_FORWARD_YEARS),
            yearsRemaining: self::MAX_CARRY_FORWARD_YEARS - $yearsElapsed,
        );
    }
}
```

---

## 6. Bounded Contexts — struktura modułów

### 6.1. Mapa modułów

```
src/
├── Shared/                          # Shared Kernel
│   └── Domain/
│       └── ValueObject/
│           ├── UserId.php
│           ├── Money.php
│           ├── CurrencyCode.php
│           ├── ISIN.php
│           ├── CountryCode.php
│           └── TaxYear.php
│
├── Identity/                        # BC: Identity & Access (Supporting)
│   ├── Application/
│   │   ├── Command/
│   │   │   ├── RegisterUser.php
│   │   │   └── RegisterUserHandler.php
│   │   └── Query/
│   ├── Domain/
│   │   ├── Model/User.php
│   │   └── Repository/UserRepositoryInterface.php
│   └── Infrastructure/
│       ├── Security/MagicLinkAuthenticator.php
│       ├── Doctrine/DoctrineUserRepository.php
│       └── Controller/AuthController.php
│
├── BrokerImport/                    # BC: Broker Integration (Supporting)
│   ├── Application/
│   │   ├── Command/
│   │   │   ├── ImportCSV.php
│   │   │   └── ImportCSVHandler.php
│   │   ├── Port/
│   │   │   └── BrokerAdapterInterface.php
│   │   └── DTO/
│   │       ├── NormalizedTransaction.php
│   │       └── ParseResult.php
│   ├── Domain/
│   │   ├── Model/ImportSession.php
│   │   └── Event/TransactionsImported.php
│   └── Infrastructure/
│       ├── Adapter/
│       │   ├── IBKR/IBKRActivityAdapter.php
│       │   ├── Degiro/DegiroTransactionsAdapter.php
│       │   └── XTB/XTBHistoryAdapter.php
│       ├── Controller/ImportController.php
│       └── Doctrine/DoctrineImportSessionRepository.php
│
├── ExchangeRate/                    # BC: Exchange Rate Service (Generic)
│   ├── Application/
│   │   ├── Port/
│   │   │   └── ExchangeRateProviderInterface.php
│   │   └── Query/
│   │       ├── GetNBPRate.php
│   │       └── GetNBPRateHandler.php
│   ├── Domain/
│   │   └── Model/DailyExchangeRate.php
│   └── Infrastructure/
│       ├── NBP/NBPApiClient.php
│       ├── Cache/CachedExchangeRateProvider.php
│       └── Doctrine/DoctrineExchangeRateRepository.php
│
├── TaxCalc/                         # BC: Tax Calculation Engine ⭐ CORE
│   ├── Application/
│   │   ├── Command/
│   │   │   ├── CalculateAnnualTax.php
│   │   │   ├── CalculateAnnualTaxHandler.php
│   │   │   ├── RegisterTransaction.php
│   │   │   └── RegisterTransactionHandler.php
│   │   ├── Query/
│   │   │   ├── GetTaxSummary.php
│   │   │   ├── GetTaxSummaryHandler.php
│   │   │   ├── GetFIFOMatching.php
│   │   │   └── GetFIFOMatchingHandler.php
│   │   └── Port/
│   │       └── NBPRateProviderInterface.php
│   ├── Domain/
│   │   ├── Model/
│   │   │   ├── TaxPositionLedger.php      # Aggregate Root
│   │   │   ├── OpenPosition.php
│   │   │   ├── ClosedPosition.php
│   │   │   ├── AnnualTaxCalculation.php   # Aggregate Root
│   │   │   ├── PriorYearLoss.php
│   │   │   └── DividendTaxResult.php
│   │   ├── Service/
│   │   │   ├── FIFOMatchingService.php
│   │   │   └── DividendTaxService.php
│   │   ├── Policy/
│   │   │   ├── LossCarryForwardPolicy.php
│   │   │   └── CryptoSeparationPolicy.php
│   │   ├── Event/
│   │   │   ├── TaxCalculated.php
│   │   │   └── FIFOMatchCompleted.php
│   │   ├── Repository/
│   │   │   ├── TaxPositionLedgerRepositoryInterface.php
│   │   │   └── AnnualTaxCalculationRepositoryInterface.php
│   │   └── Exception/
│   │       ├── InsufficientSharesException.php
│   │       └── InvalidFIFOStateException.php
│   └── Infrastructure/
│       ├── Doctrine/
│       │   ├── DoctrineTaxPositionLedgerRepository.php
│       │   └── mapping/         # XML mappings (osobno od domain)
│       └── Controller/
│           └── TaxCalcController.php
│
├── Declaration/                     # BC: Tax Declaration (Core Supporting)
│   ├── Application/
│   │   ├── Command/
│   │   │   ├── GeneratePIT38.php
│   │   │   └── GeneratePIT38Handler.php
│   │   └── Query/
│   │       └── GetDeclarationPreview.php
│   ├── Domain/
│   │   ├── Model/TaxDeclaration.php
│   │   └── Service/
│   │       ├── PIT38XMLGenerator.php
│   │       ├── PITZGGenerator.php
│   │       └── AuditReportGenerator.php
│   └── Infrastructure/
│       ├── Template/              # XML templates for e-Deklaracje
│       ├── PDF/PDFRenderer.php
│       └── Controller/DeclarationController.php
│
├── Audit/                           # BC: Audit & Reporting (Supporting)
│   ├── Application/
│   ├── Domain/
│   └── Infrastructure/
│
└── Billing/                         # BC: Billing & Subscription (Generic)
    ├── Application/
    ├── Domain/
    └── Infrastructure/
        └── Stripe/StripeWebhookHandler.php
```

### 6.2. Komunikacja między modułami

```
[BrokerImport] ──event: TransactionsImported──→ [TaxCalc]
                                                     │
                                                     ├── query → [ExchangeRate] (kurs NBP)
                                                     │
                                                     └── event: TaxCalculated ──→ [Declaration]
                                                                              ──→ [Audit]

[Identity] ── auth middleware ──→ all modules
[Billing]  ── paywall check ──→ [Declaration] (export = paid feature)
```

> **Marek:** "In-process events via Symfony Messenger. TransactionsImported event dispatched → TaxCalc handler reaguje i przelicza. Synchronicznie. Jak urośniemy — przestawiamy na async transport."

---

## 7. Doctrine Mapping — separation from Domain

### Dlaczego XML mapping zamiast PHP attributes?

> **Mariusz Gil:** "Jeśli postawicie `#[ORM\Entity]` na domain entity — złamaliście Clean Architecture w pierwszej linijce. Domain NIE WIE o Doctrine. Mapping XML jest w Infrastructure, a Domain jest czysta."

```xml
<!-- src/TaxCalc/Infrastructure/Doctrine/mapping/TaxPositionLedger.orm.xml -->
<entity name="App\TaxCalc\Domain\Model\TaxPositionLedger"
        table="tax_position_ledger"
        repository-class="App\TaxCalc\Infrastructure\Doctrine\DoctrineTaxPositionLedgerRepository">

    <id name="id" type="uuid" column="id"/>

    <embedded name="userId" class="App\Shared\Domain\ValueObject\UserId"/>
    <embedded name="isin" class="App\Shared\Domain\ValueObject\ISIN"/>
    <embedded name="taxYear" class="App\TaxCalc\Domain\ValueObject\TaxYear"/>

    <field name="taxCategory" type="string" column="tax_category" enumType="App\TaxCalc\Domain\ValueObject\TaxCategory"/>

    <one-to-many field="openPositions" target-entity="App\TaxCalc\Domain\Model\OpenPosition" mapped-by="ledger">
        <cascade>
            <cascade-persist/>
        </cascade>
        <order-by>
            <order-by-field name="date" direction="ASC"/>
        </order-by>
    </one-to-many>

    <one-to-many field="closedPositions" target-entity="App\TaxCalc\Domain\Model\ClosedPosition" mapped-by="ledger">
        <cascade>
            <cascade-persist/>
        </cascade>
    </one-to-many>
</entity>
```

---

## 8. Testing Strategy

### Piramida testów

```
          ┌──────────────────────┐
          │  Golden Dataset (E2E) │  ← 20 zestawów od Tomasza
          │  CSV → PIT-38 XML    │  ← "source of truth"
          └──────────┬───────────┘
                     │
          ┌──────────┴───────────┐
          │  Integration Tests    │  ← moduł ↔ moduł
          │  Testcontainers (PG)  │  ← real DB
          └──────────┬───────────┘
                     │
    ┌────────────────┼────────────────┐
    │                │                │
┌───┴───┐     ┌─────┴─────┐    ┌─────┴─────┐
│ Unit  │     │ Property  │    │ Adapter   │
│ Tests │     │ Based     │    │ Tests     │
│       │     │ Tests     │    │           │
│ Money │     │ "FIFO sum │    │ IBKR CSV  │
│ FIFO  │     │  = total" │    │ Degiro    │
│ Rates │     │ "no loss  │    │ XTB       │
│       │     │  of qty"  │    │           │
└───────┘     └───────────┘    └───────────┘
```

### TDD workflow

```php
// KROK 1: RED — test PRZED implementacją

final class FIFOMatchingTest extends TestCase
{
    /**
     * Tomasz golden dataset #001:
     * Buy 100 AAPL @ $170, 15.03.2025, commission $1
     * Sell 100 AAPL @ $200, 20.09.2025, commission $1
     * NBP 14.03.2025 = 4.05, NBP 19.09.2025 = 3.95
     * Expected: gain = 10 142.00 PLN, tax = 1 926.98 PLN
     */
    public function test_simple_buy_sell_with_currency_conversion(): void
    {
        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'), // AAPL
            TaxYear::of(2025),
            TaxCategory::EQUITY,
        );

        $ledger->registerBuy(
            txId: TransactionId::generate(),
            date: new \DateTimeImmutable('2025-03-15'),
            quantity: BigDecimal::of('100'),
            pricePerUnit: Money::of('170.00', CurrencyCode::USD),
            commission: Money::of('1.00', CurrencyCode::USD),
            broker: BrokerId::of('ibkr'),
            nbpRate: new NBPRate(CurrencyCode::USD, BigDecimal::of('4.05'), new \DateTimeImmutable('2025-03-14'), '052/A/NBP/2025'),
        );

        $results = $ledger->registerSell(
            txId: TransactionId::generate(),
            date: new \DateTimeImmutable('2025-09-20'),
            quantity: BigDecimal::of('100'),
            pricePerUnit: Money::of('200.00', CurrencyCode::USD),
            commission: Money::of('1.00', CurrencyCode::USD),
            broker: BrokerId::of('ibkr'),
            nbpRate: new NBPRate(CurrencyCode::USD, BigDecimal::of('3.95'), new \DateTimeImmutable('2025-09-19'), '183/A/NBP/2025'),
        );

        self::assertCount(1, $results);

        $closed = $results[0];
        // Koszt: 100 × 170 × 4.05 = 68 850.00
        self::assertTrue($closed->costBasisPLN->isEqualTo('68850.00'));
        // Przychód: 100 × 200 × 3.95 = 79 000.00
        self::assertTrue($closed->proceedsPLN->isEqualTo('79000.00'));
        // Prowizja buy: 1 × 4.05 = 4.05
        self::assertTrue($closed->buyCommissionPLN->isEqualTo('4.05'));
        // Prowizja sell: 1 × 3.95 = 3.95
        self::assertTrue($closed->sellCommissionPLN->isEqualTo('3.95'));
        // Gain: 79000 - 68850 - 4.05 - 3.95 = 10 142.00
        self::assertTrue($closed->gainLossPLN->isEqualTo('10142.00'));
    }

    /**
     * FIFO cross-broker:
     * Buy 100 AAPL on IBKR (Jan)
     * Buy 100 AAPL on Degiro (Mar)
     * Sell 50 AAPL on Degiro (Jun)
     * → FIFO says: sold from IBKR (Jan buy), not Degiro
     */
    public function test_fifo_matches_oldest_across_brokers(): void
    {
        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'),
            TaxYear::of(2025),
            TaxCategory::EQUITY,
        );

        $ibkrBuy = TransactionId::generate();
        $degiroBuy = TransactionId::generate();

        $ledger->registerBuy(
            txId: $ibkrBuy,
            date: new \DateTimeImmutable('2025-01-15'),
            quantity: BigDecimal::of('100'),
            pricePerUnit: Money::of('170.00', CurrencyCode::USD),
            commission: Money::of('1.00', CurrencyCode::USD),
            broker: BrokerId::of('ibkr'),
            nbpRate: new NBPRate(CurrencyCode::USD, BigDecimal::of('4.00'), new \DateTimeImmutable('2025-01-14'), '009/A/NBP/2025'),
        );

        $ledger->registerBuy(
            txId: $degiroBuy,
            date: new \DateTimeImmutable('2025-03-15'),
            quantity: BigDecimal::of('100'),
            pricePerUnit: Money::of('180.00', CurrencyCode::USD),
            commission: Money::of('2.00', CurrencyCode::USD),
            broker: BrokerId::of('degiro'),
            nbpRate: new NBPRate(CurrencyCode::USD, BigDecimal::of('4.05'), new \DateTimeImmutable('2025-03-14'), '052/A/NBP/2025'),
        );

        $results = $ledger->registerSell(
            txId: TransactionId::generate(),
            date: new \DateTimeImmutable('2025-06-20'),
            quantity: BigDecimal::of('50'),
            pricePerUnit: Money::of('200.00', CurrencyCode::USD),
            commission: Money::of('1.00', CurrencyCode::USD),
            broker: BrokerId::of('degiro'),
            nbpRate: new NBPRate(CurrencyCode::USD, BigDecimal::of('3.90'), new \DateTimeImmutable('2025-06-19'), '120/A/NBP/2025'),
        );

        // FIFO: sold 50 from IBKR January buy, NOT from Degiro March buy
        self::assertCount(1, $results);
        self::assertTrue($results[0]->buyTransactionId->equals($ibkrBuy));
        self::assertFalse($results[0]->buyTransactionId->equals($degiroBuy));
        self::assertTrue($results[0]->buyBroker->equals(BrokerId::of('ibkr')));
        self::assertTrue($results[0]->sellBroker->equals(BrokerId::of('degiro')));
    }
}
```

### Property-based tests

```php
// Invarianty FIFO — muszą być ZAWSZE prawdziwe

final class FIFOPropertyTest extends TestCase
{
    /**
     * Dla dowolnej sekwencji buy/sell:
     * suma sprzedanych quantity ≤ suma kupionych quantity
     */
    #[Property]
    public function total_sold_never_exceeds_total_bought(
        array $buys,
        array $sells,
    ): void {
        // ... property-based test z generatorem transakcji
    }

    /**
     * Dla dowolnej sekwencji buy/sell:
     * każdy sell jest matchowany z NAJSTARSZYM dostępnym buy
     */
    #[Property]
    public function sell_always_matches_oldest_buy(): void { /* ... */ }

    /**
     * gainLoss = proceeds - costBasis - commissions
     * Zawsze. Dokładnie. Co do grosza.
     */
    #[Property]
    public function gain_loss_equals_proceeds_minus_costs_minus_commissions(): void { /* ... */ }
}
```

---

## 9. Infrastructure — Docker + Makefile

### docker-compose.yml

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/Dockerfile
    volumes:
      - .:/app
    ports:
      - "8080:8080"
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    environment:
      - APP_ENV=dev
      - DATABASE_URL=postgresql://taxpilot:taxpilot@postgres:5432/taxpilot
      - REDIS_URL=redis://redis:6379

  postgres:
    image: postgres:17-alpine
    environment:
      POSTGRES_DB: taxpilot
      POSTGRES_USER: taxpilot
      POSTGRES_PASSWORD: taxpilot
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U taxpilot"]
      interval: 5s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 5s
      retries: 5

  messenger-worker:
    build:
      context: .
      dockerfile: docker/Dockerfile
    command: php bin/console messenger:consume async --time-limit=3600
    depends_on:
      - postgres
      - redis
    environment:
      - APP_ENV=dev
      - DATABASE_URL=postgresql://taxpilot:taxpilot@postgres:5432/taxpilot
      - REDIS_URL=redis://redis:6379

volumes:
  postgres_data:
```

### Makefile

```makefile
.PHONY: dev test lint stan

# Development
dev:
	docker compose up -d

stop:
	docker compose down

logs:
	docker compose logs -f app

shell:
	docker compose exec app bash

# Testing
test:
	docker compose exec app php bin/phpunit

test-unit:
	docker compose exec app php bin/phpunit --testsuite=unit

test-integration:
	docker compose exec app php bin/phpunit --testsuite=integration

test-golden:
	docker compose exec app php bin/phpunit --testsuite=golden-dataset

test-coverage:
	docker compose exec app php bin/phpunit --coverage-html var/coverage

# Quality
lint:
	docker compose exec app php vendor/bin/php-cs-fixer fix --dry-run --diff

fix:
	docker compose exec app php vendor/bin/php-cs-fixer fix

stan:
	docker compose exec app php vendor/bin/phpstan analyse --level=max

infection:
	docker compose exec app php vendor/bin/infection --min-msi=80

# All checks (CI parity)
ci: lint stan test

# Database
migrate:
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

migrate-diff:
	docker compose exec app php bin/console doctrine:migrations:diff

# Messenger
consume:
	docker compose exec app php bin/console messenger:consume async -vv
```

---

## 10. ADR Log

| ADR | Decyzja | Status |
|---|---|---|
| ADR-001 | Modular Monolith (nie microservices) | ACCEPTED |
| ADR-002 | PHP 8.4 + Symfony 7.2 | ACCEPTED |
| ADR-003 | Clean Architecture (Domain → Application → Infrastructure) | ACCEPTED |
| ADR-004 | CQRS via Symfony Messenger (bez Event Sourcing) | ACCEPTED |
| ADR-005 | Twig + Hotwire (nie SPA) | ACCEPTED |
| ADR-006 | brick/math BigDecimal (nigdy float, zaokrąglanie matematyczne art. 63 §1) | UPDATED |
| ADR-007 | Doctrine XML mapping (nie attributes w Domain) | ACCEPTED |
| ADR-008 | TDD + Golden Dataset + Property-based tests | ACCEPTED |
| ADR-009 | MyDevil.net prod v1, AWS ECS Fargate prod v2 | UPDATED |
| ADR-010 | In-process events (Messenger sync), async dla importu | ACCEPTED |
| ADR-011 | Tax Calculation Policies (Strategy Pattern, versioned per year) | ACCEPTED |
| ADR-012 | PII Encryption: sodium_crypto_secretbox, blind index | ACCEPTED |
| ADR-013 | Data Retention & GDPR: anonymize on delete, retain 5 years | ACCEPTED |
| ADR-014 | Secrets Management: .env.local (v1), AWS Secrets Manager (v2) | ACCEPTED |
| ADR-015 | Authentication Security: magic link spec, 256-bit token, 15min | ACCEPTED |
| ADR-016 | Timezone Handling: UTC storage, CET for NBP business day | ACCEPTED |
| ADR-017 | Multi-Year FIFO: aggregate per (UserId, ISIN), bez TaxYear | ACCEPTED |
| ADR-018 | CSV Upload Security: size limit, injection prevention, scanning | ACCEPTED |

---

*Dokument: Fully Featured AI Agent Team, 2026-04-03.*
*Architektura: Mariusz Gil + Marek [senior-dev].*
