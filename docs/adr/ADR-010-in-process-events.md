# ADR-010: In-Process Events (Messenger sync), Async dla importu

## Status
ACCEPTED

## Data
2026-04-03

## Kontekst

Bounded Contexts komunikują się eventami:
- `TransactionsImported` (BrokerImport → TaxCalc)
- `TaxCalculated` (TaxCalc → Declaration, Audit)
- `PaymentProcessed` (Billing → Identity)

Pytanie: synchronicznie (in-process) czy asynchronicznie (queue)?

### Rozważane:
1. **Wszystko async (Redis/RabbitMQ)** — loosely coupled, ale: eventual consistency, complexity
2. **Wszystko sync (in-process)** — proste, transakcyjne, ale: blocking, tight coupling
3. **Hybrid** — domyślnie sync, ciężkie operacje async

## Decyzja

**Hybrid: domyślnie synchroniczne, async tylko dla ciężkich operacji.**

### Synchroniczne (default)

```yaml
# Domyślnie events są sync — ten sam request/transaction
framework:
    messenger:
        routing:
            # Brak routingu = sync (handled in same process)
```

`TransactionsImported` → handler w TaxCalc przelicza podatek → w tej samej transakcji DB.

**Dlaczego sync domyślnie:**
- Użytkownik wgrywa CSV i chce NATYCHMIAST widzieć wynik
- Transakcyjność: import + obliczenie w jednej DB transaction — albo oba się udają, albo rollback
- Debugging: jeden request, jeden stack trace
- Nie ma potrzeby eventual consistency — to nie jest system rozproszony

### Asynchroniczne (opt-in)

```yaml
framework:
    messenger:
        routing:
            # Tylko te commands idą przez Redis queue
            'App\BrokerImport\Application\Command\ImportLargeCSV': async
            'App\TaxCalc\Application\Command\PreComputeTax': async
            'App\Declaration\Application\Command\GeneratePDF': async
```

**Kiedy async:**
- Import dużego pliku CSV (>1000 wierszy) — nie blokuj HTTP response
- Pre-compute tax after import (background recalculation)
- PDF generation (CPU-heavy rendering)
- Email sending (SES)

### Worker

```yaml
# docker-compose.yml
messenger-worker:
    command: php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
```

Osobny container/task w ECS — worker konsumuje z Redis queue.

### Retry policy

```yaml
framework:
    messenger:
        transports:
            async:
                dsn: 'redis://redis:6379/messages'
                retry_strategy:
                    max_retries: 3
                    delay: 1000      # 1s
                    multiplier: 3    # 1s, 3s, 9s
                    max_delay: 30000 # max 30s

        failure_transport: failed  # dead letter queue
```

Failed messages → `failed` transport → manual review / retry.

### Ścieżka migracji do full async

Jeśli w przyszłości moduły muszą być niezależne:
1. Zmień transport z `sync://` na `redis://` w messenger.yaml
2. Handler nadal działa — zmienia się transport, nie kod
3. Dodaj idempotency key (jeśli at-least-once delivery)

**Zero zmian w Domain ani Application layer.**

## Konsekwencje

### Pozytywne
- Prostota: większość operacji sync = jeden request, jedna transakcja, jeden error
- Async opt-in: ciężkie operacje nie blokują UI
- Ścieżka migracji: zmiana transport config, nie kodu
- Messenger abstrahuje transport — handler nie wie czy sync czy async

### Negatywne
- Sync events = coupling temporalny (caller czeka na handlera)
- Sync events = failure w handlerze rollbackuje callera (ale: to jest feature, nie bug — atomicity)
- Trzeba monitorować worker (health check, restart policy)

## Uczestnicy decyzji

| Osoba | Stanowisko |
|---|---|
| Mariusz Gil | "Sync domyślnie. Async gdy jest powód. Nie odwrotnie." |
| Marek [senior-dev] | "Zmiana sync→async = jedna linia w YAML. Zero zmian w handlerach." |
| Sylwester [SRE] | "Worker w osobnym ECS task. Health check na messenger:stats. Restart on failure." |
