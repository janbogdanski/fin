# ADR-019: Broker Adapter Versioning & Maintenance Strategy

## Status
ACCEPTED

## Data
2026-04-03

## Kontekst

Brokerzy zmieniają formaty CSV. Degiro zmienił w 2024. XTB zmienił nazwy kolumn w 2023. IBKR dodaje nowe sekcje. To nie jest "jeśli" — to jest "kiedy".

Pytania:
1. Skąd wiemy że broker zmienił format?
2. Co z danymi zaimportowanymi starym formatem?
3. Co gdy user ma plik w starym formacie ale wgrywa po migracji?
4. Jak utrzymujemy 5+ adapterów bez wypalenia?

### Dyskusja zespołu

> **Ania [data]:** "Każdy adapter to osobna klasa. Ale maintenance 5 adapterów × 2 wersje = 10 klas. Kto to utrzymuje?"
>
> **Mariusz Gil:** "Adapter to ACL — Anti-Corruption Layer. Z definicji chroni naszą domenę przed zmianami zewnętrznymi. Wersjonowanie jest w DNA tego patternu."
>
> **Kasia [QA]:** "Skąd wiemy że Degiro zmienił format? Bo user wgrywa plik i parser zwraca 100% errors. To jest za późno."
>
> **Sylwester [SRE]:** "Monitoring. Jeśli error rate na imporcie rośnie — alert. Ale to reaktywne, nie proaktywne."
>
> **Tomasz [DP]:** "Moi klienci wgrywają pliki z różnych lat. Ktoś w 2027 wgrywa plik z 2024 — stary format. I plik z 2026 — nowy format. System musi obsłużyć oba."

## Decyzja

### 1. Versioned Adapters — multi-version per broker

```
src/BrokerImport/Infrastructure/Adapter/
├── IBKR/
│   ├── IBKRActivityV1Adapter.php     # format do 2024
│   └── IBKRActivityV2Adapter.php     # format od 2025+
├── Degiro/
│   ├── DegiroV2023Adapter.php        # format 2023
│   └── DegiroV2024Adapter.php        # format 2024+ (zmienione kolumny)
├── XTB/
│   └── XTBHistoryAdapter.php         # current
├── Bossa/
│   └── BossaHistoryAdapter.php       # current
├── Revolut/
│   └── RevolutStocksAdapter.php      # current
└── AdapterRegistry.php               # auto-detect + dispatch
```

### 2. Auto-Detection — AdapterRegistry

```php
final readonly class AdapterRegistry
{
    /** @param iterable<BrokerAdapterInterface> $adapters */
    public function __construct(
        private iterable $adapters,
    ) {}

    /**
     * Próbuje każdy adapter po kolei.
     * Pierwszy który zwraca supports() = true wygrywa.
     * Kolejność: newest version first.
     */
    public function detect(string $csvContent, string $filename): BrokerAdapterInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($csvContent, $filename)) {
                return $adapter;
            }
        }

        throw new UnsupportedBrokerFormatException($filename);
    }
}
```

Każdy adapter implementuje `supports()` — sprawdza headerów/strukturę, nie nazwę pliku. Dzięki temu:
- Stary plik Degiro 2023 → `DegiroV2023Adapter` (rozpoznaje stare nagłówki)
- Nowy plik Degiro 2024 → `DegiroV2024Adapter` (rozpoznaje nowe nagłówki)
- User nie musi wybierać wersji — system sam wykrywa

### 3. Detection Strategy per Broker

| Broker | Jak `supports()` rozpoznaje format | Wersja detection |
|---|---|---|
| IBKR | Sekcja "Statement,Header" + "Interactive Brokers" | Wersja: check czy ma kolumnę "ISIN" (v2) czy nie (v1) |
| Degiro | Header row z "Datum,Tijd,Product,ISIN" (NL) lub "Date,Time,Product,ISIN" (EN) | Wersja: nazwy kolumn (zmienione w 2024) |
| XTB | Header z "Symbol,Type,Open time,Close time" | Wersja: "Transaction type" vs "Type" (zmiana 2023) |
| Bossa | "Data operacji,Instrument,Strona,Ilość" | Wersja: per nagłówki |
| Revolut | "Date,Ticker,Type,Quantity,Price per share" | Wersja: per nagłówki (Revolut zmienia często) |

### 4. Skąd wiemy że broker zmienił format?

**Proaktywne:**

| Metoda | Jak | Kto/kiedy |
|---|---|---|
| **Sample file monitoring** | Utrzymujemy 1 sample CSV per broker per wersja w `tests/Fixtures/`. Co kwartał: pobierz świeży export z konta demo brokera i porównaj headerami. | Ania [data], co kwartał |
| **Community reporting** | In-app przycisk "Format nie działa? Zgłoś" + upload anonimizowanego pliku | Users, ad-hoc |
| **Changelog monitoring** | RSS/newsletter od brokerów (IBKR release notes, Degiro blog) | Bartek [devops], automation |

**Reaktywne:**

| Metoda | Trigger | Akcja |
|---|---|---|
| **Import error rate spike** | >20% error rate na import w ostatniej godzinie | Alert Sentry → Ania sprawdza |
| **Unrecognized format alert** | `UnsupportedBrokerFormatException` > 5/dzień | Alert → priorytet P1 |
| **Parser error pattern** | >50% wierszy z "unknown column" | Prawdopodobnie nowy format → nowy adapter |

### 5. Jak tworzymy nowy adapter?

Workflow:

```
1. Alert: "Degiro format nie działa"
2. User (lub community) uploaduje sample nowego formatu
3. Ania tworzy nowy adapter (DegiroV2025Adapter)
4. Test: fixture + golden dataset
5. Deploy: nowy adapter obok starego
6. AdapterRegistry: nowy adapter ma priorytet (ordered by version DESC)
7. Stare pliki nadal działają (stary adapter nadal istnieje)
```

**Czas reakcji:** cel < 48h od zgłoszenia do deploy nowego adaptera.

### 6. Stary format + nowy format = oba działają

```
User A: wgrywa plik Degiro z 2023 → DegiroV2023Adapter → ✅
User B: wgrywa plik Degiro z 2025 → DegiroV2025Adapter → ✅
User C: wgrywa oba pliki (historia) → każdy plik auto-detected osobno → ✅
```

**Reguła: NIGDY nie usuwamy starego adaptera.** Stare pliki muszą działać zawsze. Adapter jest readonly — po napisaniu i przetestowaniu, nie modyfikujemy go.

### 7. NormalizedTransaction = stabilne API

Adaptery są niestabilne (broker zmienia format). Ale output jest stabilny — `NormalizedTransaction` DTO się nie zmienia. Dlatego:

```
[CSV v1] → [AdapterV1] ─┐
                         ├─→ NormalizedTransaction → TaxCalc Domain
[CSV v2] → [AdapterV2] ─┘
```

Domain NIE wie o formacie CSV. Adapter to tłumacz. Zmiana adaptera = zero zmian w domain.

### 8. Test strategy per adapter

Każdy adapter ma:
- **Sample fixture** — `tests/Fixtures/{broker}_{version}_sample.csv`
- **Unit test** — parsuje fixture, weryfikuje NormalizedTransactions
- **Regression test** — stary fixture nadal parsuje poprawnie po dodaniu nowej wersji
- **Canary test** — opcjonalnie: live download z konta demo brokera (CI nightly)

### 9. Community-driven adapters (v3+)

Jeśli userbase urośnie:
- Open-source adaptery (osobne repo/package?)
- Community PR-y dla nowych brokerów
- Adapter SDK: `BrokerAdapterInterface` + tooling do testowania

Ale na v1: 5 adapterów utrzymywanych centralnie.

## Scope v1 — zaktualizowany

| Broker | Priorytet | Status | Uwagi |
|---|---|---|---|
| IBKR | **MUST** | ✅ DONE | Sekcyjny CSV, trades + divs + WHT |
| Degiro | **MUST** | TODO | Flat CSV, NL/EN variants |
| XTB | **MUST** | TODO | Polski broker, popularny |
| Bossa | **MUST** | TODO | Polski broker, GPW-focused |
| Revolut | **MUST** | TODO | Stocks + krypto, rosnąca popularność w PL |

v2:
| Trading212 | SHOULD |
| eToro | SHOULD |
| mBank eMakler | COULD |
| LYNX | COULD (reseller IBKR — zweryfikować format) |

## Konsekwencje

### Pozytywne
- Multi-version: stare i nowe pliki działają równocześnie
- Auto-detection: user nie wybiera wersji ręcznie
- Adapter nigdy nie usuwany: backward compatibility permanentna
- NormalizedTransaction jako stable API: domain izolowana od zmian brokerów
- Monitoring: proaktywne + reaktywne wykrywanie zmian

### Negatywne
- Więcej klas (adapter per version per broker) — mitigacja: każdy jest mały (~100-200 linii)
- Sample file maintenance co kwartał — mitigacja: zautomatyzować gdzie możliwe
- Konto demo u każdego brokera — mitigacja: community reporting jako backup

## Uczestnicy decyzji

| Osoba | Stanowisko |
|---|---|
| Mariusz Gil | "Adapter to ACL. Wersjonowanie jest w DNA patternu. Nigdy nie usuwaj starego adaptera." |
| Ania [data] | "5 adapterów × 2 wersje to manageable. Każdy to 100-200 linii. Fixture + test = 1 dzień pracy." |
| Kasia [QA] | "Regression testy na starych fixtures. Dodanie nowej wersji nie może złamać starej." |
| Sylwester [SRE] | "Import error rate monitoring. Alert na >20% errors. < 48h do fix." |
