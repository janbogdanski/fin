# Opinia prawna: TaxPilot jako narzedzie informatyczne a doradztwo podatkowe

**DRAFT -- do weryfikacji przez radce prawnego**

*Data: 2026-04-02*

---

## I. Przedmiot opinii

Niniejsza opinia dotyczy kwalifikacji prawnej usługi swiadczonej za posrednictwem serwisu TaxPilot w kontekscie ustawy z dnia 5 lipca 1996 r. o doradztwie podatkowym (Dz.U. z 2021 r. poz. 2117 z pozn. zm.) -- w szczegolnosci oceny, czy działalnosc TaxPilot stanowi doradztwo podatkowe w rozumieniu art. 2 tej ustawy.

---

## II. Stan faktyczny

Serwis TaxPilot:

1. Umozliwia import danych transakcyjnych z plików CSV generowanych przez brokerów zagranicznych.
2. Przelicza waluty obce na PLN według kursów NBP (kurs z dnia poprzedzajacego dzien transakcji).
3. Oblicza przychód, koszty uzyskania przychodu i dochód/strate z odpłatnego zbycia papierów wartosciowych i instrumentów pochodnych.
4. Generuje plik XML zgodny ze schematem e-Deklaracji PIT-38.
5. Nie udziela rekomendacji dotyczacych optymalizacji podatkowej.
6. Nie interpretuje przepisów prawa podatkowego.
7. Nie dokonuje kwalifikacji prawnej transakcji (np. nie ocenia, czy dana transakcja podlega opodatkowaniu).
8. Nie wydaje opinii w sprawach podatkowych.

---

## III. Analiza prawna

### A. Zakres doradztwa podatkowego (art. 2 ustawy)

Zgodnie z art. 2 ust. 1 ustawy o doradztwie podatkowym, czynnosci doradztwa podatkowego obejmuja:

1. Udzielanie podatnikom, płatnikom i inkasentom, na ich zlecenie lub na ich rzecz, porad, opinii i wyjasnien z zakresu ich obowiazkow podatkowych i celnych oraz w sprawach egzekucji administracyjnej zwiazanej z tymi obowiazkami (pkt 1).
2. Prowadzenie, w imieniu i na rzecz podatników, płatników i inkasentów, ksiag rachunkowych, ksiag podatkowych i innych ewidencji do celów podatkowych oraz udzielanie im pomocy w tym zakresie (pkt 2).
3. Sporzadzanie, w imieniu i na rzecz podatników, płatników i inkasentów, zeznań i deklaracji podatkowych lub udzielanie im pomocy w tym zakresie (pkt 3).
4. Reprezentowanie podatników, płatników i inkasentów w postepowaniu przed organami administracji publicznej i w zakresie sadowej kontroli decyzji, postanowien i innych aktów administracyjnych (pkt 4).

### B. TaxPilot a poszczególne czynnosci doradztwa

**Ad pkt 1 -- porady, opinie, wyjasnienia:**
TaxPilot nie udziela porad, opinii ani wyjasnien z zakresu obowiazkow podatkowych. Serwis nie odpowiada na pytania uzytkowników, nie analizuje ich indywidualnej sytuacji podatkowej, nie interpretuje przepisów. Wykonuje wyłacznie operacje arytmetyczne na danych liczbowych wprowadzonych przez uzytkownika.

**Ad pkt 2 -- prowadzenie ksiag:**
TaxPilot nie prowadzi ksiag rachunkowych, podatkowych ani ewidencji. Przechowuje dane transakcyjne wyłacznie w celu wykonania kalkulacji.

**Ad pkt 3 -- sporzadzanie zeznan:**
To jest punkt wymagajacy najdokładniejszej analizy. TaxPilot generuje plik XML zgodny ze schematem PIT-38, jednak:

- Serwis nie sporzadza zeznania "w imieniu i na rzecz" podatnika -- to uzytkownik samodzielnie inicjuje proces, wprowadza dane i podejmuje decyzje o złozeniu zeznania.
- Serwis pełni funkcje analogiczna do kalkulatora lub arkusza kalkulacyjnego -- przetwarza dane liczbowe według zdefiniowanego algorytmu.
- Ostateczna odpowiedzialnosc za tresc zeznania spoczywa na uzytkowniku, który musi samodzielnie zweryfikowac poprawnosc wyników i podjac decyzje o złozeniu zeznania w urzedzie skarbowym.
- Analogia: Ministerstwo Finansów udostepnia usługe Twój e-PIT, która równiez automatycznie wypełnia zeznania podatkowe na podstawie danych z systemów informatycznych -- i nie jest to traktowane jako doradztwo podatkowe.

**Ad pkt 4 -- reprezentowanie:**
TaxPilot nie reprezentuje uzytkowników przed zadnymi organami.

### C. Kluczowe rozróznienie: narzedzie obliczeniowe vs doradztwo

Istotne cechy odróniajace TaxPilot od doradztwa podatkowego:

1. **Brak interpretacji przepisów** -- algorytm implementuje konkretne wzory obliczeniowe (przychód minus koszt, przeliczenie walutowe), nie interpretuje, czy dana transakcja podlega opodatkowaniu ani w jaki sposób.
2. **Brak indywidualnej analizy** -- serwis nie analizuje indywidualnej sytuacji podatkowej uzytkownika, nie uwzglednia ulg, zwolnien ani szczególnych okolicznosci wymagajacych interpretacji prawnej.
3. **Brak elementu doradczego** -- serwis nie rekomenduje konkretnych rozwiazan, nie sugeruje optymalizacji, nie ocenia ryzyk podatkowych.
4. **Mechaniczny charakter operacji** -- operacje wykonywane przez serwis maja charakter czysto arytmetyczny (dodawanie, odejmowanie, mnozenie, przeliczanie walut) i moga byc równiez wykonane recznie przez uzytkownika.
5. **Decyzyjnosc po stronie uzytkownika** -- uzytkownik samodzielnie decyduje o wprowadzeniu danych, ich poprawnosci oraz o złozeniu zeznania podatkowego.

### D. Orzecznictwo i stanowiska doktryny

Kwestia kwalifikacji narzedzi informatycznych do obliczen podatkowych nie doczekała sie jednoznacznego orzecznictwa. Nalezy jednak wskazac, ze:

- Programy księgowe (np. Symfonia, Optima) wykonuja analogiczne operacje obliczeniowe i nie sa kwalifikowane jako doradztwo podatkowe.
- Usługa Twój e-PIT Ministerstwa Finansów automatycznie generuje zeznania podatkowe i nie jest traktowana jako doradztwo.
- W doktrynie przyjmuje sie, ze czynnosci czysto techniczne (obliczenia, formatowanie danych) nie stanowia doradztwa podatkowego, o ile nie towarzysza im elementy interpretacyjne lub doradcze.

---

## IV. Wnioski i rekomendacje

### Wniosek główny

TaxPilot, w obecnym kształcie, **nie stanowi doradztwa podatkowego** w rozumieniu art. 2 ustawy o doradztwie podatkowym. Serwis pełni funkcje narzedzia informatycznego do obliczen arytmetycznych -- analogicznie do kalkulatora, arkusza kalkulacyjnego czy programu księgowego.

### Rekomendacje minimalizujace ryzyko prawne

1. **Wyrazny disclaimer** -- w regulaminie i w interfejsie serwisu nalezy jasno komunikowac, ze TaxPilot jest narzedziem kalkulacyjnym i nie stanowi doradztwa podatkowego. **(ZREALIZOWANE w par. VI ust. 1 Regulaminu)**
2. **Brak elementów doradczych** -- nalezy unikac wprowadzania funkcjonalnosci, które mogłyby byc interpretowane jako doradztwo (np. rekomendacje "jak zmniejszyc podatek", sugestie dotyczace ulg podatkowych, interpretacje przepisów).
3. **Rekomendacja weryfikacji** -- nalezy rekomendowac uzytkownikowi weryfikacje wyników przez doradce podatkowego. **(ZREALIZOWANE w par. VI ust. 6 Regulaminu)**
4. **Odpowiedzialnosc uzytkownika** -- nalezy jasno wskazac, ze odpowiedzialnosc za tresc zeznania spoczywa na uzytkowniku. **(ZREALIZOWANE w par. VI ust. 2 Regulaminu)**
5. **Brak indywidualnych porad** -- nalezy unikac udzielania odpowiedzi na pytania uzytkowników dotyczace ich sytuacji podatkowej (np. w ramach supportu). Ewentualny support powinien ograniczac sie do kwestii technicznych zwiazanych z działaniem serwisu.
6. **Monitoring regulacyjny** -- nalezy sledzic ewentualne zmiany w ustawie o doradztwie podatkowym oraz stanowiska Krajowej Izby Doradców Podatkowych, które mogłyby wpłynac na kwalifikacje usługi.

### Ryzyko rezydualne

Ryzyko zakwestionowania kwalifikacji TaxPilot jako narzedzia (a nie doradztwa) jest **niskie**, ale nie zerowe. Głównym czynnikiem ryzyka byłoby:

- Wprowadzenie funkcjonalnosci o charakterze doradczym (np. chatbot odpowiadajacy na pytania podatkowe, sugestie optymalizacji).
- Zmiana przepisów ustawy o doradztwie podatkowym rozszerzajaca zakres czynnosci zastrzezonych.
- Interpretacja organów nadzoru kwalifikujaca generowanie plików XML zeznań podatkowych jako "sporzadzanie zeznan" w rozumieniu art. 2 ust. 1 pkt 3 ustawy.

W celu minimalizacji tego ryzyka kluczowe jest przestrzeganie rekomendacji wskazanych powyzej.

---

## V. Zastrzezenia

Niniejsza opinia ma charakter informacyjny i nie stanowi porady prawnej w indywidualnej sprawie. Opinia oparta jest na stanie prawnym na dzien jej sporzadzenia. W przypadku zmian legislacyjnych lub nowych interpretacji organów nadzoru rekomendowana jest aktualizacja analizy.

---

*DRAFT -- dokument wymaga weryfikacji przez radce prawnego przed publikacja.*
