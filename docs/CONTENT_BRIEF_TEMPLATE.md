# Article Brief Template — TaxPilot Content Pipeline

## Jak używać

Wypełnij ten szablon przed oddaniem do content pipeline. Brief musi mieć wszystkie pola wypełnione — niekompletny brief blokuje research agenta.

Przykład wypełniony: artykuł "Jak rozliczyć XTB — poradnik PIT-38 2027" na końcu tego pliku.

---

## Szablon

```
## ARTICLE BRIEF — TaxPilot Content Pipeline

### Meta
- Tytuł roboczy: [...]
- Slug: [lowercase-bez-polskich-znaków-z-myślnikami]
- Data planowanej publikacji: [YYYY-MM-DD]
- Priorytet: pillar / cluster / supporting
- Pipeline: full / fast-track
- Autor briefu: [...] | Data: [...]

### Target keyword
- Primary keyword: [...] (volume: [...]/msc, difficulty: low/medium/high)
- Secondary keywords: [...]
- Long-tail: [...]
- Intencja: informacyjna / nawigacyjna / transakcyjna

### Persona czytelnika
- Kim jest: [opis — imię, wiek, broker, ilość transakcji/rok, co wie, co robi]
- Co wie przed przeczytaniem: [zakładana wiedza]
- Czego szuka (JTBD):
  When [sytuacja],
  I want [co chce zrozumieć lub zrobić],
  So I can [cel końcowy]
- Strach / frustracja: [co go boli w tym temacie]

### Cel artykułu
- Główny cel: [po przeczytaniu user potrafi... — konkretnie]
- CTA: [np. "zaimportuj raport XTB do TaxPilot"]
- Miejsce w lejku: TOFU / MOFU / BOFU

### Wymagane sekcje (H2)
1. [nagłówek H2] — [co musi zawierać: pułapka / przykład / tabela / kroki]
2. [...]
3. [...]
(każda sekcja: minimum 1 przykład liczbowy LUB tabela LUB lista kroków)

### Obowiązkowe dane do uwzględnienia
- Przykłady liczbowe: [ticker, ilość, cena w walucie, kurs NBP, data, wynik PLN]
- Aktualne stawki/terminy: [np. "WHT USA po W-8BEN: 15%", "termin PIT-38: 30.04.2027"]
- Pułapki specyficzne dla tematu: [...]
- Broker-specific: [np. "w XTB kolumna 'Kurs zamknięcia' to PLN — nieużyteczna, potrzebujesz..."]

### Internal links (minimum 3)
- [/blog/slug] — [kiedy linkować, w jakim kontekście]
- [...]

### Anti-patterns specyficzne dla tego artykułu
- [np. "NIE pisz że XTB automatycznie rozlicza podatek"]
- [...]

### Research do wykonania przed draftem
- [ ] [konkretna rzecz do sprawdzenia — np. "aktualna ścieżka eksportu w XTB (UI zmienia się)"]
- [ ] [...]

### Disclaimery wymagane
- [ ] Standardowy: "Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego."
- [ ] Roku podatkowego: "Artykuł dotyczy roku podatkowego [rok] (PIT-38 do 30 kwietnia [rok+1])."
- [ ] [dodatkowe specyficzne dla tematu]

### Definition of Done
- [ ] Tax review: APPROVE
- [ ] SEO review: APPROVE
- [ ] Legal review: APPROVE
- [ ] Przykłady liczbowe zweryfikowane arytmetycznie
- [ ] Placeholdery [Ilustracja: ...] zamiast <!-- Screenshot: ... -->
```

---

## Przykład wypełniony: Jak rozliczyć XTB — poradnik PIT-38 2027

```
## ARTICLE BRIEF — TaxPilot Content Pipeline

### Meta
- Tytuł roboczy: Jak rozliczyć XTB w Polsce — poradnik PIT-38 2027
- Slug: rozliczenie-xtb-pit-38
- Data planowanej publikacji: 2026-10-22
- Priorytet: cluster (broker-specific)
- Pipeline: full
- Autor briefu: content-strategist | Data: 2026-09-15

### Target keyword
- Primary keyword: jak rozliczyć XTB (volume: ~800/msc, difficulty: medium)
- Secondary keywords: xtb pit-8c, xtb pit-38, xtb historia transakcji eksport, xtb podatek
- Long-tail: "xtb nie wysłał pit-8c co robić", "xtb pit-8c gdzie pobrać"
- Intencja: informacyjna z elementem transakcyjnym

### Persona czytelnika
- Kim jest: Karolina, 29 lat. Inwestuje przez XTB od 2 lat. Konto akcyjne + IKE. 45 transakcji zagranicznych w 2026 (konto regularne). XTB wysłało PIT-8C — nie rozumie dlaczego nie uwzględnia kursów walutowych tak jak oczekuje.
- Co wie: wie co to PIT-38, wie że XTB wysyła PIT-8C, słyszała o FIFO ale nie liczyła sama
- Czego szuka (JTBD):
  When [dostała PIT-8C z XTB],
  I want [wiedzieć czy po prostu przepisać dane do PIT-38],
  So I can [złożyć deklarację bez błędu i bez nadpłacania]
- Strach: że XTB policzyło coś źle i ona to przepisze i będzie miała błąd. Albo że coś pominie.

### Cel artykułu
- Główny cel: po przeczytaniu Karolina rozumie co jest w PIT-8C z XTB, kiedy można przepisać dane bezpośrednio a kiedy weryfikować, i jak TaxPilot eliminuje wątpliwości
- CTA: "Zaimportuj PIT-8C z XTB do TaxPilot — porównaj wyniki"
- Miejsce w lejku: MOFU

### Wymagane sekcje (H2)

1. "Co zawiera PIT-8C z XTB — i czego nie zawiera"
   - Struktura PIT-8C (sekcja B — przychody, C — koszty)
   - XTB liczy FIFO prawidłowo dla konta regularnego
   - Pułapka: XTB może przeliczać PLN po kursie dnia transakcji (nie poprzedzającego) — zbadać!
   - IKE vs konto regularne: zyski z IKE NIE idą do PIT-38 (art. 21 ust. 1 pkt 58a)
   - Przykład: "W PIT-8C Karoliny z XTB: Przychód 28 540 PLN, Koszt 24 320 PLN, Dochód 4 220 PLN."

2. "Kiedy PIT-8C z XTB wystarczy — a kiedy musisz weryfikować"
   - Wystarczy: TYLKO XTB, brak innych brokerów
   - Musisz weryfikować: masz też Revolut/IBKR → cross-broker FIFO!
   - Przykład: XTB (dochód 4 220 PLN) + Revolut (12 transakcji USD) = sumujemy oba w PIT-38

3. "Jak pobrać raport z XTB krok po kroku"
   - Ścieżka UI: Raporty → Historia transakcji → eksport CSV
   - Uwaga: "Kurs zamknięcia" w XTB to cena w PLN — nieużyteczna do własnych obliczeń

4. "IKE w XTB — co idzie do PIT-38, a co nie"
   - IKE zwolnione z podatku przy wyjściu po 60 roku (art. 21 ust. 1 pkt 58a)
   - Wypłata przed terminem → podatek 19% ale inne zasady niż PIT-38
   - Przykład: IKE zysk 8 000 PLN + konto regularne 4 220 PLN → do PIT-38 idzie tylko 4 220 PLN

5. "Jak TaxPilot obsługuje PIT-8C z XTB"
   - Import PIT-8C (PDF lub CSV)
   - Jeśli jest też IBKR/Degiro — łączy w jeden PIT-38
   - Alert przy rozbieżności kursowej XTB vs NBP

6. "Najczęstsze błędy przy rozliczaniu XTB"
   - Wliczenie IKE do PIT-38
   - Pominięcie Revolut/IBKR gdy jest cross-broker FIFO
   - Brak dywidend z zagranicznych akcji w XTB

### Obowiązkowe dane do uwzględnienia
- PIT-8C konkretne kwoty: Przychód 28 540 PLN, Koszt 24 320 PLN, Dochód 4 220 PLN
- Przykład IKE vs konto regularne z liczbami
- Przykład cross-broker: XTB + Revolut, sumowanie w PIT-38
- Termin wysyłki PIT-8C przez XTB: do końca lutego 2027 (za rok 2026)
- Podstawa prawna IKE: art. 21 ust. 1 pkt 58a ustawy o PIT

### Internal links (minimum 3)
- /blog/pit-8c-vs-pit-38-roznice — przy pierwszej wzmiance PIT-8C
- /blog/rozliczenie-pit-38-inwestycje-zagraniczne — kontekst ogólny
- /blog/metoda-fifo-pit-38 — przy cross-broker FIFO
- /blog/strata-z-akcji-odliczenie — przy wzmiance o stracie

### Anti-patterns specyficzne dla tego artykułu
- NIE pisz "XTB automatycznie rozlicza podatek" — XTB oblicza i wysyła PIT-8C, PIT-38 składa user
- NIE twierdzij że PIT-8C z XTB "zawsze jest poprawne" — może być błędne przy cross-broker FIFO
- NIE pomijaj wątku IKE — to częsty case u użytkowników XTB
- NIE zakładaj że user ma tylko XTB

### Research do wykonania przed draftem
- [ ] Aktualna ścieżka eksportu raportu w XTB (UI zmienia się — sprawdzić w październiku 2026)
- [ ] Czy XTB stosuje kurs NBP z dnia transakcji czy z dnia poprzedzającego?
- [ ] Aktualny format PIT-8C 2026 — czy MF zmieniło układ sekcji?
- [ ] Czy XTB wysyła PIT-8C automatycznie czy trzeba pobrać z panelu?

### Disclaimery wymagane
- [ ] Standardowy: "Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego."
- [ ] Roku podatkowego: "Artykuł dotyczy roku podatkowego 2026 (PIT-38 składany do 30 kwietnia 2027)."
- [ ] IKE: "Zasady opodatkowania IKE zależą od indywidualnej sytuacji. Skonsultuj z doradcą przy wątpliwościach."

### Definition of Done
- [ ] Tax review: APPROVE (szczególnie sekcja IKE)
- [ ] SEO review: APPROVE
- [ ] Legal review: APPROVE (IKE disclaimer)
- [ ] Przykłady liczbowe zweryfikowane arytmetycznie
- [ ] UI opis aktualny po research
```

---

*Zatwierdzone przez zespół 2026-04-03.*
