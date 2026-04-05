# Stripe — Data Processing Agreement (DPA)

## Status

**TODO: Uzupełnić przed uruchomieniem produkcyjnym.**

Stripe jako podmiot przetwarzający dane osobowe (procesor) w rozumieniu art. 28 RODO wymaga zawarcia umowy powierzenia przetwarzania danych (Data Processing Agreement).

## Co należy zrobić

1. **Zalogować się do Stripe Dashboard** → Settings → Privacy → Data Processing Agreement
2. **Podpisać DPA** (dostępne online w Stripe Dashboard — nie wymaga osobnej negocjacji)
3. **Pobrać podpisane DPA** i zachować w aktach (wymagane przez art. 28 ust. 3 RODO)
4. **Zaktualizować Politykę Prywatności** o wzmiankę o Stripe jako podmiocie przetwarzającym

## Dlaczego to ważne

- Art. 28 RODO: administrator danych (operator TaxPilot) musi zawrzeć umowę powierzenia z każdym procesorem przetwarzającym dane osobowe użytkowników
- Stripe przetwarza: adres e-mail (przy płatności), dane karty (tokenizowane po stronie Stripe), adres IP
- Brak DPA = naruszenie RODO nawet jeśli dane faktycznie nie wyciekają

## Dane przetwarzane przez Stripe

| Dane | Podstawa | Cel |
|------|----------|-----|
| Adres e-mail | Art. 6 ust. 1 lit. b | Potwierdzenie płatności |
| Dane karty | Stripe jako PCI-DSS Level 1 | Tokenizacja — nie są przechowywane przez TaxPilot |
| Adres IP | Art. 6 ust. 1 lit. f | Wykrywanie oszustw przez Stripe |

## Linki

- Stripe DPA: https://stripe.com/legal/dpa
- Stripe Privacy Policy: https://stripe.com/privacy
- Stripe Sub-processors: https://stripe.com/legal/service-privacy

## Checklist

- [ ] DPA podpisane w Stripe Dashboard
- [ ] Kopia DPA w aktach (data: YYYY-MM-DD)
- [ ] Polityka Prywatności zaktualizowana
- [ ] Stripe wymieniony w wykazie podmiotów przetwarzających
