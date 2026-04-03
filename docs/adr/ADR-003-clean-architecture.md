# ADR-003: Clean Architecture z Dependency Rule

## Status
ACCEPTED

## Data
2026-04-03

## Kontekst

Tax Calculation Engine to core domain — złożone reguły podatkowe, FIFO matching, przeliczenia walutowe. Musi być:
- Testowalny bez frameworka (PHPUnit, bez Symfony kernel)
- Niezależny od persystencji (Doctrine to detal implementacyjny)
- Niezależny od delivery mechanism (HTTP, CLI, queue — to adaptery)
- Łatwy do zrozumienia dla nowego developera i domain experta (doradcy podatkowego)

## Decyzja

**Clean Architecture (Robert C. Martin) + Hexagonal Architecture (Alistair Cockburn)**

Trzy warstwy z jedną Dependency Rule: zależności TYLKO do wewnątrz.

```
Infrastructure → Application → Domain
                                  ↑
                    NIGDY w drugą stronę
```

### Domain Layer (`src/{Module}/Domain/`)

Serce systemu. Czyste PHP — zero zależności od Symfony, Doctrine, HTTP.

Zawiera:
- **Entities** — obiekty z tożsamością (TaxPositionLedger)
- **Value Objects** — immutable, equality by value (Money, ISIN, NBPRate, TaxYear)
- **Aggregates** — granice spójności (TaxPositionLedger jest aggregate root)
- **Domain Services** — logika która nie pasuje do jednego agregatu (DividendTaxService)
- **Policies** — enkapsulowane reguły biznesowe (LossCarryForwardPolicy, CryptoSeparationPolicy)
- **Domain Events** — fakty (TaxCalculated, FIFOMatchCompleted)
- **Repository Interfaces** — porty persystencji (TaxPositionLedgerRepositoryInterface)
- **Exceptions** — domenowe wyjątki (InsufficientSharesException)

NIE zawiera:
- `use Doctrine\...` — nigdy
- `use Symfony\...` — nigdy
- Annotacje/atrybuty ORM — nigdy
- HTTP concepts (Request, Response) — nigdy

### Application Layer (`src/{Module}/Application/`)

Orkiestracja. Woła domenę, koordynuje.

Zawiera:
- **Commands + Handlers** — write operations (CalculateAnnualTax → Handler)
- **Queries + Handlers** — read operations (GetTaxSummary → Handler)
- **DTOs** — data transfer objects (TaxSummaryDTO)
- **Port Interfaces** — zewnętrzne zależności (NBPRateProviderInterface, BrokerAdapterInterface)
- **Application Services** — orkiestracja cross-aggregate (opcjonalnie)

Handler NIE zawiera logiki biznesowej — woła serwis domenowy lub metodę na agregacie.

### Infrastructure Layer (`src/{Module}/Infrastructure/`)

Adaptery — implementacje portów.

Zawiera:
- **Doctrine Repositories** — implementują interfaces z Domain
- **Doctrine Mappings** — XML, osobno od Domain entities
- **Controllers** — Symfony HTTP controllers
- **External API Clients** — NBP API, Stripe
- **Broker Adapters** — CSV parsery (IBKR, Degiro, XTB)
- **CLI Commands** — Symfony Console commands
- **Twig Templates** — widoki

### Enforced by tooling

```yaml
# deptrac.yaml
deptrac:
  layers:
    - name: Domain
      collectors:
        - type: directory
          value: src/.*/Domain/.*
    - name: Application
      collectors:
        - type: directory
          value: src/.*/Application/.*
    - name: Infrastructure
      collectors:
        - type: directory
          value: src/.*/Infrastructure/.*
  ruleset:
    Domain: []  # Domain depends on NOTHING
    Application:
      - Domain
    Infrastructure:
      - Application
      - Domain
```

## Konsekwencje

### Pozytywne
- Domain testowalne w izolacji — `new TaxPositionLedger(...)`, zero mockowania Doctrine/Symfony
- Podmiana persystencji (Postgres → in-memory, Postgres → Mongo) bez zmiany domeny
- Domain entities są dokumentacją — doradca podatkowy może czytać `LossCarryForwardPolicy.php`
- Nowy developer czyta Domain layer i rozumie reguły biznesowe bez znajomości frameworka

### Negatywne
- Więcej kodu (interface + implementacja zamiast samej implementacji)
- Doctrine XML mapping jest verbose — ale za to domain jest czysta
- Trzeba pilnować granic — Deptrac w CI jest konieczny

### Wyjątki
- **Query Handlers** mogą czytać bezpośrednio z bazy (Doctrine DBAL Connection) — to jest read side CQRS, nie musi przechodzić przez domain model
- **Shared Kernel** (`src/Shared/Domain/`) — Value Objects współdzielone między modułami (Money, UserId, ISIN)

## Uczestnicy decyzji

| Osoba | Stanowisko |
|---|---|
| Mariusz Gil | "Domain layer musi być czysta. Jeśli widzę `use Doctrine` w Domain — code review odrzucony." |
| Marek [senior-dev] | "Deptrac w CI pipeline. Złamanie Dependency Rule = build failed." |
| Kasia [QA] | "Testy domain logic bez Symfony kernel boot = testy w milisekundach, nie sekundach." |
