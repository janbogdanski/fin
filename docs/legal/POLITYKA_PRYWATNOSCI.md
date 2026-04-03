# Polityka Prywatnosci serwisu TaxPilot

**DRAFT -- do weryfikacji przez radce prawnego**

*Wersja: 1.1-DRAFT*
*Data: 2026-04-02*

---

## I. Administrator danych osobowych

Administratorem danych osobowych jest [NAZWA FIRMY], z siedziba w [ADRES], NIP: [NIP], REGON: [REGON] (dalej: **Administrator**).

Kontakt z Administratorem: [EMAIL], [ADRES].

Administrator nie wyznaczył Inspektora Ochrony Danych (IOD) z uwagi na to, ze nie zachodzi przesłanka obligatoryjnego wyznaczenia IOD okreslona w art. 37 ust. 1 RODO. Osoba kontaktowa w sprawach ochrony danych osobowych jest [IMIE I NAZWISKO / STANOWISKO], dostepna pod adresem e-mail: [EMAIL_IOD].

---

## II. Zakres zbieranych danych

### A. Dane podawane przez Uzytkownika

Administrator przetwarza nastepujace dane osobowe podawane bezposrednio przez Uzytkowników:

| Kategoria danych | Szczegóły | Obowiazkowe / dobrowolne | Konsekwencja niepodania |
|---|---|---|---|
| Dane identyfikacyjne | adres e-mail | Obowiazkowe | Brak mozliwosci rejestracji i korzystania z Serwisu |
| Dane identyfikacyjne (profil) | imie, nazwisko | Dobrowolne (obowiazkowe dla wystawienia faktury) | Brak mozliwosci wystawienia faktury VAT |
| Dane podatkowe | NIP (zaszyfrowany algorytmem AES-256-GCM) | Dobrowolne (obowiazkowe dla wystawienia faktury z NIP) | Brak mozliwosci umieszczenia NIP na fakturze |
| Dane transakcyjne | dane dotyczace transakcji zakupu i sprzedazy instrumentów finansowych importowane z plików CSV (daty, kwoty, waluty, nazwy instrumentów) | Obowiazkowe do wykonania kalkulacji | Brak mozliwosci wygenerowania Deklaracji PIT-38 |
| Dane fakturowe | dane niezbedne do wystawienia faktury VAT (jezeli Uzytkownik zada faktury) | Dobrowolne | Brak mozliwosci wystawienia faktury VAT |

### B. Dane zbierane automatycznie

Nastepujace dane sa zbierane automatycznie podczas korzystania z Serwisu:

| Kategoria danych | Szczegóły | Cel |
|---|---|---|
| Dane techniczne | adres IP, identyfikator sesji (session cookie), typ przegladarki, system operacyjny | Bezpieczenstwo, utrzymanie sesji |
| Dane analityczne (Plausible) | zagregowane dane statystyczne (odwiedzane strony, zródło ruchu, kraj) -- **bez danych osobowych** | Analityka uzytkowania Serwisu |

---

## III. Cele i podstawy prawne przetwarzania danych

| Cel przetwarzania | Zakres danych | Podstawa prawna (RODO) |
|---|---|---|
| Swiadczenie Usługi (rejestracja, logowanie, import transakcji, kalkulacja PIT-38, generowanie XML) | e-mail, imie, nazwisko, NIP, dane transakcyjne | Art. 6 ust. 1 lit. b) -- wykonanie umowy |
| Rozliczenie podatkowe (przeliczanie walut, obliczanie podatku) | NIP, dane transakcyjne | Art. 6 ust. 1 lit. b) -- wykonanie umowy |
| Wystawianie faktur VAT | imie, nazwisko, NIP, adres e-mail | Art. 6 ust. 1 lit. c) -- obowiazek prawny (przepisy podatkowe i rachunkowe) |
| Bezpieczenstwo Serwisu (wykrywanie nadużyć, ochrona przed nieautoryzowanym dostępem) | adres IP, identyfikator sesji, dane techniczne | Art. 6 ust. 1 lit. f) -- prawnie uzasadniony interes Administratora (LIA: interes Administratora w zapewnieniu bezpieczenstwa Serwisu i ochronie przed naduzyciam przewaza nad interesami Uzytkowników, poniewaz przetwarzanie ogranicza sie do danych technicznych niezbednych do tego celu) |
| Analityka uzytkowania Serwisu (Plausible Analytics) | zagregowane dane statystyczne -- **Plausible nie przetwarza danych osobowych**, nie uzywa cookies, nie tworzy profili Uzytkowników | Nie wymaga podstawy prawnej z RODO (brak przetwarzania danych osobowych) |
| Obsługa reklamacji i korespondencja | e-mail, imie, nazwisko | Art. 6 ust. 1 lit. b) -- wykonanie umowy; art. 6 ust. 1 lit. f) -- prawnie uzasadniony interes (LIA: interes w prawidłowej obsłudze reklamacji) |
| Ewentualne dochodzenie roszczen lub obrona przed roszczeniami | dane identyfikacyjne, dane transakcyjne, dane fakturowe | Art. 6 ust. 1 lit. f) -- prawnie uzasadniony interes Administratora (LIA: interes w mozliwosci dochodzenia roszczen lub obrony przed roszczeniami jest uzasadniony i proporcjonalny) |

---

## IV. Okres przechowywania danych

| Kategoria danych | Okres przechowywania |
|---|---|
| Dane podatkowe i transakcyjne | 5 lat po zakonczeniu roku podatkowego, którego dotycza -- zgodnie z art. 86 par. 1 Ordynacji podatkowej |
| Dane Konta (e-mail, imie, nazwisko) | Do momentu usuniecia Konta przez Uzytkownika lub do momentu realizacji zadania usuniecia danych |
| Dane fakturowe | 5 lat od konca roku kalendarzowego, w którym upłynał termin płatnosci podatku -- zgodnie z przepisami o rachunkowosci |
| Dane techniczne (IP, sesje) | Maksymalnie 12 miesiecy od daty zebrania |
| NIP (zaszyfrowany) | Do momentu usuniecia Konta lub do upływu okresu przechowywania danych podatkowych (w zaleznosci co nastapi pózniej) |

Po upływie okresów przechowywania dane sa nieodwracalnie usuwane lub skutecznie anonimizowane.

---

## V. Odbiorcy danych

Dane osobowe moga byc przekazywane nastepujacym kategoriom odbiorców:

| Odbiorca | Cel | Lokalizacja | Zabezpieczenia transferu |
|---|---|---|---|
| **Stripe, Inc.** | Obsługa płatnosci | USA / EEA | Certyfikacja w ramach EU-U.S. Data Privacy Framework (DPF) zgodnie z art. 45 RODO (decyzja wykonawcza Komisji Europejskiej z dnia 10 lipca 2023 r.) jako podstawowy mechanizm transferu; dodatkowo Standardowe Klauzule Umowne (SCC) zgodnie z art. 46 ust. 2 lit. c) RODO jako zabezpieczenie zapasowe na wypadek uniewaznenia decyzji o adekwatnosci. Lista sub-procesorów Stripe dostepna pod adresem: [https://stripe.com/legal/service-providers](https://stripe.com/legal/service-providers) |
| **MyDevil.net** (Jelenkowski Spółka Jawna) | Hosting Serwisu | Polska (EEA) | Umowa powierzenia przetwarzania danych (art. 28 RODO) |
| **[DOSTAWCA E-MAIL]** | Wysyłka wiadomosci e-mail (magic link, powiadomienia, faktury) | [LOKALIZACJA] | Umowa powierzenia przetwarzania danych (art. 28 RODO); [DPF/SCC jezeli poza EEA] |
| Organy panstowe | Na podstawie przepisow prawa (np. organy podatkowe, sady) | Polska | Obowiazek prawny (art. 6 ust. 1 lit. c) RODO) |

Administrator nie sprzedaje danych osobowych podmiotom trzecim. Administrator nie przekazuje danych do panstw trzecich spoza Europejskiego Obszaru Gospodarczego, z wyjatkiem transferów opisanych powyzej, zabezpieczonych w sposób wskazany w tabeli.

---

## VI. Prawa Uzytkownika

Na podstawie RODO Uzytkownikowi przysługuja nastepujace prawa:

1. **Prawo dostepu do danych** (art. 15 RODO) -- prawo do uzyskania informacji o przetwarzanych danych oraz kopii danych.
2. **Prawo do sprostowania** (art. 16 RODO) -- prawo do zadania poprawienia nieprawidłowych lub uzupełnienia niekompletnych danych.
3. **Prawo do usuniecia danych** (art. 17 RODO) -- prawo do zadania usuniecia danych, z zastrzezeniem obowiazkow prawnych Administratora (np. przechowywanie danych podatkowych).
4. **Prawo do ograniczenia przetwarzania** (art. 18 RODO) -- prawo do zadania ograniczenia przetwarzania danych w okreslonych przypadkach.
5. **Prawo do przenoszenia danych** (art. 20 RODO) -- prawo do otrzymania danych w ustrukturyzowanym, powszechnie uzywanym formacie nadajacym sie do odczytu maszynowego (JSON/CSV).
6. **Prawo do sprzeciwu** (art. 21 RODO) -- prawo do wniesienia sprzeciwu wobec przetwarzania opartego na prawnie uzasadnionym interesie Administratora.
7. **Prawo do cofniecia zgody** (art. 7 ust. 3 RODO) -- w przypadku gdy przetwarzanie odbywa sie na podstawie zgody, Uzytkownik ma prawo do cofniecia zgody w dowolnym momencie, bez wpływu na zgodnosc z prawem przetwarzania dokonanego przed cofnieciem zgody.
8. **Prawo do niepodlegania decyzji opartej wyłacznie na zautomatyzowanym przetwarzaniu** (art. 22 RODO) -- prawo do tego, by nie podlegac decyzji, która opiera sie wyłacznie na zautomatyzowanym przetwarzaniu, w tym profilowaniu, i wywołuje wobec Uzytkownika skutki prawne lub w podobny sposób istotnie na niego wpływa. Administrator informuje, ze kalkulacja PIT-38 nie stanowi zautomatyzowanej decyzji w rozumieniu art. 22 RODO -- jest wyłacznie pomoca obliczeniowa, a ostateczna decyzje o złozeniu zeznania podejmuje Uzytkownik.
9. **Prawo do wniesienia skargi** -- prawo do złozenia skargi do Prezesa Urzedu Ochrony Danych Osobowych (ul. Stawki 2, 00-193 Warszawa, www.uodo.gov.pl).

W celu skorzystania z powyzszych praw nalezy skontaktowac sie z Administratorem pod adresem: [EMAIL].

Administrator rozpatruje zadania niezwłocznie, nie pózniej niz w terminie 30 dni od dnia otrzymania zadania. W przypadku skomplikowanych zadan termin moze zostac przedłuzony o kolejne 60 dni, o czym Uzytkownik zostanie poinformowany.

---

## VII. Pliki cookies i technologie śledzace

1. Serwis wykorzystuje wyłacznie niezbedne techniczne pliki cookies (session cookies) konieczne do prawidłowego działania Serwisu, w szczegolnosci do utrzymania sesji Uzytkownika.
2. Serwis korzysta z Plausible Analytics -- narzedzia analitycznego, które **nie uzywa plików cookies** i nie zbiera danych osobowych. Plausible przetwarza wyłacznie zagregowane dane statystyczne.
3. Uzytkownik moze skonfigurowac przegladarke w sposób uniemozliwiajacy przechowywanie plików cookies na urzadzeniu koncowym, co moze jednak ograniczyc funkcjonalnosc Serwisu.

---

## VIII. Zabezpieczenia danych

Administrator stosuje nastepujace srodki techniczne i organizacyjne w celu ochrony danych osobowych:

1. **Szyfrowanie NIP** -- numer NIP jest przechowywany w postaci zaszyfrowanej algorytmem AES-256-GCM (szyfrowanie symetryczne z uwierzytelnianiem).
2. **Szyfrowanie transmisji** -- cała komunikacja z Serwisem odbywa sie za posrednictwem protokołu HTTPS (TLS 1.2+).
3. **Tokeny uwierzytelniajace** -- tokeny magic link sa hashowane algorytmem SHA-256 przed zapisem w bazie danych. Tokeny maja ograniczony czas waznosci.
4. **Nagłówki bezpieczenstwa** -- Serwis stosuje nagłówki Content-Security-Policy (CSP), X-Content-Type-Options, X-Frame-Options, Strict-Transport-Security (HSTS).
5. **Kontrola dostepu** -- dostep do danych osobowych jest ograniczony do minimum niezbednego do swiadczenia Usługi (zasada minimalizacji danych, art. 5 ust. 1 lit. c) RODO).
6. **Kopie zapasowe** -- regularne tworzenie zaszyfrowanych kopii zapasowych bazy danych.
7. **Monitoring** -- monitorowanie dostepnosci Serwisu i prób nieautoryzowanego dostepu.
8. **Regularne testy bezpieczenstwa** -- Administrator przeprowadza regularne testy bezpieczenstwa Serwisu, w tym przegłady konfiguracji, testy podatnosci oraz audyty kodu zródłowego, w celu identyfikacji i eliminacji potencjalnych zagrozeni dla bezpieczenstwa danych osobowych.

---

## IX. Profilowanie i zautomatyzowane podejmowanie decyzji

Administrator nie stosuje profilowania ani zautomatyzowanego podejmowania decyzji w rozumieniu art. 22 RODO, które wywołowałoby skutki prawne wobec Uzytkownika lub w podobny sposób istotnie na niego wpływało.

Kalkulacja podatku PIT-38 stanowi wyłacznie pomoc obliczeniowa i nie jest decyzja zautomatyzowana -- ostateczna decyzje o złozeniu zeznania podejmuje Uzytkownik.

---

## X. Zmiany Polityki Prywatnosci

1. Administrator zastrzega sobie prawo do zmiany niniejszej Polityki Prywatnosci w przypadku zmian przepisów prawa, zmian zakresu swiadczonych Usług lub zmian w stosowanych srodkach technicznych.
2. O kazdej istotnej zmianie Uzytkownik zostanie powiadomiony droga elektroniczna z co najmniej 14-dniowym wyprzedzeniem.
3. Aktualna wersja Polityki Prywatnosci jest zawsze dostepna w Serwisie.

---

## XI. Kontakt

W sprawach dotyczacych ochrony danych osobowych nalezy kontaktowac sie z Administratorem:

- **E-mail:** [EMAIL]
- **Adres korespondencyjny:** [ADRES]
- **Osoba kontaktowa ds. ochrony danych:** [IMIE I NAZWISKO / STANOWISKO], [EMAIL_IOD]

---

## Załacznik: Klauzula informacyjna (skrócona wersja do formularzy)

Ponizszą klauzule nalezy wyswietlac przy formularzach rejestracji i edycji profilu:

> Administratorem Twoich danych osobowych jest [NAZWA FIRMY] z siedziba w [ADRES]. Dane przetwarzane sa w celu swiadczenia usługi TaxPilot (kalkulacja PIT-38), na podstawie art. 6 ust. 1 lit. b) RODO. Przysługuje Ci prawo dostepu, sprostowania, usuniecia, ograniczenia przetwarzania, przenoszenia danych, sprzeciwu wobec przetwarzania oraz prawo wniesienia skargi do Prezesa UODO. Szczegóły w [Polityce Prywatnosci](link).

---

*DRAFT -- dokument wymaga weryfikacji przez radce prawnego przed publikacja.*
