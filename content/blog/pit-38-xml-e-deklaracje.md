---
title: "PIT-38 XML — jak wysłać przez e-Deklaracje krok po kroku"
slug: pit-38-xml-e-deklaracje
description: "Jak wygenerować PIT-38 w formacie XML i wysłać przez e-Deklaracje? Poradnik krok po kroku: schemat XSD, walidacja, typowe błędy, UPO. Jak TaxPilot generuje gotowy XML."
date: 2026-12-08
keywords: [pit-38 xml jak zlozyc, e-deklaracje pit-38, pit-38 xml schemat, e-deklaracje jak wyslac, pit-38 xml generator]
schema: Article
---

# PIT-38 XML — jak wysłać przez e-Deklaracje krok po kroku

Masz już obliczone przychody, koszty i podatek. Teraz musisz to wszystko dostarczyć do urzędu skarbowego. Najprostszy sposób? **Plik XML wysłany przez e-Deklaracje.** Bez drukowania, bez poczty, bez kolejki w urzędzie.

Brzmi łatwo, ale XML ma swoje pułapki. W tym artykule wyjaśnię, czym jest format XML w kontekście PIT-38, jak go wygenerować, jak wysłać przez e-Deklaracje i na jakie błędy uważać.

> Ten artykuł jest częścią kompletnego poradnika: [Jak rozliczyć PIT-38 z inwestycji zagranicznych](/blog/rozliczenie-pit-38-inwestycje-zagraniczne)

## Czym jest format XML?

XML (Extensible Markup Language) to format zapisu danych w postaci ustrukturyzowanego tekstu. W kontekście PIT-38 to po prostu plik tekstowy, który zawiera wszystkie dane z formularza — Twoje dane osobowe, kwoty przychodów, kosztów, podatku — zapisane w ściśle określonej strukturze.

Plik XML z PIT-38 wygląda mniej więcej tak (uproszczony fragment):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<Deklaracja xmlns="http://crd.gov.pl/wzor/2024/12/03/13427/">
  <Naglowek>
    <KodFormularza kodSystemowy="PIT-38">PIT-38</KodFormularza>
    <WariantFormularza>17</WariantFormularza>
    <CelZlozenia poz="P_6">1</CelZlozenia>
    <Rok>2026</Rok>
  </Naglowek>
  <PozycjeSzczegolowe>
    <P_22>24616.75</P_22>  <!-- Przychód -->
    <P_23>22708.65</P_23>  <!-- Koszty -->
    <P_24>1908.10</P_24>   <!-- Dochód -->
    <P_42>362.54</P_42>    <!-- Podatek -->
  </PozycjeSzczegolowe>
</Deklaracja>
```

Nie musisz rozumieć składni XML. Ważne jest to, że urząd skarbowy przyjmuje deklaracje w tym formacie — i że plik musi być **dokładnie** zgodny ze schematem.

## Czym jest schemat XSD?

Schemat XSD (XML Schema Definition) to "szablon", który definiuje, jakie pola musi zawierać plik XML, jakiego typu muszą być dane (tekst, liczba, data), jakie wartości są dozwolone i w jakiej kolejności muszą występować elementy.

Ministerstwo Finansów publikuje schematy XSD dla każdego formularza podatkowego. Schemat dla PIT-38 za rok 2026 znajdziesz na stronie:

**[https://www.podatki.gov.pl/e-deklaracje/dokumentacja-it/struktury-dokumentow-xml/](https://www.podatki.gov.pl/e-deklaracje/dokumentacja-it/struktury-dokumentow-xml/)**

Dlaczego to ważne? Bo jeśli Twój plik XML nie jest zgodny ze schematem — e-Deklaracje go odrzucą. Najczęstsze przyczyny niezgodności to:

- Błędny numer wariantu formularza (zmienia się co rok)
- Brak wymaganych pól
- Wartości poza dozwolonym zakresem
- Błędna przestrzeń nazw (namespace) XML

## Gdzie wziąć plik XML z PIT-38?

Masz trzy opcje:

### Opcja 1: Ręczne stworzenie XML

Teoretycznie możesz napisać plik XML ręcznie w edytorze tekstu. W praktyce to droga do szaleństwa. Schemat PIT-38 ma kilkadziesiąt pól, ścisłe reguły walidacji i zmienia się co rok. Jeden brakujący tag lub literówka w nazwie pola — i plik jest odrzucany.

Nie polecam. Ale jeśli jesteś programistą i lubisz wyzwania — schemat XSD jest publiczny.

### Opcja 2: Formularz interaktywny PDF

Ministerstwo Finansów udostępnia interaktywne formularze PDF na stronie e-Deklaracji. Wypełniasz pola w PDF-ie, a formularz generuje XML wewnętrznie i wysyła za Ciebie.

Problem: interaktywne PDF-y nie zawsze działają poprawnie na macOS i Linuxie. Wymagają Adobe Readera, a na nowszych wersjach bywają niestabilne. Poza tym dane musisz wpisywać ręcznie — więc cała mordęga z obliczeniami nadal na Tobie.

### Opcja 3: TaxPilot (automatyczny generator XML)

TaxPilot generuje plik XML automatycznie na podstawie wgranych danych transakcyjnych. Plik jest zgodny z aktualnym schematem XSD, walidowany przed wygenerowaniem i gotowy do wysłania przez e-Deklaracje.

Jak to działa:
1. Wgrywasz pliki CSV z brokerów (IBKR, Degiro, Bossa itd.).
2. TaxPilot oblicza przychody, koszty, FIFO, dywidendy.
3. Klikasz "Generuj XML".
4. Pobierasz plik `.xml` gotowy do wysłania.

Plik zawiera PIT-38 i (jeśli masz dywidendy zagraniczne) załącznik PIT/ZG — wszystko w jednym XML.

## Jak wysłać PIT-38 przez e-Deklaracje — krok po kroku

### Krok 1: Przejdź na stronę e-Deklaracji

Otwórz: **[https://www.podatki.gov.pl/e-deklaracje/](https://www.podatki.gov.pl/e-deklaracje/)**

Wybierz opcję **"Wyślij e-Deklarację"** lub przejdź bezpośrednio do bramki: **[https://bramka.e-deklaracje.mf.gov.pl/](https://bramka.e-deklaracje.mf.gov.pl/)**

<!-- Screenshot: strona główna e-Deklaracji z zaznaczonym przyciskiem "Wyślij e-Deklarację" -->

### Krok 2: Wybierz plik XML

Na stronie bramki kliknij **"Wybierz plik"** (lub "Dodaj załącznik") i wskaż plik XML pobrany z TaxPilot lub wygenerowany w inny sposób.

System załaduje plik i przeprowadzi wstępną walidację. Jeśli plik jest poprawny — zobaczysz podgląd danych.

### Krok 3: Autoryzacja

Do wysłania PIT-38 bez podpisu kwalifikowanego potrzebujesz **danych autoryzujących**:

- **PESEL** (lub NIP)
- **Imię** (pierwsze)
- **Nazwisko**
- **Data urodzenia**
- **Kwota przychodu z deklaracji za poprzedni rok** (np. z PIT-37 za 2025 — kwota z poz. 63 lub analogicznej)

To ostatni punkt jest najczęstszą przyczyną problemów. Musisz podać **dokładną kwotę** z zeszłorocznej deklaracji — co do grosza. Jeśli nie składałeś deklaracji za poprzedni rok, wpisujesz 0.

Alternatywnie możesz użyć:
- **Podpisu kwalifikowanego** (e-podpis)
- **Profilu zaufanego** (ePUAP) — ale nie na bramce e-Deklaracji, tylko przez Twój e-PIT

### Krok 4: Wysłanie i UPO

Po autoryzacji kliknij **"Wyślij"**. System przetworzy deklarację i zwróci **UPO (Urzędowe Poświadczenie Odbioru)**.

UPO to Twój dowód, że deklaracja została złożona. Zawiera:
- Numer referencyjny
- Datę i godzinę przyjęcia
- Status przetwarzania

**Zapisz UPO!** To jedyny dowód złożenia deklaracji. Pobierz PDF lub zanotuj numer referencyjny.

UPO możesz pobrać:
- Od razu po wysłaniu
- Później — na stronie **[https://www.podatki.gov.pl/e-deklaracje/sprawdz-status-e-deklaracji/](https://www.podatki.gov.pl/e-deklaracje/sprawdz-status-e-deklaracji/)** po wpisaniu numeru referencyjnego

## Alternatywa: Twój e-PIT

Od kilku lat Ministerstwo Finansów udostępnia usługę **Twój e-PIT** (login.gov.pl → e-Urząd Skarbowy). Jeśli masz PIT-8C od polskiego brokera, PIT-38 może być częściowo wypełniony automatycznie.

Ale: Twój e-PIT **nie uwzględnia** transakcji od brokerów zagranicznych (IBKR, Degiro, Bossa Zagranica). Musisz ręcznie uzupełnić dane lub wysłać własny XML, który nadpisze wersję przygotowaną przez urząd.

Więcej o Twój e-PIT vs własny XML przeczytasz w artykule: [PIT-38 za 2026 — terminy, zmiany, nowe przepisy](/blog/pit-38-termin-2027)

## Typowe błędy przy wysyłaniu XML

### Błąd 1: Nieprawidłowy NIP/PESEL

Najczęstszy błąd. System waliduje sumę kontrolną NIP/PESEL. Literówka = odrzucenie.

**Rozwiązanie:** Sprawdź PESEL/NIP przed wygenerowaniem XML. TaxPilot waliduje numer na etapie wprowadzania danych.

### Błąd 2: Niewłaściwy wariant formularza

Schemat PIT-38 zmienia się co rok. Wariant formularza (tag `WariantFormularza`) musi odpowiadać aktualnemu schematowi. Plik z zeszłorocznym wariantem zostanie odrzucony.

**Rozwiązanie:** Upewnij się, że używasz aktualnego schematu. TaxPilot aktualizuje schemat automatycznie na początku każdego sezonu podatkowego.

### Błąd 3: Kodowanie pliku

Plik XML musi być w kodowaniu **UTF-8**. Jeśli edytujesz plik ręcznie w edytorze, który zapisuje w innym kodowaniu (np. Windows-1250), system może odrzucić plik lub źle zinterpretować polskie znaki.

**Rozwiązanie:** Nie edytuj pliku XML ręcznie. Jeśli musisz — użyj edytora z wymuszonym kodowaniem UTF-8 (VS Code, Notepad++).

### Błąd 4: Kwota autoryzacyjna

Podałeś złą kwotę przychodu z zeszłorocznej deklaracji. System odrzuca autoryzację.

**Rozwiązanie:** Znajdź zeszłoroczny PIT (37 lub 38) i sprawdź dokładną kwotę. Jeśli nie składałeś — wpisz 0. Jeśli składałeś korektę — podaj kwotę z korekty, nie z pierwotnej deklaracji.

### Błąd 5: Brak wymaganych załączników

Jeśli masz dywidendy zagraniczne, PIT/ZG jest wymagany. Plik XML bez PIT/ZG, gdy deklarujesz dochody zagraniczne, zostanie odrzucony lub przyjęty z błędami.

**Rozwiązanie:** TaxPilot automatycznie generuje PIT/ZG, gdy wykryje transakcje od brokerów zagranicznych.

### Błąd 6: Niezgodność kwot w załączniku

Jeśli PIT-38 deklaruje dochody zagraniczne, a kwoty w PIT/ZG nie odpowiadają kwotom w części głównej formularza — system może wyświetlić ostrzeżenie lub odrzucić deklarację. Sumy z PIT/ZG muszą być spójne z częścią F PIT-38.

**Rozwiązanie:** Przed wysłaniem sprawdź, czy kwoty w PIT/ZG (dochody i podatek zapłacony za granicą) odpowiadają temu, co wpisujesz w głównym formularzu. TaxPilot generuje oba formularze z tych samych danych, więc spójność jest zagwarantowana.

### Błąd 7: Podwójne złożenie (Twój e-PIT + XML)

Nie jest to błąd techniczny, ale organizacyjny. Jeśli zaakceptujesz wstępną wersję w Twój e-PIT, a potem wyślesz własny XML — XML nadpisze wersję z Twój e-PIT. Problem pojawia się, gdy wysyłasz XML **przed** zaakceptowaniem Twój e-PIT, a potem nieświadomie akceptujesz wersję Twój e-PIT — nadpisując poprawny XML.

**Rozwiązanie:** Wybierz jedną ścieżkę i trzymaj się jej. Jeśli wysyłasz własny XML — nie zatwierdzaj Twój e-PIT.

## Bezpieczeństwo pliku XML

Plik XML z PIT-38 zawiera Twoje dane osobowe (PESEL, imię, nazwisko) i dane finansowe. Traktuj go jak poufny dokument:

- **Nie wysyłaj pliku XML mailem** bez szyfrowania
- **Nie przechowuj na publicznych dyskach** (Google Drive z linkiem publicznym itp.)
- **Usuń po wysłaniu** lub przechowuj w bezpiecznym miejscu (zaszyfrowany dysk, menedżer haseł z załącznikami)
- **Zachowaj kopię** na wypadek, gdybyś musiał złożyć korektę

## Walidacja XML przed wysłaniem

Zanim wyślesz plik, warto go zwalidować offline. Masz kilka opcji:

1. **Bramka e-Deklaracji** — walidacja odbywa się po załadowaniu pliku, jeszcze przed wysłaniem.
2. **Narzędzia online** — np. walidatory XML, które sprawdzają zgodność ze schematem XSD.
3. **TaxPilot** — waliduje plik na etapie generowania. Jeśli pobrałeś XML z TaxPilot, jest już zwalidowany.

## Jak TaxPilot generuje XML

TaxPilot generuje plik XML w kilku krokach:

1. **Obliczenie danych** — na podstawie wgranych plików CSV oblicza przychody, koszty, FIFO, dywidendy, WHT.
2. **Mapowanie na pola formularza** — każda obliczona wartość trafia do odpowiedniego pola PIT-38 (P_22, P_23, P_24 itd.).
3. **Generowanie PIT/ZG** — jeśli są dywidendy zagraniczne, tworzony jest załącznik z rozbiciem na kraje.
4. **Walidacja XSD** — plik jest sprawdzany pod kątem zgodności z aktualnym schematem Ministerstwa Finansów.
5. **Eksport** — pobierasz gotowy plik `.xml`.

Cały proces trwa kilka sekund. Plik jest gotowy do wysłania przez bramkę e-Deklaracji.

[Wygeneruj PIT-38 XML z TaxPilot →](https://taxpilot.pl)

---

## Najczęstsze pytania

### Czy mogę wysłać PIT-38 jako PDF zamiast XML?

Nie przez e-Deklaracje — bramka przyjmuje tylko XML. Możesz wydrukować PDF i złożyć papierowo w urzędzie skarbowym, ale to mniej wygodne i wolniejsze.

### Czy potrzebuję podpisu elektronicznego?

Nie, jeśli autoryzujesz się danymi (PESEL + kwota przychodu z zeszłego roku). Podpis kwalifikowany lub profil zaufany to opcja, nie wymóg.

### Wysłałem XML, ale znalazłem błąd — co teraz?

Składasz korektę. Generujesz nowy XML z poprawionymi danymi, ustawiasz cel złożenia na "korekta" (zamiast "złożenie") i wysyłasz ponownie. Korektę możesz złożyć w dowolnym momencie.

### Czy TaxPilot generuje XML zgodny z aktualnym schematem?

Tak. Schemat jest aktualizowany na początku każdego sezonu podatkowego (styczeń/luty), gdy Ministerstwo Finansów publikuje nowe wzory formularzy.

### Mam PIT/ZG — czy to osobny plik XML?

Nie. PIT/ZG jest częścią tego samego pliku XML co PIT-38. Jeden plik, jedna wysyłka.

---

*Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego. W przypadku wątpliwości skonsultuj się z doradcą podatkowym.*
