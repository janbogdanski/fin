# ADR-013: Data Retention & GDPR

## Status
ACCEPTED

## Data
2026-04-03

## Decyzja

### Retencja danych

| Zdarzenie | Akcja | Podstawa prawna |
|---|---|---|
| User usuwa konto | Anonymizuj PII (NIP→hash, email→null, name→null). Zachowaj anonymizowane transakcje i obliczenia. | GDPR Art. 17 ust. 3 lit. b (obowiązek prawny) |
| 5 lat od końca roku podatkowego | Purge wszystkie dane (w tym anonymizowane) | Ordynacja podatkowa art. 86 §1 |
| Upload CSV po przetworzeniu | Usuń po 30 dniach | Minimalizacja danych (GDPR Art. 5(1)(c)) |
| Sesja wygasła | Usuń z Redis | — |

### GDPR compliance

- **DPIA:** zlecona jako gate condition G-006 (przed pisaniem kodu)
- **Rejestr czynności przetwarzania:** art. 30 GDPR — administrator prowadzi rejestr
- **Lawful basis:** art. 6(1)(b) — wykonanie umowy (obliczenia), art. 6(1)(c) — obowiązek prawny (retencja 5 lat)
- **Data portability (art. 20):** eksport JSON ze wszystkimi danymi użytkownika, w ciągu 30 dni
- **Right to erasure (art. 17):** anonymizacja PII, retencja finansowa pod wyjątkiem art. 17(3)(b)
- **Breach notification:** UODO w 72h, użytkownicy "bez zbędnej zwłoki" (art. 33, 34)
