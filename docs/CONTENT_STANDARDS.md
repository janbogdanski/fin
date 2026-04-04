# Content Standards — TaxPilot Blog

## Metadata

| | |
|---|---|
| Data | 2026-04-03 |
| Autorzy | Tomasz (doradca podatkowy), Michał P. (SEO/security), Zofia (UX/content), Łukasz (legal/risk), Marek (senior-dev) |
| Status | ZATWIERDZONE |
| Uwaga | Każdy nowy artykuł przechodzi content pipeline (patrz niżej) |

---

## Zasada nadrzędna

Artykuły TaxPilot są pisane jak przez **Tomasza Kędzierskiego — doradcę podatkowego z 10-letnim stażem**, który siedzi z klientem przy biurku i tłumaczy te same błędy po raz dziesiąty. Konkretny, rzeczowy, bez lania wody. Traktuje czytelnika jak inteligentną osobę, która potrzebuje faktów i przykładów — nie motywacji do inwestowania.

---

## 10 zasad dobrego artykułu

### Zasada 1: Każde twierdzenie ma liczbę lub źródło prawne

**Źle:** "Pamiętaj o stosowaniu kursów NBP do przeliczenia przychodów z zagranicy."

**Dobrze:** "Sprzedajesz akcje w środę 12 listopada — bierzesz kurs średni USD/PLN z tabeli A NBP z wtorku 11 listopada. Jeśli 11 listopada to święto (Dzień Niepodległości) — cofasz się do poniedziałku 10 listopada."

---

### Zasada 2: Artykuł odpowiada na pytanie, z którym user przyszedł — nie na ogólniejsze

User wpisuje "degiro pit-38 prowizja fx" — ma konkretny problem z opłatami FX. Artykuł nie zaczyna się od "Degiro to popularny broker holenderski...".

**Źle:** "Inwestujesz przez Degiro? Zanim rozliczysz PIT-38, musisz wiedzieć czym jest FIFO..."

**Dobrze:** "Degiro pobiera dwa rodzaje opłat, które inaczej traktujesz w PIT-38: transaction costs (koszt uzyskania przychodu) i connectivity fee (nie jest KUP)."

---

### Zasada 3: Przykłady liczbowe są kompletne i weryfikowalne

Przykład MUSI mieć: datę, ticker, ilość, cenę w walucie obcej, kurs NBP, wynik w PLN z widocznym działaniem.

**Źle:** "Kupiłeś 10 akcji po 180 USD przy kursie 4,05, więc Twój koszt to 7 290 PLN."

**Dobrze:**
```
Kupno: 10 szt. AAPL, 15.03.2026 (środa), cena 180 USD.
Kurs NBP z 14.03.2026 (wtorek): 4,05 PLN/USD.
Koszt nabycia: 10 × 180 × 4,05 = 7 290,00 PLN.
```

---

### Zasada 4: Pułapki są opisane zanim user w nie wpadnie

**Źle:** "Pamiętaj, że FIFO stosuje się globalnie, nie per broker."

**Dobrze:** "Masz AAPL w Revolut (kupno 01.03) i AAPL w IBKR (kupno 15.06). Sprzedajesz przez IBKR 01.10. Intuicja mówi: koszt to zakup z IBKR (15.06). Prawo mówi: koszt to zakup z Revolut (01.03) — bo jest starszy. Jeśli rozliczysz każdego brokera osobno, zapłacisz błędny podatek."

---

### Zasada 5: Każda sekcja ma wyraźny takeaway

Po każdym przykładzie — pogrubione podsumowanie:

**`Wniosek: FIFO jest globalne per ISIN, nie per rachunek brokerski.`**

---

### Zasada 6: Zastrzeżenia prawne są zintegrowane, nie tylko na końcu

Przy każdym nieoczywistym twierdzeniu — zaznacz poziom pewności lub wyjątki.

**Źle:** "Zawsze składaj W-8BEN."

**Dobrze:** "Złożenie W-8BEN obniża WHT z USA z 30% do 15% dla większości polskich rezydentów. Wyjątek: jeśli prowadzisz działalność przez spółkę lub masz specjalny status podatkowy — skonsultuj z doradcą."

---

### Zasada 7: Internal linking jest fabularny, nie mechaniczny

**Źle:** "Więcej informacji znajdziesz w artykule o FIFO [kliknij tutaj]."

**Dobrze:** "...musisz połączyć ewidencje ze wszystkich brokerów. Jeśli masz Revolut + IBKR, to wygląda tak: [Jak rozliczyć kilku brokerów — cross-broker FIFO]."

---

### Zasada 8: Ton jest ekspercki, ale nie protekcjonalny

**Źle:** "Teraz wytłumaczę Ci, co to jest FIFO, krok po kroku, powoli..."

**Dobrze:** "FIFO — First In, First Out — to metoda wyceny, którą ustawa o PIT narzuca bez wyjątków (art. 24 ust. 10). W praktyce: przy sprzedaży akcji kupowanych w kilku transzach, do obliczenia kosztu zawsze bierzesz najstarsze kupno."

---

### Zasada 9: Artykuł ma datę ważności

Footer każdego artykułu:
```
Artykuł dotyczy roku podatkowego [rok] (PIT-38 składany do 30 kwietnia [rok+1]).
Ostatnia weryfikacja merytoryczna: [data].
Przepisy podatkowe mogą ulec zmianie.
```

---

### Zasada 10: Sekcja TaxPilot jest specyficzna dla artykułu

**Źle:** "TaxPilot automatyzuje cały proces. Importujesz, sprawdzasz, wysyłasz."

**Dobrze:** "W przypadku Degiro konkretnie: TaxPilot rozróżnia 'transaction costs' (KUP) od 'connectivity fee' (nie-KUP) — ręcznie można to przeoczyć. Importuje jednocześnie Transactions i Account Statement i łączy prowizje FX z odpowiednimi transakcjami."

---

## Absolutne anti-patterns

Poniższe frazy powodują zwrot artykułu do przeróbki:

**Wypełniacze:**
- "Warto zauważyć, że..."
- "Należy pamiętać, że..."
- "Jest to niezwykle ważne..."
- "W dzisiejszych czasach..."
- "Jak wszyscy wiemy..."
- "Podatki mogą być skomplikowane, ale..."
- "Inwestowanie to fascynujące zajęcie..."
- Każde zdanie zaczynające się od "Pamiętaj, że..."

**Ogólniki bez liczb:**
- "kurs NBP jest odpowiedni" → jaki kurs? z jakiego dnia?
- "prowizje też wchodzą w koszty" → jak obliczone?
- "pamiętaj o FIFO" → zamiast: konkretny przykład

**Fałszywy zakres:**
- Artykuł o Degiro nie powtarza 3 akapitów o tym czym jest PIT-38 — jest link do artykułu głównego

**Zdania marketingowe bez substancji:**
- "TaxPilot to rewolucyjne narzędzie, które oszczędza Twój czas"
- "Prosta, intuicyjna aplikacja do rozliczania podatków"

**Nieweryfikowalne twierdzenia:**
- "Urząd skarbowy zazwyczaj akceptuje..." (brak źródła)
- "Większość doradców podatkowych zaleca..." (kto konkretnie?)

**Brak kontekstu czasowego:**
- Artykuł bez roku podatkowego jest mylący w każdym kolejnym roku

---

## Checklist przed publikacją

```
MERYTORYKA (weryfikuje agent "Tomasz" — tax-advisor)
[ ] Każde twierdzenie podatkowe zgodne z aktualną ustawą o PIT
[ ] Podstawy prawne przytoczone dla twierdzeń nieoczywistych (art. numer)
[ ] Stawki WHT aktualne i z datą weryfikacji
[ ] Przykłady liczbowe arytmetycznie poprawne
[ ] Brak "zawsze/nigdy" bez zastrzeżeń

SEO (weryfikuje agent "Michał P." — seo-auditor)
[ ] Primary keyword w: title, H1, pierwszym akapicie, meta description, jednym H2
[ ] Secondary keywords naturalnie wplecione (nie keyword stuffing)
[ ] Meta description: 150-160 znaków, zawiera primary keyword i CTA
[ ] Minimum 3 internal links do powiązanych artykułów
[ ] Schema markup ustawiony (Article lub HowTo)
[ ] Slug: lowercase, bez polskich znaków, z myślnikami

CZYTELNOŚĆ (weryfikuje agent "Zofia" — ux-reviewer)
[ ] Brak anti-patterns z listy powyżej
[ ] Każda sekcja H2 ma przykład liczbowy LUB tabelę LUB listę kroków
[ ] Artykuł odpowiada na intencję zapytania
[ ] Takeaway po każdej sekcji
[ ] Ton ekspercki, nie protekcjonalny i nie reklamowy

LEGAL (weryfikuje agent "Łukasz" — legal-auditor)
[ ] Disclaimer "nie stanowi doradztwa podatkowego" na końcu
[ ] Zastrzeżenie o roku podatkowym i dacie weryfikacji
[ ] Brak twierdzeń gwarantujących konkretny wynik podatkowy
[ ] Brak "musisz" przy nieoczywistych obowiązkach bez przepisu

KOMPLETNOŚĆ
[ ] Tytuł: primary keyword + rok (np. "2027")
[ ] Spis treści dla artykułów > 1500 słów
[ ] Sekcja FAQ (minimum 3 pytania — konkretne, nie "Czy warto?")
[ ] Sekcja TaxPilot: specyficzna dla artykułu, nie generyczna
[ ] Footer z rokiem podatkowym i datą weryfikacji
```

---

## Content Pipeline

```
BRIEF (content-strategist)
  ↓ — szablon: docs/CONTENT_BRIEF_TEMPLATE.md
RESEARCH (research-agent)
  ↓ — fakty, przepisy, broker-specific dane ze źródłami
DRAFT (content-writer-agent)
  ↓ — Markdown wg standardów
REVIEW — równolegle:
  ├── TAX REVIEW (tax-advisor-agent "Tomasz")
  ├── SEO REVIEW (seo-auditor-agent "Michał P.")
  └── LEGAL REVIEW (legal-auditor-agent "Łukasz")
  ↓
REVISIONS (content-writer-agent) — naprawia P0/P1
  ↓
FINAL APPROVAL (content-lead) — checklist
  ↓
PUBLISH — commit do /content/blog/, sitemap update
```

### Full pipeline vs Fast track

**Full pipeline** (wszystkie kroki): nowy artykuł, artykuł broker-specific, artykuł z przepisami/stawkami.

**Fast track** (skip research, jeden review): aktualizacja roku podatkowego w istniejącym artykule, poprawki SEO bez zmiany merytoryki.

**Tax review jest zawsze — bez wyjątków.**

---

## Długości artykułów

| Typ | Słowa |
|---|---|
| Pillar page (np. kompletny poradnik PIT-38) | 2500-4000 |
| Broker-specific (np. jak rozliczyć XTB) | 1800-2500 |
| Supporting (np. metoda FIFO, strata z akcji) | 1200-2000 |

Lepsza krótka treść z substancją niż długa z wypełniaczami.

---

*Zatwierdzone przez zespół 2026-04-03.*
