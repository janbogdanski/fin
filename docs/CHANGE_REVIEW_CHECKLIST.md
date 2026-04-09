# Change Review Checklist

## Kiedy używać

Używaj przed każdym pushem dla każdej zmiany większej niż trywialna korekta tekstu.

## Wejście do review

- link do diffu albo `git show`
- cel zmiany w 1-2 zdaniach
- lista dotkniętych bounded contextów
- wynik `make ci`

## Checklist

- Czy zmiana rozwiązuje jeden spójny problem, a nie kilka naraz?
- Czy granice BC, warstw i portów pozostały czytelne?
- Czy logika biznesowa nie wycieka do infrastruktury lub kontrolera?
- Czy nowy kod jest prostszy niż alternatywne rozwiązania?
- Czy są testy procesu dla nowego zachowania lub regresji?
- Czy scenariusze błędne i edge case'y są objęte testem albo jawnie opisane?
- Czy zmiana nie psuje idempotencji, replayu albo importu wieloletniego?
- Czy output dla użytkownika jest spójny, czytelny i audytowalny?
- Czy obserwowalność lub logowanie nie pogorszyły się?
- Czy nazwy klas, metod i DTO są zgodne z językiem domeny?

## Format reviewera

```text
Findings
- [P1] Krótki tytuł
  - File: /abs/path/to/file.php:123
  - Risk: wpływ na zachowanie / utrzymanie / operacje
  - Why: konkret z kodu
  - Fix: najmniejsza bezpieczna poprawka

Open Questions
- brakująca reguła biznesowa albo założenie wdrożeniowe

Change Summary
- 1-3 krótkie punkty
```

## Severity

- `P0` blokuje merge i release
- `P1` musi być naprawione przed commitem lub push
- `P2` może wejść z backlog itemem i ownerem
- `P3` to polish albo sugestia

