# ADR-004: CQRS via Symfony Messenger (bez Event Sourcing)

## Status
ACCEPTED

## Data
2026-04-03

## Kontekst

Obliczenia podatkowe (write) mają inną strukturę niż wyświetlanie wyników (read):
- **Write**: RegisterTransaction → FIFO matching → oblicz zysk/stratę → zapisz
- **Read**: Pokaż podsumowanie roczne, drill-down per transakcja, podgląd PIT-38

Write wymaga domain modelu (TaxPositionLedger z FIFO queue). Read wymaga flat, denormalizowanych danych (tabele, sumy, breakdown). Próba obsługi obu przez ten sam model = compromises w obu kierunkach.

### Rozważane:
1. **CQRS + Event Sourcing** — pełne odtwarzanie stanu z eventów
2. **CQRS bez Event Sourcing** — osobne command/query handlers, state-based persistence
3. **Brak CQRS** — jeden model read/write

## Decyzja

**CQRS bez Event Sourcing** via Symfony Messenger.

### Dlaczego nie Event Sourcing?
- Za duży overhead dla MVP — projekcje, snapshoty, eventual consistency
- Stan obliczenia podatkowego jest deterministyczny (te same transakcje = ten sam wynik) — nie potrzebujemy historii zmian stanu
- Audit trail robimy osobno (audit log table) — prostsze niż event store
- Team nie ma doświadczenia z ES — learning curve w MVP to ryzyko

### Implementacja

**Dwa busy w Symfony Messenger:**

```yaml
framework:
    messenger:
        default_bus: command.bus
        buses:
            command.bus:
                middleware:
                    - doctrine_transaction
                    - validation
            query.bus:
                middleware:
                    - validation
```

**Command** = intencja zmiany stanu (imperative mood):
```php
final readonly class CalculateAnnualTax {
    public function __construct(
        public UserId $userId,
        public TaxYear $taxYear,
    ) {}
}
```

**Query** = pytanie o stan (question):
```php
final readonly class GetTaxSummary {
    public function __construct(
        public UserId $userId,
        public TaxYear $taxYear,
    ) {}
}
```

### Command Handlers
- Ładują agregaty z repozytorium
- Wołają metody na domenowym modelu
- Zapisują przez repozytorium
- Dispatchują domain events
- **NIE zwracają wartości** (void) — command to fire-and-forget

### Query Handlers
- Mogą czytać bezpośrednio z bazy (Doctrine DBAL)
- Zwracają dedykowane DTO (nie domain entities)
- **NIE mutują stanu**
- Mogą korzystać z denormalizowanych widoków / cache

### Async Commands
Ciężkie operacje przez async transport (Redis):

```yaml
routing:
    'App\BrokerImport\Application\Command\ImportCSV': async
    'App\TaxCalc\Application\Command\PreComputeTax': async
```

Lekkie operacje synchronicznie (domyślnie).

## Konsekwencje

### Pozytywne
- Osobne modele read/write — query może być zoptymalizowany SQL, nie przechodzi przez FIFO logic
- Symfony Messenger = zero dodatkowych bibliotek
- Async transport dla ciężkich operacji (import CSV) — UI nie czeka
- Command handlers w doctrine_transaction middleware — automatyczny rollback na exception

### Negatywne
- Dwa handlery zamiast jednego (command + query) — więcej plików
- Trzeba utrzymywać spójność read modelu z write modelem (ale bez ES to jest proste — jeden zapis)
- Brak "replay" — jeśli logika się zmieni, trzeba przeliczyć, nie "odtworzyć z eventów"

## Uczestnicy decyzji

| Osoba | Stanowisko |
|---|---|
| Mariusz Gil | "CQRS bez ES to sweet spot. Messenger jest gotowy. Nie dodawajcie ES dopóki nie będzie bólu." |
| Marek [senior-dev] | "Command bus = write. Query bus = read. Proste. Handler to adapter, nie domena." |
| Aleksandra [perf] | "Query handler czyta z bazy SQL bezpośrednio — żaden ORM overhead. Fast." |
