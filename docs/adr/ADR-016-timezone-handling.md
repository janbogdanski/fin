# ADR-016: Timezone Handling

## Status
ACCEPTED

## Data
2026-04-03

## Kontekst

HS-005 z Event Storming: transakcja w NYC o 23:00 piątek = sobota w Polsce. Który kurs NBP?

## Decyzja

### Reguły

1. **Storage:** wszystkie daty transakcji przechowywane jako `DATE` (bez czasu) w UTC
2. **Import:** jeśli broker podaje datetime → konwertuj na datę w timezone brokera, potem zapisz jako DATE
3. **NBP rate lookup:** "ostatni dzień roboczy PRZED datą transakcji" — w polskiej strefie czasowej (CET/CEST)
4. **Dzień roboczy:** poniedziałek-piątek, minus polskie święta państwowe
5. **Konfiguracja timezone per broker:**

| Broker | Timezone | Uwagi |
|---|---|---|
| XTB | Europe/Warsaw | Polski broker |
| mBank eMakler | Europe/Warsaw | Polski broker |
| IBKR | Exchange timezone (per instrument) | Flex Query zawiera exchange info |
| Degiro | Europe/Amsterdam (CET) | Holenderski broker |
| Trading212 | Europe/London (GMT/BST) | Brytyjski broker |

6. **Fallback:** jeśli broker podaje tylko datę (brak timezone) → przyjmij datę as-is
7. **Edge case weekendowy:** transakcja w piątek 23:00 NYC = sobota 05:00 PL → data transakcji = piątek (timezone brokera), NBP rate = czwartek (ostatni dzień roboczy PRZED piątkiem)

### Golden dataset scenariusze
- Piątek wieczór US → kurs z czwartku
- Poniedziałek rano Asia → kurs z piątku
- 31 grudnia (sylwester) → kurs z 30.12 lub wcześniej
- 2 stycznia (po Nowym Roku) → kurs z 31.12 lub wcześniej
