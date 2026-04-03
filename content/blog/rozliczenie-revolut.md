---
title: "Revolut a podatek giełdowy — co musisz wiedzieć (2027)"
slug: rozliczenie-revolut
description: "Jak rozliczyć inwestycje z Revolut w PIT-38? Revolut nie wysyła PIT-8C, nie podaje ISIN, a ułamkowe akcje komplikują obliczenia. Poradnik krok po kroku z rozwiązaniami."
date: 2026-11-12
keywords: [podatek gieldowy revolut, revolut pit-38, rozliczenie revolut podatek, revolut pit-8c, revolut akcje podatek, revolut dywidendy rozliczenie]
schema: Article
---

# Revolut a podatek giełdowy — co musisz wiedzieć (2027)

Revolut to jedna z najpopularniejszych aplikacji finansowych w Polsce. Miliony Polaków korzystają z niej na co dzień — do płatności, wymian walut, a coraz częściej do inwestowania w akcje. Problem? Revolut to broker z licencją litewską i **nie wysyła PIT-8C**. Ale to dopiero początek.

W tym artykule omawiam pięć głównych problemów podatkowych z Revolut i pokazuję, jak sobie z nimi poradzić. Bez paniki, z konkretnymi rozwiązaniami.

> Ten artykuł jest częścią kompletnego poradnika: [Jak rozliczyć PIT-38 z inwestycji zagranicznych](/blog/rozliczenie-pit-38-inwestycje-zagraniczne)

## Spis treści

1. [Revolut nie wysyła PIT-8C — i co teraz?](#brak-pit-8c)
2. [Jak wyeksportować dane z Revolut](#eksport-danych)
3. [Problem z brakującymi kodami ISIN](#brak-isin)
4. [Ułamkowe akcje — jak rozliczyć?](#ulamkowe-akcje)
5. [Dywidendy w Revolut](#dywidendy)
6. [Metoda FIFO w kontekście Revolut](#fifo-revolut)
7. [Kursy NBP — praktyczne problemy](#kursy-nbp)
8. [Revolut a inne brokery — łączne rozliczenie](#inne-brokery)
9. [Najczęstsze błędy przy rozliczaniu Revolut](#najczestsze-bledy)
10. [Jak TaxPilot rozwiązuje problemy z Revolut](#taxpilot)

---

## Revolut nie wysyła PIT-8C — i co teraz? {#brak-pit-8c}

Zacznijmy od podstaw. [PIT-8C to informacja podatkowa](/blog/pit-8c-vs-pit-38-roznice), którą **polski** broker ma obowiązek sporządzić i przesłać do Twojego urzędu skarbowego. Revolut nie jest polskim brokerem — działa na licencji litewskiej (Revolut Securities Europe UAB) — więc tego obowiązku nie ma.

Co to oznacza dla Ciebie?

- **Musisz sam policzyć** przychody, koszty i dochód ze sprzedaży akcji.
- **Musisz sam złożyć PIT-38** — urząd skarbowy nie wstawi Ci danych automatycznie (jak robi to przy polskich brokerach).
- **Musisz prowadzić ewidencję** transakcji przez cały rok.

Nie oznacza to, że urząd skarbowy nie wie o Twoich inwestycjach. W ramach automatycznej wymiany informacji podatkowych (CRS) dane o Twoich rachunkach inwestycyjnych i tak trafiają do polskiego fiskusa. Różnica w tym, że nie dostajesz gotowego zestawienia — musisz je przygotować sam.

Dobra wiadomość: Revolut udostępnia historię transakcji w formie eksportowalnej. Zła wiadomość: format tych danych nie jest idealny.

## Jak wyeksportować dane z Revolut {#eksport-danych}

Revolut oferuje kilka sposobów na pobranie historii inwestycyjnej:

### Opcja 1: Wyciąg z aplikacji

1. Otwórz Revolut → zakładka "Inwestycje" (lub "Stocks").
2. Kliknij ikonę ustawień (trybik).
3. Wybierz "Wyciągi" lub "Statements".
4. Pobierz za interesujący Cię rok.

Format: PDF lub CSV. Dla celów podatkowych **zawsze wybieraj CSV** — łatwiej przetwarzać.

### Opcja 2: Profit & Loss Statement

Revolut od 2024 roku generuje roczne zestawienie zysków i strat (Profit & Loss Statement). Znajdziesz je w:

1. Revolut → Inwestycje → Dokumenty.
2. Sekcja "Raporty podatkowe" lub "Tax Documents".

Ten raport jest przydatny jako punkt odniesienia, ale **nie zastępuje szczegółowej ewidencji transakcji**, bo nie zawiera informacji potrzebnych do prawidłowego przeliczenia na PLN (daty transakcji, kursy NBP).

### Opcja 3: Szczegółowy eksport CSV

W sekcji "Statements" możesz pobrać szczegółowy eksport zawierający:
- Datę i godzinę transakcji
- Ticker (np. AAPL, TSLA)
- Typ operacji (kupno/sprzedaż)
- Liczbę akcji (w tym ułamkowe)
- Cenę za akcję
- Walutę
- Prowizje (u Revolut zazwyczaj 0 dla podstawowego planu)

**Tip:** Pobieraj dane jak najwcześniej po zakończeniu roku. Revolut może zmieniać format eksportu między wersjami aplikacji.

## Problem z brakującymi kodami ISIN {#brak-isin}

To jeden z najbardziej irytujących problemów z Revolut. Eksport transakcji zawiera **ticker** (np. AAPL, TSLA), ale często **nie zawiera kodu ISIN** (International Securities Identification Number).

### Dlaczego ISIN jest ważny?

ISIN jednoznacznie identyfikuje papier wartościowy na świecie. Ticker tego nie robi — np. "SHELL" może oznaczać różne instrumenty na różnych giełdach. W kontekście polskiego rozliczenia podatkowego ISIN jest potrzebny do:

- Prawidłowej identyfikacji instrumentu w ewidencji
- Odróżnienia akcji od ETF-ów (różne zasady podatkowe w niektórych przypadkach)
- Ewentualnej weryfikacji przez urząd skarbowy

### Jak rozwiązać problem brakujących ISIN?

Opcja ręczna: dla każdego tickera wyszukujesz ISIN na stronach takich jak isin.org czy finance.yahoo.com. Przy kilkunastu spółkach to kilkanaście minut. Przy stu — to koszmar.

TaxPilot rozwiązuje to automatycznie. System mapuje tickery Revolut na kody ISIN na podstawie wewnętrznej bazy danych instrumentów. Więcej o tym w sekcji o [TaxPilot](#taxpilot).

## Ułamkowe akcje — jak rozliczyć? {#ulamkowe-akcje}

Revolut pozwala kupować **ułamkowe akcje** (fractional shares). Możesz kupić np. 0,5 akcji Apple za pół ceny jednej akcji. To świetna funkcja dla drobnych inwestorów, ale podatkowy koszmar.

### Czym są ułamkowe akcje technicznie?

Kiedy kupujesz 0,5 akcji Apple przez Revolut, nie stajesz się właścicielem połowy akcji w tradycyjnym sensie. Revolut (lub jego partner — DriveWealth) kupuje pełną akcję i przydziela Ci ułamkowy udział. Z podatkowego punktu widzenia to nadal nabycie i zbycie papierów wartościowych.

### Problem z FIFO i ułamkami

Metoda [FIFO](/blog/metoda-fifo-pit-38) działa tak samo dla ułamkowych akcji — sprzedajesz najstarsze nabyte jednostki. Ale przeliczenia robią się bardziej skomplikowane:

**Przykład:**
- 15.03 — kupno 0,3 AAPL po 170 USD
- 22.04 — kupno 0,7 AAPL po 175 USD
- 10.08 — sprzedaż 0,5 AAPL po 190 USD

Metodą FIFO:
1. Najpierw „zużywasz" 0,3 AAPL z 15.03 (koszt: 0,3 × 170 = 51 USD)
2. Potem 0,2 AAPL z 22.04 (koszt: 0,2 × 175 = 35 USD)
3. Łączny koszt: 86 USD
4. Przychód: 0,5 × 190 = 95 USD
5. Dochód: 9 USD

A teraz wyobraź sobie to dla 50 transakcji, w trzech walutach, z kursami NBP dla każdej daty. Ręcznie? Powodzenia.

### Ułamkowe akcje a regulacje KNF

Warto wiedzieć, że polscy brokerzy **nie oferują** ułamkowych akcji. To specyfika brokerów zagranicznych jak Revolut, Trading 212 czy eToro. Z podatkowego punktu widzenia nie ma osobnych przepisów dla ułamkowych akcji — rozliczasz je tak samo jak pełne, ale z dokładnością do ułamków.

## Dywidendy w Revolut {#dywidendy}

Jeśli posiadasz akcje spółek dywidendowych przez Revolut, dostajesz dywidendy automatycznie na konto. Revolut:

- **Pobiera WHT** zgodnie ze stawką kraju źródła.
- **Automatycznie stosuje W-8BEN** dla spółek amerykańskich (składasz go elektronicznie przy aktywacji handlu akcjami US).
- **Nie wystawia żadnego formularza podatkowego** dla polskiego fiskusa.

### Stawka WHT w Revolut

Dla spółek US z W-8BEN: **15%** (zgodnie z UPO Polska-USA). Revolut robi to dobrze — nie musisz się martwić o 30%.

Dla spółek z innych krajów — zależy od umowy UPO danego kraju z Litwą (bo Revolut Securities ma licencję litewską) lub bezpośrednio z krajem źródła. W praktyce stawki są zbliżone do tych z UPO polskich.

### Jak rozliczyć dywidendy z Revolut?

Proces jest taki sam jak dla każdego brokera zagranicznego — szczegóły znajdziesz w artykule o [rozliczeniu dywidend zagranicznych](/blog/rozliczenie-dywidend-zagranicznych):

1. Pobierz historię dywidend z Revolut (eksport CSV lub Profit & Loss Statement).
2. Dla każdej dywidendy znajdź kurs NBP z dnia poprzedzającego payment date.
3. Przelicz brutto dywidendy i WHT na PLN.
4. Wypełnij PIT/ZG dla każdego kraju.
5. W PIT-38 wykaż dochód z dywidend zagranicznych.

## Metoda FIFO w kontekście Revolut {#fifo-revolut}

[Metoda FIFO](/blog/metoda-fifo-pit-38) (First In, First Out) jest jedyną dozwoloną metodą rozliczania kosztów uzyskania przychodu w PIT-38 (art. 24 ust. 10 ustawy o PIT).

### FIFO a wiele brokerów

Ważna rzecz: FIFO stosuje się **globalnie** — nie per broker. Jeśli kupiłeś 10 akcji Apple przez Revolut w styczniu, a 10 przez Interactive Brokers w marcu, a potem sprzedałeś 10 przez Interactive Brokers w czerwcu — metodą FIFO sprzedajesz te z Revolut (bo były pierwsze).

To oznacza, że **nie możesz rozliczać Revolut w izolacji** od innych brokerów. Musisz połączyć historię transakcji ze wszystkich źródeł.

Więcej o cross-broker FIFO znajdziesz w artykule o [metodzie FIFO w PIT-38](/blog/metoda-fifo-pit-38).

### Revolut a brak ISIN — problem z identyfikacją

Skoro FIFO działa globalnie, musisz być pewien, że ten sam instrument jest identyfikowany jednakowo u każdego brokera. Interactive Brokers podaje ISIN, Revolut podaje ticker. Jeśli nie zmapujesz tickera na ISIN, możesz przypadkowo potraktować te same akcje jako różne instrumenty i zastosować FIFO błędnie.

## Kursy NBP — praktyczne problemy {#kursy-nbp}

Zasada: stosuj **średni kurs NBP z dnia roboczego poprzedzającego dzień transakcji**.

### Revolut a strefy czasowe

Revolut rejestruje transakcje w UTC. Jeśli kupiłeś akcje o 22:00 czasu polskiego (20:00 UTC), data w eksporcie może być inna niż oczekujesz. Przy transakcjach blisko północy — sprawdź dokładnie, jaka data figuruje w raporcie i odpowiadający jej kurs NBP.

### Weekendy i święta

Jeśli transakcja odbyła się w poniedziałek, kurs NBP bierzesz z piątku (ostatni dzień roboczy przed transakcją). Ale uwaga — jeśli piątek był świętem, bierzesz kurs z czwartku. Przy Revolut, gdzie handel jest dostępny dłużej niż na tradycyjnych giełdach, trafienie na niestandardowy dzień jest bardziej prawdopodobne.

## Revolut a inne brokery — łączne rozliczenie {#inne-brokery}

Wielu polskich inwestorów korzysta z Revolut **i** innego brokera — np. [Interactive Brokers](/blog/rozliczenie-interactive-brokers) lub [Degiro](/blog/rozliczenie-degiro). Jak to łączyć?

### PIT-38 jest jeden

Niezależnie od liczby brokerów, składasz **jeden PIT-38**. Sumujesz przychody i koszty ze wszystkich źródeł.

### FIFO jest globalne

Jak napisałem wyżej — FIFO stosuje się per instrument, nie per broker. Musisz połączyć transakcje chronologicznie.

### PIT/ZG per kraj, nie per broker

Jeśli masz dywidendy z USA przez Revolut i przez Interactive Brokers — łączysz je w **jednym** PIT/ZG dla USA.

### Praktyczna rada

Wyeksportuj dane z każdego brokera na początku roku i połącz je w jednym zestawieniu (Excelu lub narzędziu). Alternatywnie — wgraj wszystkie raporty do TaxPilot, który sam je połączy i zastosuje FIFO globalnie.

## Najczęstsze błędy przy rozliczaniu Revolut {#najczestsze-bledy}

### 1. "Mam Revolut, więc nie muszę składać PIT-38"

Błąd numer jeden. Jeśli sprzedałeś **cokolwiek** w danym roku — musisz złożyć PIT-38. Revolut nie zrobi tego za Ciebie.

### 2. Zapomnienie o dywidendach

Sprzedaży zwykle pamiętasz. Dywidendy — nie zawsze. A każda dywidenda zagraniczna wymaga rozliczenia w PIT-38 z załącznikiem PIT/ZG.

### 3. Ignorowanie ułamkowych akcji

"Kupiłem za 50 zł, to nieistotne" — nie dla urzędu skarbowego. Każda transakcja, nawet na 0,01 akcji, podlega rozliczeniu.

### 4. FIFO tylko w ramach Revolut

Jeśli masz ten sam instrument u innego brokera — FIFO stosuje się globalnie. Rozliczanie Revolut osobno to błąd.

### 5. Zły kurs NBP przez problem z datami

Strefy czasowe, weekendy, święta — łatwo wziąć kurs z niewłaściwego dnia. Różnica bywa niewielka, ale przy kontroli urząd może się przyczepić.

### 6. Brak ewidencji przez cały rok

Revolut może zmienić format eksportu, usunąć starsze dane lub zmienić sposób prezentacji. Pobieraj raporty **regularnie** i archiwizuj je.

## Jak TaxPilot rozwiązuje problemy z Revolut {#taxpilot}

TaxPilot został zaprojektowany z myślą o brokerach takich jak Revolut, gdzie standardowe rozliczenie wymaga dużo ręcznej pracy.

Co TaxPilot robi z raportem Revolut:

1. **Parsuje eksport CSV** — rozpoznaje format Revolut, wyodrębnia transakcje kupna, sprzedaży i dywidend.
2. **Mapuje tickery na ISIN** — wewnętrzna baza instrumentów automatycznie przypisuje kody ISIN do tickerów Revolut. Koniec z ręcznym wyszukiwaniem.
3. **Obsługuje ułamkowe akcje** — FIFO z dokładnością do ułamków, prawidłowe przeliczenia proporcjonalne.
4. **Łączy z innymi brokerami** — wgrywasz raporty z Revolut, IB, Degiro — system stosuje FIFO globalnie.
5. **Kursy NBP** — automatycznie dla każdej transakcji, z uwzględnieniem weekendów i świąt.
6. **Dywidendy z PIT/ZG** — identyfikuje kraj źródła, WHT, generuje osobne PIT/ZG.
7. **Gotowy PIT-38** — kompletny formularz do wgrania na e-Deklaracje.

Zamiast godzin w Excelu — kilka minut. Zamiast stresu o błędy — automatyczna weryfikacja.

[Wypróbuj TaxPilot za darmo →](https://taxpilot.pl)

---

## Podsumowanie

Revolut to wygodna aplikacja do inwestowania, ale rozliczenie podatkowe wymaga samodzielnej pracy. Najważniejsze rzeczy do zapamiętania:

- **Revolut nie wysyła PIT-8C** — musisz złożyć PIT-38 samodzielnie.
- **Eksportuj dane jako CSV** — PDF jest mniej użyteczny do obliczeń.
- **Brak ISIN** to problem, który musisz rozwiązać (ręcznie lub narzędziem).
- **Ułamkowe akcje** rozliczasz tak samo jak pełne — FIFO z dokładnością do ułamków.
- **Dywidendy** wymagają PIT/ZG i przeliczenia WHT.
- **FIFO jest globalne** — jeśli masz tego samego tickera u innego brokera, musisz to uwzględnić.

Jeśli chcesz zaoszczędzić czas i uniknąć błędów, [sprawdź TaxPilot](https://taxpilot.pl) — wgraj raport z Revolut i otrzymaj gotowy PIT-38 w kilka minut.

---

*Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego. W przypadku wątpliwości skonsultuj się z doradcą podatkowym.*
