# ADR-021: Import Flow UX — Multi-Broker Wizard

- **Status:** Proposed
- **Data:** 2026-04-02
- **Kontekst:** User ma pliki z wielu brokerów. Obecny flow: wrzuć plik → auto-detect → wynik. Brak multi-file, brak wyboru brokera, brak potwierdzenia.

## Decyzja

### Flow: Multi-Broker Import Wizard

```
Krok 1: "Dodaj pliki z brokerów"
  ┌─────────────────────────────────────────────────┐
  │ Broker: [XTB ▼]  Plik: [Wybierz plik 📎]       │
  │                                                   │
  │ ✅ Rozpoznano 142 transakcje (2025-01-15 — 12-28)│
  │ Kupno: 89 | Sprzedaż: 42 | Dywidendy: 11        │
  └─────────────────────────────────────────────────┘
  [+ Dodaj kolejny broker]

Krok 2: (opcjonalny) kolejny broker
  ┌─────────────────────────────────────────────────┐
  │ Broker: [Degiro ▼]  Plik: [Wybierz plik 📎]    │
  │                                                   │
  │ ✅ Rozpoznano 89 transakcji (2025-02-01 — 11-30) │
  └─────────────────────────────────────────────────┘
  [+ Dodaj kolejny broker] | [Zakończ i oblicz →]

Krok 3: Podsumowanie
  2 brokery | 231 transakcji | 2025-01-15 — 2025-12-28
  [Oblicz podatek →]
```

### Zasady

1. **User ZAWSZE wybiera brokera** — nie zgadujemy. Dropdown z listą wspieranych brokerów + "Inny / Nie wiem" (wtedy auto-detect).
2. **Walidacja plik vs broker** — jeśli user wybrał XTB ale wrzucił plik Degiro, adapter XTB nie rozpozna → jasny error: "Ten plik nie wygląda na raport XTB. Sprawdź czy wybrałeś właściwego brokera."
3. **Multi-file w jednej sesji** — user może dodać pliki z wielu brokerów bez opuszczania strony.
4. **Natychmiastowy feedback** — po wrzuceniu pliku, natychmiast parsuj i pokaż podsumowanie (ile transakcji, okres, typy).
5. **"Następny?" flow** — po każdym pliku: "Dodaj kolejny broker" lub "Zakończ import".
6. **Sesja importu** — wszystkie pliki z jednej sesji traktowane jako jeden import. Dedup działa cross-file.

### Dropdown brokerów

```
Interactive Brokers — Activity Statement (CSV)
Degiro — Transactions (CSV)
Degiro — Account Statement (CSV) [dywidendy]
Revolut — Stocks Statement (CSV)
Bossa — Historia transakcji (CSV)
XTB — Historia transakcji (CSV) [wkrótce]
mBank eMakler — Raport (CSV) [wkrótce]
───────────────
Inny / Nie wiem — auto-detect
```

### Techniczne implikacje

- ImportController: accept `broker_id` parameter w POST
- AdapterRegistry: `findByBrokerId(string $brokerId)` zamiast `detect(string $content)`
- Auto-detect jako fallback gdy `broker_id === 'auto'`
- Session storage: trzymaj parsed results z wielu plików, merge przed obliczeniem
- Stimulus controller: dynamic "dodaj kolejny" without page reload

## Konsekwencje

- Eliminuje false positive w auto-detect
- User czuje kontrolę ("ja wybieram, nie system zgaduje")
- Multi-broker import w jednej sesji
- Wymaga Stimulus controller dla dynamic UI
- Auto-detect zostaje jako fallback (nie usuwamy)
