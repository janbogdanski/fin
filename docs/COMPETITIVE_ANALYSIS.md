# TaxPilot -- Analiza Konkurencji

Data: 2026-04-02

---

## Krajobraz rynkowy

Polski rynek narzedzi do rozliczania PIT-38 z obrotu papierami wartosciowymi jest fragmentaryczny. Brak dominujacego gracza oferujacego pelny audit trail i bezposredni XML do e-Deklaracji.

---

## Konkurenci

### PodatekGieldowy.pl

Glowny konkurent. Najbardziej zaawansowany pod wzgledem obslugi brokerow.

| Cecha | Wartosc |
|---|---|
| Brokerzy | 8 (IBKR, Degiro, Revolut, Exante, eToro, Trading212, Freedom24, Tastytrade) |
| Cennik | Free (5 transakcji FIFO) / Premium 149-199 PLN |
| Format raportu | PDF (NIE XML -- wymaga recznego przepisywania do e-Deklaracji) |
| Autentykacja | Magic link |
| Infrastruktura | AWS Frankfurt |
| Audit trail | Brak |
| Zrodlo | Closed source |

**Slabosci:** Brak XML e-Deklaracje (tylko PDF). Brak sladu rewizyjnego FIFO. Niski free tier (5 tx).

### TaxAll.pl

| Cecha | Wartosc |
|---|---|
| Brokerzy | IBKR, Degiro, Revolut, XTB, eToro, Bossa, Trading212 |
| Cennik | ~149-299 PLN/rok |
| Znane bugi | Bledy w cross-broker FIFO |
| Audit trail | Brak |
| Zrodlo | Closed source |

**Slabosci:** Zglaszane bledy w FIFO przy wielu brokerach. Brak transparentnosci obliczen.

### pit.pl / e-pity.pl

Najwieksze portale PIT w Polsce. Skupione na formularzach, nie na inwestycjach.

| Cecha | Wartosc |
|---|---|
| Import CSV | Brak |
| Kalkulacja FIFO | Brak |
| Cennik | Free / 29-49 PLN |
| Funkcja | Cyfrowe formularze papierowe |

**Slabosci:** Zero automatyzacji dla inwestorow. Uzytkownik musi sam obliczyc FIFO i wpisac kwoty.

### Koinly / CoinTracker

Narzedzia crypto-first z rynku anglosaskiego.

| Cecha | Wartosc |
|---|---|
| PIT-38 XML | Brak |
| Kursy NBP | Brak (USD-centric) |
| Cennik | $49-279/rok |
| Rynek | US/UK, nie PL |

**Slabosci:** Brak wsparcia dla polskiego systemu podatkowego. Brak kursow NBP. Cennik w USD.

### Biura rachunkowe

| Cecha | Wartosc |
|---|---|
| Koszt | 500-2000 PLN za PIT-38 |
| Metoda | Reczny FIFO w Excelu |
| Bledy | Ludzkie, trudne do wykrycia |
| Audit trail | Brak lub szczatkowy |

**Slabosci:** Drogie. Brak gwarancji poprawnosci. Czas realizacji: dni/tygodnie.

### Excel DIY

| Cecha | Wartosc |
|---|---|
| Koszt | 0 PLN |
| Czas | 4-8 godzin |
| Bledy | Wysokie ryzyko (kopiuj-wklej, formuly, zaokraglenia) |
| Audit trail | Brak |

**Slabosci:** Czas, ryzyko bledow, brak walidacji, brak obslugi cross-year FIFO.

---

## Macierz funkcjonalnosci

| Funkcja | TaxPilot | PodatekGieldowy | TaxAll | pit.pl/e-pity | Koinly | Biuro rach. | Excel |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Import CSV | 5+ brokerow | 8 brokerow | 7 brokerow | -- | crypto | reczny | reczny |
| FIFO cross-broker | tak | tak | bugi | -- | -- | reczny | reczny |
| FIFO cross-year | tak | tak | ? | -- | -- | reczny | reczny |
| Dywidendy + WHT | tak | tak | tak | -- | -- | reczny | reczny |
| PIT-38 XML | tak | -- (PDF) | ? | -- | -- | -- | -- |
| PIT/ZG XML | tak | ? | ? | -- | -- | -- | -- |
| Audit trail FIFO | tak | -- | -- | -- | -- | -- | -- |
| Dual-path reconciliation | tak | -- | -- | -- | -- | -- | -- |
| Kursy NBP (D-1) | tak | tak | tak | -- | -- | reczny | reczny |
| Free tier | 30 tx | 5 tx | -- | tak (inny scope) | -- | -- | tak |
| Cena | 99 PLN | 149-199 PLN | 149-299 PLN | 29-49 PLN | $49-279 | 500-2000 PLN | 0 PLN |
| Testy (weryfikowalna jakosc) | 459 | ? | ? | ? | ? | -- | -- |
| Contract testing | tak | ? | ? | ? | ? | -- | -- |

---

## Przewagi TaxPilot

### 1. Audit trail -- slad rewizyjny FIFO

Unikalna funkcja na rynku. Kazda pozycja zamknieta ma pelny lancuch FIFO: skad pochodzi lot, ile sztuk, po jakiej cenie, jaki kurs NBP, jaka prowizja. Uzytkownik moze zweryfikowac kazda zlotowke w deklaracji.

Zaden konkurent tego nie oferuje.

### 2. PIT-38 XML (nie PDF)

Bezposredni upload do e-Deklaracje / e-Urzad Skarbowy. PodatekGieldowy generuje PDF, ktory uzytkownik musi reczne przepisac do formularza online. TaxPilot generuje XML w formacie e-Deklaracje v17.

### 3. Dual-path reconciliation

Podwojne sprawdzanie obliczen -- jak double-entry bookkeeping. Suma otwartych + zamknietych pozycji musi zgadzac sie z importem. Wbudowana samoweryfikacja.

### 4. Weryfikowalna jakosc

459 testow, contract testing (Pact), property tests, golden datasets. Kod otwarty na audyt. Konkurenci to black-boxy.

### 5. Nizsza cena

99 PLN vs 149-199 PLN (PodatekGieldowy) vs 149-299 PLN (TaxAll).

### 6. Wyzszy free tier

30 transakcji vs 5 transakcji FIFO (PodatekGieldowy). Wiekszy hook na przetestowanie przed zakupem.

---

## Podsumowanie strategiczne

TaxPilot celuje w luke: transparentnosc obliczen (audit trail) + gotowy XML. Konkurenci albo generuja PDF (wymaga recznego przepisywania), albo maja znane bugi w FIFO, albo nie obsluguja polskiego systemu w ogole.

Glowne ryzyka:
- PodatekGieldowy moze dodac XML export
- Wejscie nowego gracza z wiekszym budzetem marketingowym
- Regulacje KAS moga zmienic format e-Deklaracji (wymaga aktualizacji generatora)
