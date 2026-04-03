# Regulamin serwisu TaxPilot

**DRAFT -- do weryfikacji przez radce prawnego**

*Wersja: 1.0-DRAFT*
*Data: 2026-04-02*

---

## I. Definicje

1. **Usługodawca** -- [NAZWA FIRMY], z siedziba w [ADRES], NIP: [NIP], REGON: [REGON], wpisana do [CEIDG/KRS pod numerem ...].
2. **Serwis** -- aplikacja internetowa TaxPilot dostepna pod adresem [https://taxpilot.pl], umozliwiajaca kalkulacje podatku PIT-38 z inwestycji zagranicznych.
3. **Usługa** -- usługa swiadczona droga elektroniczna w rozumieniu ustawy z dnia 18 lipca 2002 r. o swiadczeniu usług droga elektroniczna (Dz.U. z 2020 r. poz. 344 z pozn. zm.), polegajaca na udostepnieniu funkcjonalnosci Serwisu.
4. **Uzytkownik** -- osoba fizyczna, która dokonała rejestracji w Serwisie i zaakceptowała niniejszy Regulamin.
5. **Konto** -- indywidualne konto Uzytkownika w Serwisie, utworzone w procesie rejestracji, chronione mechanizmem magic link.
6. **Deklaracja** -- wygenerowany przez Serwis plik XML z danymi niezbednymi do złozenia zeznania PIT-38, na podstawie danych wprowadzonych przez Uzytkownika.
7. **Transakcja** -- pojedyncza operacja zakupu lub sprzedazy instrumentu finansowego, zaimportowana do Serwisu przez Uzytkownika.
8. **Plan** -- wybrany przez Uzytkownika model dostepu do Serwisu (Free, Standard, Pro).

---

## II. Postanowienia ogólne

1. Niniejszy Regulamin okresla rodzaj, zakres i warunki swiadczenia Usług droga elektroniczna przez Usługodawce za posrednictwem Serwisu, zgodnie z art. 8 ust. 1 pkt 1 ustawy z dnia 18 lipca 2002 r. o swiadczeniu usług droga elektroniczna.
2. Korzystanie z Serwisu wymaga akceptacji niniejszego Regulaminu oraz Polityki Prywatnosci.
3. Wymagania techniczne: przegladarka internetowa z obsługa JavaScript, aktywne konto poczty elektronicznej.

---

## III. Rodzaj i zakres Usług

1. Serwis udostepnia nastepujace funkcjonalnosci:
   - a) import danych transakcyjnych z plików CSV generowanych przez brokerów zagranicznych;
   - b) kalkulacja podatku PIT-38, w tym przeliczenie walut obcych na PLN według kursów NBP;
   - c) generowanie pliku XML zgodnego ze schematem e-Deklaracji PIT-38;
   - d) przegladanie historii rozliczen i transakcji.

2. Serwis oferuje nastepujace Plany:
   - a) **Free** -- do 30 Transakcji, bez opłat;
   - b) **Standard** -- 99 PLN brutto, rozszerzony limit Transakcji;
   - c) **Pro** -- 199 PLN brutto, pełna funkcjonalnosc.

3. Szczegółowy zakres funkcjonalnosci kazdego Planu dostepny jest na stronie cennika w Serwisie.

---

## IV. Warunki korzystania z Serwisu

1. Rejestracja w Serwisie wymaga podania adresu e-mail.
2. Logowanie do Serwisu odbywa sie za pomoca mechanizmu magic link -- jednorazowego łacza uwierzytelniajacego wysyłanego na adres e-mail Uzytkownika. Serwis nie przechowuje haseł.
3. Uzytkownik zobowiazuje sie do podania prawdziwych i aktualnych danych osobowych.
4. Uzytkownik akceptuje Regulamin oraz Polityke Prywatnosci w momencie rejestracji.
5. Jedno Konto moze byc przypisane wyłacznie do jednego Uzytkownika.

---

## V. Obowiazki Uzytkownika

1. Uzytkownik ponosi wyłaczna odpowiedzialnosc za prawidłowosc, kompletnosc i zgodnosc z prawda danych wprowadzonych do Serwisu, w tym danych transakcyjnych importowanych z plików CSV.
2. Uzytkownik zobowiazuje sie do korzystania z Serwisu zgodnie z obowiazujacym prawem, niniejszym Regulaminem oraz dobrymi obyczajami.
3. Uzytkownik nie moze udostepniac dostepu do swojego Konta osobom trzecim.
4. Uzytkownik zobowiazuje sie do niezwłocznego powiadomienia Usługodawcy o kazdym przypadku nieautoryzowanego dostepu do Konta.

---

## VI. Wyłaczenie odpowiedzialnosci

1. **TaxPilot jest narzedziem kalkulacyjnym. Serwis NIE stanowi doradztwa podatkowego w rozumieniu art. 2 ustawy z dnia 5 lipca 1996 r. o doradztwie podatkowym (Dz.U. z 2021 r. poz. 2117 z pozn. zm.). Usługodawca nie jest doradca podatkowym w rozumieniu tej ustawy.**
2. **Odpowiedzialnosc za prawidłowosc zeznania podatkowego PIT-38 ponosi wyłacznie podatnik (Uzytkownik). Wygenerowana Deklaracja stanowi jedynie pomoc kalkulacyjna i nie zwalnia Uzytkownika z obowiazku weryfikacji jej poprawnosci.**
3. Usługodawca nie ponosi odpowiedzialnosci za:
   - a) błedy w kalkulacji wynikajace z nieprawidłowych, niekompletnych lub nieaktualnych danych wprowadzonych przez Uzytkownika;
   - b) błedy w plikach CSV dostarczonych przez brokerów;
   - c) konsekwencje podatkowe lub prawne wynikajace z korzystania z Serwisu;
   - d) szkody wynikajace z przerw w działaniu Serwisu spowodowanych siła wyzsza, konserwacja techniczna lub działaniem osób trzecich;
   - e) zmiany w przepisach podatkowych, które nastapily po wygenerowaniu Deklaracji.
4. Usługodawca rekomenduje weryfikacje wygenerowanej Deklaracji przez doradce podatkowego lub biegłego rewidenta przed jej złozeniem.

---

## VII. Płatnosci

1. Płatnosci za Plany płatne (Standard, Pro) realizowane sa za posrednictwem operatora płatnosci Stripe (Stripe, Inc.).
2. Ceny podane w Serwisie sa cenami brutto (zawieraja podatek VAT).
3. Usługodawca wystawia faktury VAT na zadanie Uzytkownika, na podstawie danych podanych w profilu Uzytkownika (w tym NIP).
4. Dostep do płatnych funkcjonalnosci jest aktywowany po potwierdzeniu otrzymania płatnosci przez operatora płatnosci.

---

## VIII. Prawo odstapienia od umowy

1. Uzytkownik bedacy konsumentem ma prawo odstapic od umowy w terminie 14 dni od dnia zawarcia umowy, bez podawania przyczyny, zgodnie z art. 27 ustawy z dnia 30 maja 2014 r. o prawach konsumenta (Dz.U. z 2020 r. poz. 287 z pozn. zm.).
2. Uzytkownik traci prawo odstapienia od umowy w momencie, gdy Usługa została w pełni wykonana za wyrazna zgoda Uzytkownika -- w szczegolnosci po wygenerowaniu Deklaracji PIT-38 -- zgodnie z art. 38 pkt 1 ustawy o prawach konsumenta.
3. Przed rozpoczeciem swiadczenia Usługi Uzytkownik wyrazna zgode na rozpoczecie wykonywania Usługi przed upływem terminu do odstapienia od umowy oraz potwierdza, ze został poinformowany o utracie prawa odstapienia w przypadku pełnego wykonania Usługi.
4. Oswiadczenie o odstapienie od umowy moze byc złozone drogą elektroniczna na adres: [EMAIL].

---

## IX. Reklamacje

1. Uzytkownik ma prawo złozyc reklamacje dotyczaca działania Serwisu.
2. Reklamacje nalezy składac drogą elektroniczna na adres: [EMAIL].
3. Reklamacja powinna zawierac: dane identyfikujace Uzytkownika (adres e-mail przypisany do Konta), opis problemu oraz oczekiwany sposob rozwiazania.
4. Usługodawca rozpatruje reklamacje w terminie 14 dni od dnia jej otrzymania i informuje Uzytkownika o wyniku rozpatrzenia droga elektroniczna.

---

## X. Usunięcie Konta

1. Uzytkownik ma prawo w kazdym czasie zadac usuniecia Konta wraz ze wszystkimi powiazanymi danymi osobowymi, zgodnie z art. 17 Rozporządzenia Parlamentu Europejskiego i Rady (UE) 2016/679 (RODO).
2. Zadanie usuniecia Konta moze byc złozone za posrednictwem ustawien Konta w Serwisie lub drogą elektroniczna na adres: [EMAIL].
3. Usługodawca realizuje zadanie usuniecia Konta niezwłocznie, nie pozniej niz w terminie 30 dni od dnia otrzymania zadania.
4. Usuniecie Konta jest nieodwracalne. Usługodawca zastrzega sobie prawo do przechowywania danych niezbednych do realizacji obowiazkow prawnych (np. danych fakturowych) przez okres wymagany przepisami prawa.

---

## XI. Zmiany Regulaminu

1. Usługodawca zastrzega sobie prawo do zmiany Regulaminu z waznych przyczyn, w szczegolnosci w przypadku:
   - a) zmiany przepisow prawa majacych wpływ na swiadczenie Usług;
   - b) zmiany zakresu lub sposobu swiadczenia Usług;
   - c) zmiany warunków technicznych swiadczenia Usług.
2. O kazdej zmianie Regulaminu Uzytkownik zostanie powiadomiony drogą elektroniczna (na adres e-mail przypisany do Konta) z co najmniej 14-dniowym wyprzedzeniem.
3. Uzytkownik, który nie akceptuje zmienionego Regulaminu, ma prawo wypowiedziec umowe (usunac Konto) do dnia wejscia w zycie zmian. Dalsze korzystanie z Serwisu po wejsciu w zycie zmian oznacza akceptacje zmienionego Regulaminu.

---

## XII. Postanowienia koncowe

1. Prawem własciwym dla umowy miedzy Usługodawca a Uzytkownikiem jest prawo polskie.
2. Wszelkie spory wynikajace z korzystania z Serwisu beda rozstrzygane przez sad własciwym miejscowo dla siedziby Usługodawcy, z zastrzezeniem bezwzglednie obowiazujacych przepisow dotyczacych własciwosci sadu dla konsumentów.
3. W sprawach nieuregulowanych niniejszym Regulaminem zastosowanie maja przepisy prawa polskiego, w szczegolnosci Kodeksu cywilnego, ustawy o swiadczeniu usług droga elektroniczna, ustawy o prawach konsumenta oraz RODO.
4. Regulamin wchodzi w zycie z dniem [DATA].

---

*DRAFT -- dokument wymaga weryfikacji przez radce prawnego przed publikacja.*
