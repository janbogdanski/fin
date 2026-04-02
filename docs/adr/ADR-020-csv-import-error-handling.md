# ADR-020: CSV Import Error Handling Strategy

- **Status:** Accepted
- **Data:** 2026-04-02
- **Kontekst:** Brokerzy zmieniają formaty CSV bez ostrzeżenia. Użytkownicy mogą edytować pliki lub wgrywać niewłaściwe raporty. Potrzebujemy jasnej strategii obsługi tych scenariuszy.

## Decyzja

### Odpowiedzialność systemu (implementujemy):

1. **Nowe kolumny w CSV** — adapter ignoruje nieznane kolumny, parsuje znane. Warning w ParseResult.
2. **Zmienione nazwy kolumn** — nowa wersja adaptera (per ADR-019). Stara wersja NIGDY nie jest usuwana. Auto-detect (`supports()` + priority) wybiera pasującą.
3. **Brakujące WYMAGANE kolumny (date, quantity, price, amount)** — cały import odrzucony z error "Missing required column: X. This broker format may have changed — contact support." Partial parse z brakującymi kwotami jest NIEBEZPIECZNY (cichy zerowy commission → błędny podatek).
3b. **Brakujące OPCJONALNE kolumny (commission, ISIN, notes)** — warning + explicit domyślna wartość. Np. commission=0 z ostrzeżeniem: "Commission column missing — defaulting to 0. Verify manually." User MUSI widzieć ten warning.
4. **Nowy format (CSV → XLSX)** — nowy adapter. Stary zostaje.
5. **Encoding (BOM, Windows-1250)** — system obsługuje automatycznie (CsvSanitizer::stripBom, iconv).
6. **Duplikaty** — SHA-256 content hash, warning "już importowany", opcja force reimport.

### Odpowiedzialność użytkownika (NIE implementujemy):

1. **Ręczna edycja CSV** — parsujemy co dostaliśmy. Brak walidacji "prawdziwości" danych.
2. **Plik z innego brokera** — auto-detect rozwiązuje. Jeśli format pasuje ale dane nie — warning, nie error.
3. **Przyszłe daty** — parsujemy. NBP API może nie mieć kursu → error per transakcja.
4. **Uszkodzony plik** — MIME + extension check na wejściu.

### Error taxonomy w ParseResult:

| Level | Znaczenie | Blokuje import? |
|---|---|---|
| `ParseError` | Transakcja nie może być przetworzona (brakujące pole, nieprawidłowy format) | Nie — reszta transakcji jest parsowana |
| `ParseWarning` | Transakcja przetworzona ale z ograniczeniami (brak ISIN, unknown ticker) | Nie |
| `UnsupportedBrokerFormatException` | Żaden adapter nie rozpoznał formatu | Tak — cały import odrzucony |

### Zasada: fail-hard na strukturze, fail-soft na danych

- **Brak wymaganej kolumny** → fail-hard (cały import odrzucony). Cichy partial parse z zerami jest gorszy niż brak importu.
- **Błąd w jednej linii** → fail-soft (reszta parsowana). Jedna zepsuta transakcja nie powinna blokować 499 poprawnych.
- **Nierozpoznany format** → fail-hard (UnsupportedBrokerFormatException).
- **Opcjonalna kolumna brakuje** → warning z explicit default. User MUSI widzieć ostrzeżenie.

## Konsekwencje

- Użytkownik widzi partial results + listę errors/warnings
- Adapter development jest backward-compatible (nigdy nie usuwamy starych wersji)
- Canary tests (P3-001) monitorują zmiany formatów
- User feedback (P3-002) zbiera edge cases z produkcji
