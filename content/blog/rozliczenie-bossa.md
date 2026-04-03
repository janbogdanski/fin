---
title: "Rozliczenie Bossa Zagranica — poradnik PIT-38 (2027)"
slug: rozliczenie-bossa
description: "Jak rozliczyć Bossa Zagranica w PIT-38? Poradnik krok po kroku: eksport CSV, format Windows-1250, FIFO w PLN, prowizje, różnice między Bossa PL a Bossa Zagranica."
date: 2026-12-01
keywords: [rozliczenie bossa pit-38, bossa zagranica pit-38, bossa csv eksport, bossa zagranica rozliczenie, bossa fifo]
schema: Article
---

# Rozliczenie Bossa Zagranica — poradnik PIT-38 (2027)

Bossa to platforma inwestycyjna Domu Maklerskiego BOŚ. Wielu polskich inwestorów korzysta z dwóch wariantów: Bossa PL (giełda polska) i Bossa Zagranica (giełdy zagraniczne). Rozliczenie podatkowe w obu przypadkach wygląda inaczej — i tu zaczynają się schody.

Jeśli inwestujesz przez Bossa Zagranica, ten poradnik przeprowadzi Cię przez cały proces: od eksportu CSV po gotowy PIT-38.

> Ten artykuł jest częścią kompletnego poradnika: [Jak rozliczyć PIT-38 z inwestycji zagranicznych](/blog/rozliczenie-pit-38-inwestycje-zagraniczne)

## Bossa PL vs Bossa Zagranica — kluczowe różnice

Zanim przejdziemy do rozliczenia, warto zrozumieć, czym się różnią te dwa warianty. To ma bezpośredni wpływ na to, ile pracy czeka Cię przy PIT-38.

### Bossa PL (GPW)

Bossa PL daje dostęp do Giełdy Papierów Wartościowych w Warszawie. Jako polski dom maklerski, BOŚ ma obowiązek wystawić **PIT-8C** za każdy rok podatkowy. Dostajesz go do końca lutego — gotowe zestawienie przychodów i kosztów. Teoretycznie wystarczy przepisać kwoty do PIT-38.

W praktyce warto sprawdzić, czy PIT-8C jest poprawny — zdarzają się pomyłki, szczególnie przy corporate actions. Ale punkt wyjścia masz gotowy.

Więcej o różnicy między PIT-8C a PIT-38 przeczytasz tutaj: [PIT-8C a PIT-38 — czym się różnią i kiedy który?](/blog/pit-8c-vs-pit-38-roznice)

### Bossa Zagranica

Bossa Zagranica daje dostęp do giełd zagranicznych (NYSE, NASDAQ, LSE i inne) przez partnera zagranicznego. I tu jest haczyk: **transakcje zagraniczne realizowane są przez podmiot zagraniczny**, więc BOŚ **nie zawsze uwzględnia je w PIT-8C** w sposób kompletny. Zależy to od modelu pośrednictwa i konkretnego roku podatkowego.

W praktyce wielu inwestorów raportuje, że PIT-8C z Bossa albo nie uwzględnia transakcji zagranicznych, albo uwzględnia je bez przeliczenia na PLN po kursach NBP. Musisz samodzielnie zweryfikować dane i uzupełnić rozliczenie.

### Podsumowanie różnic

| Cecha | Bossa PL | Bossa Zagranica |
|---|---|---|
| Giełdy | GPW, NewConnect | NYSE, NASDAQ, LSE i inne |
| Waluta transakcji | PLN | USD, EUR, GBP |
| PIT-8C | Tak, kompletny | Często niekompletny |
| Przeliczenie NBP | Nie dotyczy (PLN) | Wymagane |
| Samodzielne rozliczenie | Minimalne | Pełne |

## Krok 1: Eksport CSV z Bossa

### Gdzie znaleźć eksport?

1. Zaloguj się na [bossa.pl](https://bossa.pl) do panelu klienta.
2. Przejdź do sekcji **Historia** → **Transakcje**.
3. Ustaw zakres dat: **01.01.2026 — 31.12.2026**.
4. Wybierz konto **Zagranica**.
5. Kliknij **Eksportuj do CSV**.

<!-- Screenshot: widok eksportu transakcji w panelu Bossa z zaznaczonym kontem Zagranica -->

### Format pliku CSV z Bossa

Tu zaczyna się zabawa. Pliki CSV z Bossa mają kilka specyficznych cech, które mogą sprawić problemy przy ręcznym przetwarzaniu:

**Kodowanie: Windows-1250** — Bossa eksportuje pliki w kodowaniu Windows-1250 (CP-1250), a nie UTF-8. Jeśli otworzysz taki plik w nowoczesnym edytorze tekstu lub arkuszu kalkulacyjnym bez ustawienia odpowiedniego kodowania, polskie znaki (ą, ę, ś, ź itd.) zamienią się w "krzaczki".

Jak otworzyć poprawnie:
- **Excel:** Użyj importu danych (Dane → Z pliku tekstowego/CSV) i wybierz kodowanie "1250: Europa Środkowa (Windows)".
- **LibreOffice Calc:** Przy otwieraniu pliku zaznacz kodowanie "Europa Środkowa (Windows-1250/WinLatin 2)".
- **Google Sheets:** Zaimportuj plik i wybierz automatyczne wykrywanie lub ręcznie ustaw kodowanie.

**Separator: średnik (;)** — Bossa używa średnika jako separatora kolumn, nie przecinka. To polska konwencja (bo w polskiej notacji liczbowej przecinek jest separatorem dziesiętnym). Excel w polskiej wersji językowej zazwyczaj rozpoznaje to automatycznie, ale w angielskiej wersji musisz ustawić separator ręcznie.

**Separator dziesiętny: przecinek** — Kwoty są zapisane z przecinkiem (np. "105,20" zamiast "105.20"). Przy przetwarzaniu programistycznym trzeba to zamienić na kropkę.

### Warianty nagłówków CSV

Bossa zmieniała format CSV na przestrzeni lat. W zależności od tego, kiedy eksportujesz dane, możesz spotkać różne warianty nagłówków:

**Wariant aktualny (2025-2026):**

```
Data;Czas;Instrument;ISIN;Typ;Ilość;Cena;Waluta;Wartość;Prowizja;Wartość netto
```

**Wariant starszy (2023-2024):**

```
Data operacji;Instrument;Kierunek;Ilość;Cena;Waluta;Prowizja;Wartość
```

**Wariant najstarszy (do 2022):**

```
Data;Papier;K/S;Ilosc;Cena;Waluta;Prowizja;Wartosc
```

Różnice dotyczą nazw kolumn i ich kolejności. Zawartość merytoryczna jest podobna, ale przy ręcznym przetwarzaniu musisz wiedzieć, z którym wariantem masz do czynienia. TaxPilot rozpoznaje wszystkie trzy warianty automatycznie.

### Przykładowy plik CSV (wariant aktualny)

```
Data;Czas;Instrument;ISIN;Typ;Ilość;Cena;Waluta;Wartość;Prowizja;Wartość netto
2026-02-10;14:35:22;APPLE INC;US0378331005;K;10;185,50;USD;1855,00;9,90;1864,90
2026-05-20;16:12:08;APPLE INC;US0378331005;K;5;192,30;USD;961,50;9,90;971,40
2026-08-15;15:45:33;MICROSOFT CORP;US5949181045;K;8;410,00;USD;3280,00;9,90;3289,90
2026-10-22;17:02:11;APPLE INC;US0378331005;S;12;205,80;USD;2469,60;9,90;2459,70
2026-11-28;16:30:45;MICROSOFT CORP;US5949181045;S;8;435,00;USD;3480,00;9,90;3470,10
```

Typ "K" = kupno, "S" = sprzedaż. Prowizja jest podana w walucie transakcji.

## Krok 2: Identyfikacja transakcji do rozliczenia

Z pliku CSV wyfiltruj transakcje sprzedaży (Typ = "S"). To te transakcje generują przychód podatkowy.

Następnie dla każdej sprzedaży musisz zidentyfikować odpowiadające kupna — wg zasady **FIFO** (First In, First Out).

### Czym jest FIFO i dlaczego ma znaczenie?

FIFO oznacza, że przy sprzedaży akcji najpierw "zużywasz" te kupione najwcześniej. Nie możesz wybrać, które pakiety sprzedajesz — kolejność jest narzucona przez prawo podatkowe.

W naszym przykładzie sprzedajesz 12 akcji Apple 22.10.2026. Wg FIFO:
1. Najpierw 10 szt. z kupna 10.02.2026 (po 185,50 USD)
2. Potem 2 szt. z kupna 20.05.2026 (po 192,30 USD)

Zostają 3 szt. z kupna 20.05 — przechodzą na następny rok.

## Krok 3: Przeliczenie na PLN po kursach NBP

Każdą transakcję przeliczasz na PLN po kursie średnim NBP z **dnia roboczego poprzedzającego** dzień transakcji. To wymóg ustawowy.

### Przeliczenie Apple

**Sprzedaż 22.10.2026 — 12 szt. po 205,80 USD**
- Kurs NBP z 21.10.2026: 4,12 PLN/USD
- Przychód: 12 x 205,80 x 4,12 = **10 174,75 PLN**
- Prowizja sprzedaży: 9,90 x 4,12 = **40,79 PLN**

**Kupno FIFO — 10 szt. z 10.02.2026**
- Kurs NBP z 09.02.2026: 4,05 PLN/USD
- Koszt: 10 x 185,50 x 4,05 = **7 512,75 PLN**
- Prowizja (proporcjonalnie 10/10): 9,90 x 4,05 = **40,10 PLN**

**Kupno FIFO — 2 szt. z 20.05.2026**
- Kurs NBP z 19.05.2026: 4,08 PLN/USD
- Koszt: 2 x 192,30 x 4,08 = **1 569,17 PLN**
- Prowizja (proporcjonalnie 2/5): 9,90 x 2/5 x 4,08 = **16,16 PLN**

**Dochód z Apple:**
- Przychód: 10 174,75 PLN
- Koszty: 7 512,75 + 1 569,17 + 40,79 + 40,10 + 16,16 = 9 178,97 PLN
- **Dochód: 995,78 PLN**

### Przeliczenie Microsoft

**Sprzedaż 28.11.2026 — 8 szt. po 435,00 USD**
- Kurs NBP z 27.11.2026: 4,15 PLN/USD
- Przychód: 8 x 435 x 4,15 = **14 442,00 PLN**
- Prowizja sprzedaży: 9,90 x 4,15 = **41,09 PLN**

**Kupno 15.08.2026 — 8 szt. po 410,00 USD**
- Kurs NBP z 14.08.2026: 4,10 PLN/USD
- Koszt: 8 x 410 x 4,10 = **13 448,00 PLN**
- Prowizja kupna: 9,90 x 4,10 = **40,59 PLN**

**Dochód z Microsoft:**
- Przychód: 14 442,00 PLN
- Koszty: 13 448,00 + 41,09 + 40,59 = 13 529,68 PLN
- **Dochód: 912,32 PLN**

### Podsumowanie transakcji

| Instrument | Przychód (PLN) | Koszt (PLN) | Dochód (PLN) |
|---|---|---|---|
| Apple | 10 174,75 | 9 178,97 | 995,78 |
| Microsoft | 14 442,00 | 13 529,68 | 912,32 |
| **Razem** | **24 616,75** | **22 708,65** | **1 908,10** |

Podatek (19%): 1 908,10 x 0,19 = **362,54 PLN**

## Krok 4: Prowizje w Bossa Zagranica

Bossa Zagranica ma specyficzną strukturę opłat, którą musisz znać przy rozliczeniu:

### Prowizja transakcyjna

Stała prowizja za transakcję (np. 9,90 USD za zlecenie na giełdzie USA). Widoczna w CSV w kolumnie "Prowizja". To bezpośredni koszt uzyskania przychodu — doliczasz do kosztów w PIT-38.

### Spread walutowy

Bossa przewalutowuje środki po własnym kursie (kurs Bossa, nie kurs NBP). Różnica między kursem Bossa a kursem rynkowym to ukryty koszt. Tego kosztu **nie odliczasz** w PIT-38 — do rozliczenia stosujesz kurs średni NBP, niezależnie od kursu Bossa.

### Opłata za prowadzenie rachunku

Bossa może pobierać opłatę za prowadzenie rachunku zagranicznego. To opłata ogólna, nie związana z konkretną transakcją — **nie jest** kosztem uzyskania przychodu.

### Opłata za przechowywanie instrumentów

Analogicznie — opłata depozytowa nie kwalifikuje się jako koszt uzyskania przychodu.

### Co jest kosztem, a co nie — podsumowanie

| Opłata | Koszt uzyskania przychodu? |
|---|---|
| Prowizja transakcyjna | Tak |
| Spread walutowy | Nie (stosujesz kurs NBP) |
| Opłata za prowadzenie rachunku | Nie |
| Opłata depozytowa | Nie |

## Krok 5: FIFO cross-broker — ważna pułapka

Jeśli masz akcje tego samego instrumentu u kilku brokerów (np. Apple przez Bossa i Apple przez Interactive Brokers), FIFO stosuje się **globalnie**, a nie per broker. To znaczy, że przy sprzedaży Apple przez Bossa jako koszt bierzesz najstarsze kupno Apple — nawet jeśli było u innego brokera.

W praktyce to komplikuje rozliczenie, bo musisz połączyć dane z wielu źródeł. Ręcznie to koszmar. TaxPilot obsługuje FIFO cross-broker automatycznie — wgrywasz pliki ze wszystkich brokerów, a algorytm łączy transakcje.

Więcej o tym problemie przeczytasz w naszym porównaniu narzędzi: [Kalkulator podatku giełdowego — porównanie narzędzi](/blog/kalkulator-podatku-gieldowego-porownanie)

## Krok 6: Dywidendy z Bossa Zagranica

Dywidendy z akcji zagranicznych kupionych przez Bossa rozliczasz na załączniku **PIT/ZG**. W historii operacji znajdziesz pozycje typu "Dywidenda" z kwotą brutto i potrąconym podatkiem u źródła (WHT).

### Przykład

| Data | Instrument | Dywidenda brutto (USD) | WHT (USD) |
|---|---|---|---|
| 15.05.2026 | APPLE INC | 24,00 | 3,60 |
| 10.08.2026 | MICROSOFT CORP | 22,40 | 3,36 |

Kurs NBP z 14.05.2026: 4,10 PLN/USD
- Dywidenda Apple: 24,00 x 4,10 = **98,40 PLN**
- WHT Apple: 3,60 x 4,10 = **14,76 PLN**
- Podatek PL (19%): 98,40 x 0,19 = **18,70 PLN**
- Do dopłaty: 18,70 - 14,76 = **3,94 PLN**

Kurs NBP z 09.08.2026: 4,10 PLN/USD
- Dywidenda Microsoft: 22,40 x 4,10 = **91,84 PLN**
- WHT Microsoft: 3,36 x 4,10 = **13,78 PLN**
- Podatek PL (19%): 91,84 x 0,19 = **17,45 PLN**
- Do dopłaty: 17,45 - 13,78 = **3,67 PLN**

Dywidendy z USA grupujesz razem w PIT/ZG.

## Krok 7: Wypełnienie PIT-38

### Część C formularza PIT-38

- **Przychód:** 24 616,75 PLN
- **Koszty uzyskania przychodu:** 22 708,65 PLN
- **Dochód:** 1 908,10 PLN

### Załącznik PIT/ZG

Dywidendy z USA:
- Dochód: 190,24 PLN
- Podatek zapłacony za granicą: 28,54 PLN

### Część F — obliczenie podatku

- Podatek od zysków kapitałowych: 1 908,10 x 19% = 362,54 PLN
- Podatek od dywidend: 190,24 x 19% = 36,15 PLN, minus WHT 28,54 PLN = 7,61 PLN
- **Razem do zapłaty:** 370,15 PLN

## Jak TaxPilot to upraszcza

Rozliczenie Bossa Zagranica jest szczególnie problematyczne z powodu:

- **Kodowania Windows-1250** — pliki CSV wymagają konwersji.
- **Separatora średnik** — standardowe narzędzia mogą go nie rozpoznać.
- **Wariantów nagłówków** — format CSV zmieniał się na przestrzeni lat.
- **Niekompletnego PIT-8C** — musisz samodzielnie zweryfikować dane.

**TaxPilot** radzi sobie z tym wszystkim automatycznie:

1. Wgrywasz plik CSV z Bossa (dowolny wariant nagłówków).
2. TaxPilot rozpoznaje kodowanie, separator, mapuje kolumny.
3. Pobiera kursy NBP, stosuje FIFO (w tym cross-broker).
4. Generuje gotowy PIT-38 + PIT/ZG w formacie XML.

Bez ręcznej konwersji kodowania, bez Excela, bez szukania kursów.

[Rozlicz Bossa z TaxPilot →](https://taxpilot.pl)

---

## Najczęstsze pytania

### Dostałem PIT-8C z Bossa — czy muszę jeszcze coś robić?

Sprawdź, czy PIT-8C zawiera transakcje zagraniczne. Jeśli masz tylko Bossa PL — PIT-8C powinien być kompletny. Jeśli masz też Bossa Zagranica — zweryfikuj, czy transakcje zagraniczne są uwzględnione i poprawnie przeliczone na PLN po kursach NBP.

### Czy mogę otworzyć CSV z Bossa w Google Sheets?

Tak, ale musisz ustawić odpowiednie kodowanie przy imporcie. Google Sheets automatycznie wykrywa separator (średnik), ale z kodowaniem Windows-1250 może mieć problem. Jeśli widzisz "krzaczki" zamiast polskich znaków — skonwertuj plik na UTF-8 przed importem.

### Mam Bossa PL i Bossa Zagranica — jedno rozliczenie czy dwa?

Jedno. Składasz jeden PIT-38, w którym sumujesz wyniki ze wszystkich źródeł — Bossa PL, Bossa Zagranica i ewentualnie innych brokerów.

### Bossa zmieniła format CSV — co robić?

Jeśli przetwarzasz CSV ręcznie, sprawdź nagłówki i dostosuj swój arkusz/skrypt. Jeśli używasz TaxPilot — wgrywasz plik bez zmian, a system sam rozpozna wariant.

### Czy prowizja minimalna w Bossa (np. 9,90 USD) jest kosztem uzyskania przychodu?

Tak. Prowizja transakcyjna, niezależnie od tego czy stała (minimalna) czy procentowa, jest kosztem uzyskania przychodu. Przypisujesz ją do konkretnej transakcji kupna lub sprzedaży.

---

*Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego. W przypadku wątpliwości skonsultuj się z doradcą podatkowym.*
