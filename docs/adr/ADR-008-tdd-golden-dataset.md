# ADR-008: TDD + Golden Dataset + Property-Based Tests + Mutation Testing

## Status
ACCEPTED

## Data
2026-04-03

## Kontekst

Obliczenia podatkowe MUSZĄ być poprawne co do grosza. Błąd = kara z US dla użytkownika + roszczenie cywilne wobec nas.

Standardowe unit testy nie wystarczą:
- Developerzy piszą testy pod swoją implementację (confirmation bias)
- Happy path coverage = fałszywe poczucie bezpieczeństwa
- Edge cases w prawie podatkowym są nieintuicyjne (cross-broker FIFO, split, spin-off)

Potrzebujemy:
1. **External source of truth** — niezależne od kodu
2. **Exhaustive edge case coverage**
3. **Gwarancja że testy naprawdę testują** (nie są pozorowane)

## Decyzja

### 1. TDD (Test-Driven Development)

Red → Green → Refactor. Bez wyjątków w Domain i Application layer.

- Test PRZED implementacją
- Test definiuje oczekiwane zachowanie
- Implementacja sprawia że test przechodzi
- Refactor bez zmiany zachowania

### 2. Golden Dataset Tests

20 anonimizowanych zestawów testowych od **Tomasza Kędzierskiego** (doradca podatkowy, 300+ klientów).

Każdy zestaw zawiera:
- Plik CSV z transakcjami (w formacie brokera)
- Oczekiwany wynik: FIFO matching, kwoty w PLN, podatek
- Oczekiwany PIT-38 (sekcje C i D)
- Komentarz: jaki edge case testuje

**To jest nasz north star.** Jeśli system daje inny wynik niż Tomasz — my mamy bug, nie Tomasz.

Scenariusze w golden dataset:
1. Proste buy-sell jednej akcji w PLN
2. Buy-sell akcji zagranicznej z przeliczeniem NBP
3. Multi-broker FIFO (IBKR buy + Degiro sell)
4. Partial sell (sprzedaż części pakietu)
5. Dywidendy z USA (15% WHT, W-8BEN)
6. Dywidendy z UK (10% WHT)
7. Dywidendy z 5 krajów + PIT/ZG
8. Straty z lat poprzednich (50% limit)
9. Strata z jednego instrumentu + zysk z drugiego
10. ETF zagraniczny (VWCE na Xetra)
11. Wiele transakcji na jednym instrumencie (30 buy, 10 sell)
12. Zero transakcji sprzedaży (tylko buy — brak PIT-38)
13. Short selling
14. Prowizja w innej walucie niż transakcja
15. Transakcja w piątek wieczorem (timezone/kurs NBP)
16. Split akcji (nie jest zdarzeniem podatkowym)
17. Rok z czystą stratą (podatek = 0, ale trzeba złożyć PIT-38)
18. Dywidenda ze spółki polskiej (nie trafia na PIT-38)
19. Mix: akcje + ETF + dywidendy w jednym roku
20. Stress test: 5000 transakcji, 15 instrumentów, 3 brokerzy

### 3. Property-Based Tests

Invarianty FIFO — muszą być ZAWSZE prawdziwe dla DOWOLNYCH danych:

- `total_sold_quantity ≤ total_bought_quantity`
- `sell matches oldest available buy` (FIFO order)
- `gain_loss = proceeds - cost_basis - commissions` (exact, to the penny)
- `no transaction counted twice`
- `sum of closed quantities = sell quantity` (no loss of shares)
- `crypto gains never mixed with equity gains` (separate basket)

Narzędzie: phpunit-random-data-generator lub custom generators.

### 4. Mutation Testing (Infection)

Mutation testing zmienia kod (np. `>` na `>=`, `+` na `-`) i sprawdza czy testy to łapią.

- Minimum **MSI (Mutation Score Indicator) > 80%** dla Domain layer
- Minimum **MSI > 90%** dla `TaxPositionLedger` i `Money`
- Infection w CI pipeline — jeśli MSI spadnie poniżej threshold = build failed

### Piramida testów

```
          ┌───────────────────────┐
          │ Golden Dataset (E2E)   │  ← 20 zestawów, CSV → PIT-38
          │ Snapshot tests         │  ← expected output zamrożony
          └───────────┬───────────┘
                      │
          ┌───────────┴───────────┐
          │ Integration Tests      │  ← moduł ↔ moduł, real DB
          │ Testcontainers (PG)    │  ← docker PostgreSQL w teście
          └───────────┬───────────┘
                      │
    ┌─────────────────┼─────────────────┐
    │                 │                 │
┌───┴────┐    ┌───────┴───────┐   ┌────┴───────┐
│ Unit   │    │ Property-Based│   │ Adapter    │
│ Tests  │    │ Tests         │   │ Tests      │
│        │    │               │   │            │
│ Money  │    │ FIFO inv.     │   │ IBKR CSV   │
│ FIFO   │    │ Money inv.    │   │ Degiro CSV │
│ Policy │    │ Tax inv.      │   │ XTB CSV    │
└────────┘    └───────────────┘   └────────────┘
```

### CI pipeline

```yaml
# .github/workflows/test.yml
jobs:
  test:
    steps:
      - run: make test-unit           # < 10 sec
      - run: make test-property       # < 30 sec
      - run: make test-adapter        # < 10 sec
      - run: make test-integration    # < 60 sec (testcontainers)
      - run: make test-golden         # < 30 sec
      - run: make infection           # < 120 sec (mutation testing)
```

## Konsekwencje

### Pozytywne
- Golden dataset = niezależny source of truth (doradca podatkowy, nie developer)
- Property-based tests łapią edge cases których developer nie przewidział
- Mutation testing gwarantuje że testy naprawdę testują (nie są pozorowane)
- TDD = testy są specyfikacją, nie afterthought

### Negatywne
- Wolniejszy development (test first) — mitigacja: szybsze w długim terminie
- Golden dataset wymaga współpracy z doradcą podatkowym — mitigacja: Tomasz jest zaangażowany
- Mutation testing jest wolne — mitigacja: tylko Domain layer, w CI (nie local)
- 20 zestawów to dużo pracy do przygotowania — mitigacja: Tomasz przygotowuje je raz

## Uczestnicy decyzji

| Osoba | Stanowisko |
|---|---|
| Mariusz Gil | "TDD od dnia zero w Tax Calculation Engine. Każda reguła podatkowa = test." |
| Kasia [QA] | "Golden dataset to nasz north star. Tomasz mówi '1 926.98' — to jest prawda." |
| Michał W. [QA] | "Infection MSI > 80%. Jeśli ktoś zmieni `>` na `>=` i testy nadal przechodzą — mamy problem." |
| Tomasz [DP] | "Dam wam 20 zestawów. Z komentarzami. Z pułapkami." |
