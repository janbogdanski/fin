# Event Storming: Aplikacja do Rozliczania Inwestycji w Polsce

## Metadata

| | |
|---|---|
| **Data** | 2026-04-01 — 2026-04-02 |
| **Lokalizacja** | Warszawa, WeWork Mennica Legacy Tower, sala "Brandolini" |
| **Format** | Big Picture → Process Level → Design Level + Business Case |
| **Facylitatorzy** | Alberto Brandolini, Mariusz Gil |
| **Zespół realizacyjny** | Fully Featured AI Agent Team |

---

## Uczestnicy

### Facylitatorzy

| Osoba | Rola | Fokus |
|---|---|---|
| **Alberto Brandolini** | Twórca Event Stormingu, główny facylitator | Big Picture, identyfikacja Bounded Contexts, pilnuje żeby nikt nie rozmawiał o implementacji |
| **Mariusz Gil** | Co-facylitator, DDD expert, software craftsman | Ubiquitous Language, modelowanie domenowe, polskie realia rynku tech. Autor wielu prezentacji o ES i DDD w polskiej społeczności |

### Eksperci domenowi

| Osoba | Rola | Ekspertyza |
|---|---|---|
| **Mec. Katarzyna Wiśniewska** | Radca prawny | Prawo podatkowe, ustawa o PIT, ordynacja podatkowa |
| **Mec. Piotr Nowak** | Prawnik | Umowy o unikaniu podwójnego opodatkowania (UPO), prawo międzynarodowe |
| **Joanna Makowska** | Biegły rewident | Audyt finansowy, realia Urzędów Skarbowych, kontrole |
| **Tomasz Kędzierski** | Doradca podatkowy (DP) | Praktyk, 300+ klientów inwestorów, rozliczenia zagraniczne |

### Fully Featured AI Agent Team

| Agent | Specjalizacja | Rola w sesji |
|---|---|---|
| **Marek** `[senior-dev]` | Backend, DDD, domain modeling | Modelowanie agregatów, Bounded Contexts |
| **Ania** `[data-engineer]` | Pipelines, APIs brokerów, CSV/import | Integracje, formaty danych, quality |
| **Paweł** `[front-engineer]` | Fullstack, UX flow, React | Przepływy użytkownika, UI PIT-38 |
| **Kasia** `[qa-lead]` | Edge cases, compliance testing | Scenariusze brzegowe, walidacja reguł |
| **Michał W.** `[qa-lead #2]` | Automatyzacja, testy regresyjne | Testy reguł podatkowych, regression |
| **Zofia** `[front-engineer]` | UI/UX, accessibility | Formularze, wizualizacja obliczeń |
| **Bartek** `[devops]` | Kubernetes, CI/CD | Infrastruktura, deployment pipeline |
| **Sylwester** `[SRE]` | Reliability, monitoring | SLA obliczeń, disaster recovery |
| **Łukasz** `[risk-manager]` | Ryzyko biznesowe i prawne | Go/No-Go, odpowiedzialność, compliance |
| **Aleksandra** `[performance-engineer]` | Skalowanie, optymalizacja | Obliczenia na dużych wolumenach |
| **Michał P.** `[security-auditor]` | OWASP, GDPR, dane finansowe | Bezpieczeństwo danych, regulacje |

---

# DZIEŃ 1 — Big Picture Event Storming

## 09:00–09:30 — Intro i zasady

**Brandolini** otwiera sesję:

> "Mamy dwa dni. Pierwszy dzień — Big Picture. Żadnego PowerPointa, żadnych diagramów UML. Tylko pomarańczowe kartki na ścianie. Piszecie zdarzenia — rzeczy, które się WYDARZYŁY — w czasie przeszłym. Nie dyskutujecie. Przyklejacie i idziecie dalej. Chaos jest zamierzony."

**Mariusz Gil** dodaje perspektywę DDD:

> "Zanim zaczniemy kleić kartki — umówmy się na jedno: szukamy języka domeny. Nie języka programowania, nie języka UI. Języka, którym mówi doradca podatkowy, którym mówi inwestor. Jeśli Tomasz mówi 'rozliczenie', a Kasia pisze 'kalkulacja podatku' — to musimy wybrać jedno i się tego trzymać. To jest Ubiquitous Language i bez tego wszystko się rozsypie."

**Michał P. [security]** od razu:

> "Zanim zaczniemy — chcę zaznaczyć: będziemy operować na danych finansowych użytkowników. Historii transakcji, stanach kont, NIP-ach. To są dane wrażliwe. Każde zdarzenie, które dotyka danych osobowych, proszę oznaczać małą czerwoną kropką."

**Brandolini** kiwa głową:

> "Dobry punkt. Czerwona kropka = dane wrażliwe. Dodajemy do legendy."

### Legenda kolorów na ścianie:
- 🟠 **Pomarańczowa** — Domain Event (coś się wydarzyło)
- 🔵 **Niebieska** — Command (decyzja/akcja)
- 🟡 **Żółta** — Actor (kto wykonuje)
- 🟡+ramka **Żółta z ramką** — Aggregate
- 🩷 **Różowa** — Hotspot (problem, niejasność, ryzyko)
- 🟢 **Zielona** — Read Model / View
- 🟣 **Fioletowa** — Policy (automatyczna reakcja)
- 🔴 **Czerwona kropka** — dane wrażliwe (GDPR)

---

## 09:30–12:00 — Chaotic Exploration: Domain Events

Trzy godziny intensywnego klejenia kartek. Ściana 8 metrów. Każdy pisze i klei. Bez dyskusji — Brandolini pilnuje.

### Pełna lista Domain Events

#### Obszar: Onboarding & Konto Użytkownika

| # | Domain Event | Zgłosił |
|---|---|---|
| 1 | Użytkownik założył konto | Paweł [front] |
| 2 | Użytkownik podał NIP | Michał P. [security] — "to jest moment, gdzie zaczynamy zbierać PII" |
| 3 | Użytkownik wybrał rok podatkowy do rozliczenia | Zofia [front] |
| 4 | Użytkownik połączył konto brokera (OAuth/API) | Ania [data] |
| 5 | Użytkownik wgrał plik CSV z transakcjami | Ania [data] |
| 6 | Użytkownik ręcznie dodał transakcję | Zofia [front] |
| 7 | Użytkownik usunął konto | Michał P. [security] — "GDPR right to erasure" |
| 8 | Użytkownik zaakceptował regulamin i politykę prywatności | Łukasz [risk] |
| 9 | Użytkownik wybrał brokera z listy | Paweł [front] |
| 10 | Użytkownik dodał kolejnego brokera | Ania [data] |

#### Obszar: Import Transakcji

| # | Domain Event | Zgłosił |
|---|---|---|
| 11 | Plik CSV został przesłany | Ania [data] |
| 12 | Format pliku został rozpoznany (XTB/IBKR/Degiro/...) | Ania [data] |
| 13 | Format pliku NIE został rozpoznany | Kasia [QA] — "co wtedy?" |
| 14 | Transakcje zostały sparsowane z CSV | Ania [data] |
| 15 | Wykryto błędy w parsowaniu (brakujące pola, złe daty) | Kasia [QA] |
| 16 | Transakcje zostały zwalidowane | Marek [senior-dev] |
| 17 | Wykryto duplikaty transakcji | Kasia [QA] |
| 18 | Transakcje zostały zaimportowane do systemu | Marek [senior-dev] |
| 19 | Import został odrzucony (zbyt wiele błędów) | Kasia [QA] |
| 20 | Dane zostały pobrane przez API brokera | Ania [data] |
| 21 | API brokera zwróciło błąd / timeout | Sylwester [SRE] |
| 22 | Wykryto transakcję w nieznanym instrumencie | Kasia [QA] |
| 23 | Użytkownik ręcznie zmapował nieznany instrument | Zofia [front] |
| 24 | Import z PIT-8C (OCR/upload) został przetworzony | Ania [data] — "polski broker daje PIT-8C, ale ludzie chcą zweryfikować" |
| 25 | Wykryto rozbieżność między PIT-8C a importem CSV | Tomasz [DP] — "to się zdarza BARDZO często" |

#### Obszar: Klasyfikacja Instrumentów

| # | Domain Event | Zgłosił |
|---|---|---|
| 26 | Instrument został sklasyfikowany jako akcja | Marek [senior-dev] |
| 27 | Instrument został sklasyfikowany jako CFD | Marek [senior-dev] |
| 28 | Instrument został sklasyfikowany jako kryptowaluta | Marek [senior-dev] |
| 29 | Instrument został sklasyfikowany jako ETF | Marek [senior-dev] |
| 30 | Instrument został sklasyfikowany jako obligacja | Tomasz [DP] |
| 31 | Instrument został sklasyfikowany jako opcja/warrant | Tomasz [DP] |
| 32 | Instrument został sklasyfikowany jako prawo poboru (PP) | Tomasz [DP] |
| 33 | Instrument został sklasyfikowany jako PDA | Tomasz [DP] |
| 34 | Wykryto instrument hybrydowy (np. ETN na krypto) | Tomasz [DP] — "a to jest pułapka — ETN na Bitcoina to NIE jest krypto podatkowo" |
| 35 | Klasyfikacja instrumentu została ręcznie skorygowana przez użytkownika | Zofia [front] |
| 36 | Waluta instrumentu została ustalona | Ania [data] |

#### Obszar: Kursy Walut (NBP)

| # | Domain Event | Zgłosił |
|---|---|---|
| 37 | Kurs NBP dla dnia transakcji został pobrany | Ania [data] |
| 38 | Kurs NBP nie jest dostępny (dzień wolny — trzeba cofnąć) | Tomasz [DP] — "ostatni dzień roboczy PRZED transakcją, art. 11a ust. 1 ustawy o PIT" |
| 39 | Kurs NBP został zcache'owany | Aleksandra [perf] |
| 40 | API NBP zwróciło błąd | Sylwester [SRE] |
| 41 | Użyto kursu zastępczego (manual override) | Kasia [QA] — "a czy wolno?" Tomasz: "NIE, kurs NBP jest jedynym legalnym" |
| 42 | Przeliczono wartość transakcji na PLN | Marek [senior-dev] |

#### Obszar: Obliczanie Podatku — Akcje

| # | Domain Event | Zgłosił |
|---|---|---|
| 43 | Pozycja zakupu akcji została zarejestrowana | Marek [senior-dev] |
| 44 | Pozycja sprzedaży akcji została zarejestrowana | Marek [senior-dev] |
| 45 | Zastosowano metodę FIFO do dopasowania buy-sell | Tomasz [DP] — "to jest KLUCZOWE — art. 30a ust. 3 ustawy o PIT" |
| 46 | Obliczono koszt nabycia w PLN (kurs NBP z dnia-1 zakupu) | Tomasz [DP] |
| 47 | Obliczono przychód ze sprzedaży w PLN (kurs NBP z dnia-1 sprzedaży) | Tomasz [DP] |
| 48 | Obliczono zysk/stratę na transakcji | Marek [senior-dev] |
| 49 | Uwzględniono prowizję brokera jako koszt | Tomasz [DP] — "prowizja jest KUP — art. 23 ust. 1 pkt 38" |
| 50 | Wykryto częściową sprzedaż (sprzedano mniej niż kupiono) | Kasia [QA] |
| 51 | Wykryto short selling | Kasia [QA] — Tomasz: "short sell rozlicza się analogicznie, ale koszt jest w momencie odkupu" |
| 52 | Wykryto corporate action: split | Tomasz [DP] — "nie jest zdarzeniem podatkowym" |
| 53 | Wykryto corporate action: reverse split | Tomasz [DP] |
| 54 | Wykryto corporate action: spin-off | Tomasz [DP] — "tu jest DRAMAT — trzeba alokować koszt nabycia proporcjonalnie" |
| 55 | Wykryto corporate action: merger/acquisition | Tomasz [DP] |
| 56 | Wykryto konwersję PDA na akcje | Tomasz [DP] |
| 57 | Wykryto sprzedaż prawa poboru | Tomasz [DP] — "koszt nabycia = 0 jeśli PP otrzymane bezpłatnie" |

#### Obszar: Obliczanie Podatku — CFD

| # | Domain Event | Zgłosił |
|---|---|---|
| 58 | Pozycja CFD została otwarta (long/short) | Marek [senior-dev] |
| 59 | Pozycja CFD została zamknięta | Marek [senior-dev] |
| 60 | Naliczono overnight fee / swap | Tomasz [DP] — "kontrowersyjne! Część US traktuje jako koszt, część nie" |
| 61 | Obliczono wynik na pozycji CFD w PLN | Marek [senior-dev] |
| 62 | Zsumowano wyniki wszystkich CFD w roku | Tomasz [DP] — "CFD = instrumenty pochodne, art. 17 ust. 1 pkt 10" |
| 63 | Wykryto rollover pozycji CFD | Kasia [QA] |

#### Obszar: Obliczanie Podatku — Kryptowaluty

| # | Domain Event | Zgłosił |
|---|---|---|
| 64 | Zakup kryptowaluty został zarejestrowany | Marek [senior-dev] |
| 65 | Sprzedaż kryptowaluty została zarejestrowana | Marek [senior-dev] |
| 66 | Wymiana krypto-na-krypto została zarejestrowana | Tomasz [DP] — "od 2019 wymiana krypto na krypto NIE jest zdarzeniem podatkowym!" |
| 67 | Wykryto staking rewards | Tomasz [DP] — "to jest przychód w momencie otrzymania" |
| 68 | Wykryto airdrop | Tomasz [DP] — "airdrop = przychód, koszt = 0, ale skąd wziąć wartość rynkową?" |
| 69 | Wykryto mining rewards | Tomasz [DP] |
| 70 | Obliczono zysk/stratę na krypto (FIFO) | Marek [senior-dev] |
| 71 | Strata z krypto NIE została połączona z zyskiem z akcji | Tomasz [DP] — "OSOBNY koszyk! art. 30b ust. 5d" |
| 72 | Wykryto transfer krypto między portfelami (nie jest sprzedażą) | Kasia [QA] |
| 73 | Wykryto DeFi yield / liquidity pool rewards | Kasia [QA] — Tomasz: "szara strefa prawna, nie ma jasnych przepisów" |
| 74 | Wykryto NFT transakcję | Kasia [QA] — Tomasz: "NFT to temat rzeka... teoretycznie jak krypto, ale US mogą mieć inną interpretację" |

#### Obszar: Dywidendy

| # | Domain Event | Zgłosił |
|---|---|---|
| 75 | Dywidenda z polskiej spółki została otrzymana | Tomasz [DP] — "19% pobrał broker, użytkownik nie musi nic robić" |
| 76 | Dywidenda z zagranicznej spółki została otrzymana | Tomasz [DP] |
| 77 | Podatek u źródła (WHT) został pobrany za granicą | Mec. Nowak [UPO] |
| 78 | Zidentyfikowano kraj źródła dywidendy | Mec. Nowak [UPO] |
| 79 | Zastosowano stawkę WHT z umowy UPO | Mec. Nowak [UPO] — "trzeba znać stawkę z UPO DLA KAŻDEGO KRAJU" |
| 80 | Obliczono dopłatę podatku w PL (19% minus WHT) | Tomasz [DP] |
| 81 | WHT > 19% — brak dopłaty, ale brak zwrotu | Mec. Nowak — "np. USA 30% jeśli nie ma W-8BEN" |
| 82 | Wykryto dywidendę w walucie innej niż PLN | Ania [data] |
| 83 | Przeliczono dywidendę na PLN kursem NBP | Tomasz [DP] |
| 84 | Wykryto dywidendę rzeczową (np. akcje) | Tomasz [DP] — "rzadkie ale się zdarza" |
| 85 | Wykryto REIT distribution | Kasia [QA] — Tomasz: "kwalifikacja zależy od struktury REIT i UPO" |

#### Obszar: Straty z Lat Poprzednich

| # | Domain Event | Zgłosił |
|---|---|---|
| 86 | Użytkownik wprowadził straty z lat poprzednich | Zofia [front] |
| 87 | Zweryfikowano czy strata jest w okresie 5 lat | Tomasz [DP] — "art. 9 ust. 3 ustawy o PIT" |
| 88 | Obliczono maksymalny odpis straty (50% w roku) | Tomasz [DP] |
| 89 | Zasugerowano optymalny rozkład odpisu straty | Tomasz [DP] — "UWAGA — to już jest doradztwo podatkowe!" Łukasz [risk]: "STOP. To zmienia wszystko." |
| 90 | Strata z krypto została odseparowana od strat z akcji | Tomasz [DP] |
| 91 | Strata z lat poprzednich została zastosowana do obliczenia | Marek [senior-dev] |

#### Obszar: Generowanie Deklaracji PIT-38

| # | Domain Event | Zgłosił |
|---|---|---|
| 92 | Obliczenie roczne zostało sfinalizowane | Marek [senior-dev] |
| 93 | Wygenerowano podgląd PIT-38 | Paweł [front] |
| 94 | Użytkownik zweryfikował i zaakceptował obliczenia | Zofia [front] |
| 95 | Wygenerowano PIT-38 w formacie XML (e-Deklaracje) | Ania [data] |
| 96 | Wygenerowano PIT-38 w formacie PDF | Ania [data] |
| 97 | Użytkownik wyeksportował dane do programu e-pity / PITax | Paweł [front] |
| 98 | Wygenerowano załącznik PIT/ZG (dochody zagraniczne) | Tomasz [DP] — "osobny załącznik dla KAŻDEGO kraju!" |
| 99 | Obliczenie zostało zarchiwizowane dla roku podatkowego | Marek [senior-dev] |

#### Obszar: Audyt i Kontrola

| # | Domain Event | Zgłosił |
|---|---|---|
| 100 | Użytkownik pobrał szczegółowy raport obliczeń (audit trail) | Joanna [audytor] — "US WYMAGA żeby podatnik umiał udowodnić skąd te liczby" |
| 101 | Wygenerowano raport FIFO matching (która sprzedaż do którego zakupu) | Joanna [audytor] |
| 102 | Wygenerowano raport kursów NBP użytych w obliczeniach | Joanna [audytor] |
| 103 | Wykryto rozbieżność z PIT-8C brokera | Joanna [audytor] |
| 104 | System wygenerował ostrzeżenie o potencjalnym błędzie | Kasia [QA] |
| 105 | Użytkownik zgłosił reklamację (obliczenie jest błędne) | Łukasz [risk] |

#### Obszar: Płatności i Subskrypcja

| # | Domain Event | Zgłosił |
|---|---|---|
| 106 | Użytkownik wybrał plan cenowy | Paweł [front] |
| 107 | Płatność została przetworzona | Paweł [front] |
| 108 | Subskrypcja została aktywowana | Marek [senior-dev] |
| 109 | Subskrypcja wygasła | Marek [senior-dev] |
| 110 | Użytkownik zażądał zwrotu pieniędzy | Łukasz [risk] — "co jeśli twierdzi że obliczenie było błędne?" |

---

## 12:00–12:45 — Lunch Break + Backstage

**Przy kawie, korytarz:**

**Mariusz Gil** rozmawia z **Tomaszem Kędzierskim** (doradca podatkowy):

> **Gil:** "Tomasz, powiedz mi szczerze — ile edge case'ów jest w tych rozliczeniach zagranicznych? Bo mam wrażenie, że ściana to dopiero wierzchołek."
>
> **Tomasz:** "Mariusz, ja rozliczam ponad 300 osób rocznie. Każdy rok mam minimum 5 przypadków, których NIE widziałem wcześniej. Spin-off amerykańskiej spółki, konwersja ADR na akcje, dywidenda w naturze z REIT-a... Prawo nie nadąża za rynkiem."
>
> **Gil:** "A to oznacza, że nasz model domenowy musi być otwarty na rozszerzenia. Nie zamkniemy tego w statycznym enum."
>
> **Tomasz:** "I jeszcze jedno — ta kartka o sugerowaniu optymalnego odpisu straty... to jest DORADZTWO PODATKOWE. Art. 2 ustawy o doradztwie podatkowym. Żeby to robić, musicie być doradcą podatkowym lub firmą audytorską."

**W kuchni:**

**Michał P. [security]** do **Łukasza [risk]**:

> **Michał P.:** "Łukasz, zdajesz sobie sprawę, że będziemy przechowywać pełną historię transakcji inwestycyjnych ludzi? Ich zyski, straty, NIP-y. To jest profil finansowy. Jedno naruszenie i mamy pozew zbiorowy."
>
> **Łukasz:** "Wiem. I jeszcze kwestia odpowiedzialności za błędne obliczenia. Wyobraź sobie: user dostaje karę z US bo nasze FIFO było źle zaimplementowane..."

**Agenci AI** w tym czasie wstępnie grupują zdarzenia na ścianie w klastry, przygotowując się do timeline.

---

## 12:45–14:30 — Timeline & Hotspots

Brandolini prowadzi porządkowanie zdarzeń na osi czasu. Mariusz Gil rysuje "swimlanes" na ścianie — oddzielne ścieżki dla różnych typów instrumentów.

### Hotspot Register

| ID | Hotspot | Zgłosił | Dyskusja | Status |
|---|---|---|---|---|
| **HS-001** | **Granica między narzędziem a doradztwem podatkowym** | Łukasz [risk] | Tomasz: "Art. 2 ustawy o doradztwie podatkowym — czynności doradztwa mogą wykonywać tylko doradcy podatkowi, adwokaci, radcy prawni i biegli rewidenci." Mec. Wiśniewska: "Jeśli system SUGERUJE optymalny odpis straty — to jest doradztwo. Jeśli tylko OBLICZA — to narzędzie kalkulacyjne." Łukasz: "Ale gdzie jest ta granica? Jeśli system pokazuje że można odliczyć stratę i automatycznie to robi — to doradztwo czy kalkulacja?" Brandolini: "🩷 — to jest wasz największy hotspot. Różowa kartka, rozmiar XXL." | **ESCALATED** — wymaga opinii prawnej |
| **HS-002** | **Odpowiedzialność za błędne obliczenia** | Łukasz [risk] | Łukasz: "Kto odpowiada gdy nasze FIFO da zły wynik? Użytkownik? My? Nikt?" Mec. Wiśniewska: "Podatnik ZAWSZE odpowiada za swoje zeznanie. Ale jeśli korzystał z narzędzia które się reklamuje jako 'rozlicz PIT-38' — ma roszczenie cywilne wobec nas." Tomasz: "Regulamin nie wystarczy. Ludzie będą płacić za to ŻEBY NIE MYŚLEĆ. A jak dostaną korektę z US, przyjdą do nas." | **OPEN** |
| **HS-003** | **Overnight fee / swap w CFD — koszt czy nie?** | Tomasz [DP] | Tomasz: "Nie ma jednoznacznej interpretacji. Część US mówi że swap jest kosztem uzyskania przychodu, część że nie. Mam klientów w dwóch US z różnymi interpretacjami!" Mec. Wiśniewska: "Można wystąpić o interpretację indywidualną, ale to dotyczyłoby tylko wnioskodawcy." Mariusz Gil: "To znaczy, że nasz model musi mieć konfigurowalną politykę? To jest domena, nie feature flag." | **OPEN** |
| **HS-004** | **Format CSV — każdy broker ma inny** | Ania [data] | Ania: "XTB ma inny format niż IBKR, Degiro zmienia format co rok, Trading212 eksportuje inaczej niż eToro. Niektóre mają prowizje w osobnej kolumnie, inne wliczają w cenę." Kasia [QA]: "Ile formatów musimy wspierać? 10? 20? 50?" Aleksandra [perf]: "I każdy nowy broker to nowy parser. Ile będzie kosztował maintenance?" | **OPEN** |
| **HS-005** | **Kurs NBP — który dokładnie dzień?** | Tomasz [DP] | Tomasz: "Art. 11a ust. 1 — kurs średni NBP z ostatniego dnia roboczego POPRZEDZAJĄCEGO dzień uzyskania przychodu. Ale co jeśli transakcja była o 23:55 w piątek w USA, a w Polsce jest już sobota?" Ania: "Timezone. Musimy wiedzieć w jakiej strefie czasowej jest broker." Mariusz Gil: "To jest fundamentalna decyzja domenowa — czyja strefa czasowa jest źródłem prawdy?" Tomasz: "Polska. Liczy się moment uznania — ale brokerzy raportują w swoim czasie..." | **OPEN** |
| **HS-006** | **Wymiana krypto-na-krypto** | Tomasz [DP] | Tomasz: "Od 1.01.2019 wymiana krypto na krypto NIE jest zdarzeniem podatkowym (art. 17 ust. 1f). ALE — wymiana krypto na stablecoina pegged do USD? To jest krypto-na-krypto, więc nie jest zdarzeniem." Kasia [QA]: "A co z wrapped tokens? wBTC to BTC?" Tomasz: "Cisza w przepisach. Szara strefa." | **OPEN** |
| **HS-007** | **DeFi — yield farming, liquidity pools** | Kasia [QA] | Tomasz: "Zero przepisów specyficznych. Można argumentować że yield to odsetki, albo że to przychód z kapitałów. Albo że to przychód z innych źródeł (art. 10 ust. 1 pkt 9). Każda opcja ma inne konsekwencje." Mec. Wiśniewska: "Dopóki nie ma interpretacji ogólnej MF — budowanie systemu który to rozlicza to budowanie na piasku." Łukasz [risk]: "Czyli out of scope?" Brandolini: "Zapiszcie to — ale nie budujcie tego w v1." | **RESOLVED** — out of scope v1 |
| **HS-008** | **ETN na Bitcoina ≠ kryptowaluta** | Tomasz [DP] | Tomasz: "ETN, np. VanEck Bitcoin ETN — to jest papier wartościowy, nie krypto. Rozliczasz jak akcję. Ale user kupi to na IBKR i powie 'to moje krypto'. System musi to rozróżnić." Marek [senior-dev]: "Klasyfikacja instrumentu to core domain logic." Mariusz Gil: "I nie może być enumem! To musi być strategia, bo jutro pojawi się nowy instrument." | **RESOLVED** — kluczowy aggregate: InstrumentClassification |
| **HS-009** | **Spin-off — alokacja kosztu nabycia** | Tomasz [DP] | Tomasz: "Miałem klienta — PayPal spin-off z eBay. Musiał alokować koszt nabycia eBay proporcjonalnie. Ale proporcjonalnie do CZEGO? Do ceny rynkowej w dniu spin-off. A skąd wziąć tę cenę? Z zamknięcia giełdy w dniu spin-off w USA." Kasia [QA]: "A jeśli ktoś miał 1000 akcji eBay kupowanych w 15 transzach na przestrzeni 3 lat? FIFO na każdą transzę?" Tomasz: "Tak. Każda transza osobno." Aleksandra [perf]: "To jest O(n²) w najgorszym przypadku." | **OPEN** |
| **HS-010** | **Prowizja brokera — w jakiej walucie i jak przeliczyć** | Tomasz [DP] | Tomasz: "Prowizja jest kosztem uzyskania przychodu. Ale jeśli IBKR pobiera prowizję w USD — przeliczasz ją kursem NBP z dnia-1 TRANSAKCJI, nie z dnia pobrania prowizji." Ania [data]: "Niektóre brokerzy pokazują prowizję osobno, inne wliczają w cenę. Degiro ma osobną tabelę opłat." | **RESOLVED** — prowizja przeliczana kursem z dnia transakcji |
| **HS-011** | **Multi-currency portfolio — ten sam instrument na dwóch giełdach** | Kasia [QA] | Kasia: "Co jeśli ktoś kupił Apple na NASDAQ przez IBKR i na Frankfurt przez Degiro? To ten sam ISIN ale inny kurs wymiany." Tomasz: "FIFO jest per instrument, nie per giełda/broker. Więc kupno z IBKR w USD i sprzedaż z Degiro w EUR — to jedna kolejka FIFO." Mariusz Gil: "To jest insight! Aggregate FIFO jest per ISIN, cross-broker." | **RESOLVED** — FIFO per ISIN, cross-broker |
| **HS-012** | **Dane z API brokera — freshness i reliability** | Sylwester [SRE] | Sylwester: "Jakie SLA mają API brokerów? Jeśli XTB API padnie w sezonie podatkowym (luty-kwiecień), tracimy core functionality." Ania: "XTB nie ma publicznego API do eksportu transakcji. IBKR ma Flex Queries. Degiro ma nieoficjalne API." Bartek [devops]: "Czyli zależymy od nieoficjalnych API które mogą zniknąć?" | **ESCALATED** |
| **HS-013** | **PIT-8C vs. własne obliczenia — rozbieżności** | Joanna [audytor] | Joanna: "Polski broker wystawia PIT-8C. User wgrywa swoje transakcje i nasze obliczenie daje INNY wynik. Co wtedy?" Tomasz: "PIT-8C brokera jest tylko informacją. Podatnik może się nie zgodzić. ALE — jeśli user złoży PIT-38 niezgodny z PIT-8C, US zapyta dlaczego." Łukasz [risk]: "Czy musimy wyjaśnić user'owi różnicę? Bo to znowu pachnie doradztwem." | **OPEN** |
| **HS-014** | **GDPR — prawo do usunięcia vs. obowiązek archiwizacji** | Michał P. [security] | Michał P.: "User chce usunąć konto (art. 17 GDPR). Ale my mamy jego dane podatkowe. Ordynacja podatkowa wymaga przechowywania dokumentacji 5 lat." Mec. Wiśniewska: "Art. 17 ust. 3 lit. b GDPR — wyjątek dla obowiązku prawnego. Możecie przechowywać to co jest prawnie wymagane." Michał P.: "Ale co dokładnie jest 'prawnie wymagane'? Cała historia transakcji?" | **OPEN** |
| **HS-015** | **Sezon podatkowy — peak traffic** | Aleksandra [perf] | Aleksandra: "PIT-38 trzeba złożyć do 30 kwietnia. W praktyce 80% ludzi robi to w ostatnich 2 tygodniach. Jeśli mamy 50k userów i wszyscy chcą obliczyć w tym samym czasie..." Sylwester [SRE]: "Auto-scaling. Ale obliczenia podatkowe to CPU-heavy, nie I/O. Skalowanie w chmurze na CPU jest droższe." Bartek [devops]: "Pre-compute? Jak się zmieni coś w imporcie, przelicz od razu?" | **RESOLVED** — pre-compute + cache, re-calc on change |
| **HS-016** | **Staking krypto — moment rozpoznania przychodu** | Tomasz [DP] | Tomasz: "Staking rewards — kiedy jest przychód? W momencie przyznania (credited to wallet) czy w momencie odblokowania (unstaking)? Zależy od mechanizmu stakingu." Kasia [QA]: "A jeśli staking na Ethereum — rewards są zablokowane do momentu unstake?" Tomasz: "Argument: przychód jest w momencie gdy masz dyspozyję. Jeśli zablokowane — nie masz dyspozyji." | **OPEN** |
| **HS-017** | **W-8BEN i stawki WHT** | Mec. Nowak [UPO] | Mec. Nowak: "US WHT na dywidendy to 30%. Ale z W-8BEN i UPO Polska-USA: 15%. Czy nasz system wie czy user złożył W-8BEN?" Ania: "To zależy od brokera. IBKR automatycznie aplikuje W-8BEN. eToro — nie wiadomo." Mec. Nowak: "Jeśli user dostał 15% WHT — ok. Jeśli 30% — nadpłacił za granicą, ale w PL i tak dopłaca do 19%. Czy różnicę odzyskuje?" Tomasz: "Teoretycznie tak, ale procedura jest gehenna." | **OPEN** |
| **HS-018** | **Instrument denominowany w egzotycznej walucie** | Kasia [QA] | Kasia: "Co z instrumentami w TRY, ZAR, HKD? NBP ma kursy tych walut?" Ania: "Sprawdzę — NBP publikuje ok. 35 walut. TRY — tak. Ale np. THB — nie wiem." Tomasz: "Jeśli NBP nie publikuje kursu — stosuje się kurs krzyżowy przez USD lub EUR. To jest osobna logika." | **OPEN** |
| **HS-019** | **Podatek od zysków vs. podatek od dywidend — osobne rubryki PIT-38** | Tomasz [DP] | Tomasz: "PIT-38 ma osobne sekcje: C — przychody/koszty z odpłatnego zbycia, D — przychody z dywidend i inne przychody z tytułu udziału w zyskach. Nie można tego mieszać!" Paweł [front]: "Czyli UI musi jasno rozdzielać te dwa strumienie." | **RESOLVED** — osobne sekcje w UI i modelu |
| **HS-020** | **Broker zmienia format eksportu** | Ania [data] | Ania: "Degiro zmienił format CSV w 2024. XTB zmienił nazwy kolumn w 2023. Co jak to się stanie po tym jak user ma już dane?" Marek [senior-dev]: "Versioned parsers. Każdy parser ma wersję. Stare dane zachowują stary format." Mariusz Gil: "Anti-Corruption Layer. Klasyczny pattern. Parser jako ACL między formatem brokera a naszym modelem." | **RESOLVED** — ACL pattern, versioned parsers |
| **HS-021** | **Częściowe zamknięcie pozycji CFD** | Kasia [QA] | Kasia: "User otwiera 10 lot CFD na EURUSD, zamyka 3, potem zamyka 7. Albo: otwiera 10, dodaje 5, zamyka 8." Tomasz: "Wynik oblicza się na każde zamknięcie osobno. Cena nabycia to weighted average albo FIFO — zależy od brokera. ALE podatkowo liczy się wynik od brokera." Marek: "To znaczy że dla CFD nie robimy własnego FIFO?" Tomasz: "Dla CFD bierze się wynik z brokera — P&L per zamknięta pozycja." | **RESOLVED** — CFD P&L from broker, nie własne FIFO |
| **HS-022** | **Waluta kosztu nabycia vs. waluta przychodu** | Tomasz [DP] | Tomasz: "Kupujesz Apple za USD, sprzedajesz Apple za USD. Ale koszt nabycia przeliczasz kursem NBP z dnia-1 KUPNA, a przychód kursem NBP z dnia-1 SPRZEDAŻY. To są RÓŻNE kursy. Różnice kursowe są wliczone w zysk/stratę automatycznie." Aleksandra [perf]: "Czyli potrzebujemy kursu NBP dla każdego dnia transakcji. Jeśli user ma 5000 transakcji w roku — to 5000 requestów do NBP." Ania: "Batch download. NBP ma API z zakresem dat." | **RESOLVED** — batch download kursów NBP |
| **HS-023** | **Odziedziczone akcje** | Tomasz [DP] | Tomasz: "Jeśli ktoś odziedziczył akcje — koszt nabycia = wartość rynkowa z dnia śmierci spadkodawcy (art. 22 ust. 1m). Ale skąd user weźmie tę wartość?" Zofia [front]: "Manual input z datepickerem i polem na wartość?" Tomasz: "Tak, ale musi być oznaczone jako 'nabycie w drodze spadku'." | **OPEN** |
| **HS-024** | **Akcje pracownicze (ESOP/RSU)** | Tomasz [DP] | Tomasz: "RSU z Google czy Meta — moment nabycia to moment vestingu. Koszt nabycia = wartość rynkowa w dniu vestingu MINUS to co zapłacił (zwykle 0). Ale opodatkowanie może być podwójne — raz jako przychód ze stosunku pracy (PIT-11), raz przy sprzedaży (PIT-38)." Łukasz [risk]: "To jest wystarczająco skomplikowane, żeby być osobnym modułem." Brandolini: "Albo osobnym Bounded Context." | **OPEN** |
| **HS-025** | **Korekta zeznania** | Joanna [audytor] | Joanna: "User składa PIT-38, potem orientuje się że zapomniał o jednym brokerze. Musi złożyć korektę. Czy nasz system to wspiera?" Paweł [front]: "To jest basically re-obliczenie z nowymi danymi i wygenerowanie nowego XMLa." Tomasz: "I porównanie z poprzednim — żeby user widział co się zmieniło." | **OPEN** |
| **HS-026** | **Transfer instrumentów między brokerami** | Kasia [QA] | Kasia: "User przenosi akcje z XTB na IBKR. To nie jest sprzedaż. Ale w CSV XTB wygląda jak 'wyjście' a w IBKR jak 'wejście'. System musi to rozpoznać jako transfer, nie transakcję." Ania: "Jak? Na podstawie dat i ilości? Fuzzy matching?" Mariusz Gil: "To jest polityka — TransferDetectionPolicy. I user musi to potwierdzić." | **OPEN** |
| **HS-027** | **Automatyczne pobieranie PIT-8C z e-US** | Ania [data] | Ania: "Czy możemy zintegrować się z systemem e-Urząd Skarbowy i pobrać PIT-8C automatycznie?" Bartek [devops]: "To jest system rządowy. API?" Ania: "Profil Zaufany + API KAS... ale nie wiem czy jest publiczne." Sylwester [SRE]: "Dependency na system rządowy = ryzyko." | **OPEN** |
| **HS-028** | **Multi-year consistency** | Joanna [audytor] | Joanna: "Jeśli user rozlicza 2025 u nas, ale 2024 robił sam — skąd wiemy jakie FIFO zastosował w 2024? Bo FIFO jest ciągłe — pozycja kupiona w 2023 i sprzedana w 2025 wymaga znajomości stanu z 2024." Tomasz: "To jest KLUCZOWE. System musi albo mieć pełną historię, albo user musi podać stan otwarcia." Mariusz Gil: "Aggregate TaxPositionLedger musi mieć snapshot z roku bazowego." | **OPEN** |
| **HS-029** | **Prezentacja obliczeń — zrozumiałość dla użytkownika** | Zofia [front] | Zofia: "User widzi: 'Podatek do zapłaty: 3 847 PLN'. Ale chce zrozumieć SKĄD. Drill-down do poziomu pojedynczej transakcji." Paweł: "To jest dużo UI." Joanna: "To jest konieczne — na wypadek kontroli US." | **OPEN** |
| **HS-030** | **Zmiana przepisów w trakcie roku podatkowego** | Mec. Wiśniewska | Mec. Wiśniewska: "Co jeśli zmienią się przepisy? Np. w 2019 zmieniono opodatkowanie krypto — nowe zasady weszły od 1.01.2019 ale ustawa była podpisana w listopadzie 2018." Marek [senior-dev]: "Reguły podatkowe muszą być wersjonowane per rok podatkowy." Mariusz Gil: "TaxRuleSet jako Value Object z effective date range." | **RESOLVED** — versioned tax rules per year |
| **HS-031** | **Wielokrotne konta u tego samego brokera** | Kasia [QA] | Kasia: "IBKR pozwala na sub-accounts. XTB pozwala na konto PLN i konto EUR. Jeden user, dwa konta, jeden broker." | **OPEN** |

---

## 15:00–17:00 — Commands, Actors & Aggregates

Mariusz Gil przejmuje tę część:

> "Mamy zdarzenia na osi czasu. Teraz pytamy: KTO wywołał to zdarzenie i JAKĄ komendą? I co jest agregatem — obiektem który pilnuje spójności?"

### Zidentyfikowane Bounded Contexts

#### 1. **Identity & Access** (Supporting)

| Element | Wartość |
|---|---|
| Odpowiedzialność | Rejestracja, logowanie, zarządzanie kontem, GDPR |
| Actors | Użytkownik, System |
| Commands | CreateAccount, Login, DeleteAccount, AcceptTerms |
| Aggregates | UserAccount |
| Events | #1, #2, #7, #8 |
| Integracje | OAuth2, Profil Zaufany (opcja) |

#### 2. **Broker Integration** (Supporting)

| Element | Wartość |
|---|---|
| Odpowiedzialność | Import transakcji, parsowanie CSV, API brokerów, normalizacja danych |
| Actors | Użytkownik, System (scheduler) |
| Commands | UploadCSV, ConnectBrokerAPI, ParseTransactions, ResolveUnknownInstrument |
| Aggregates | BrokerConnection, ImportSession, TransactionParser (ACL) |
| Events | #4, #5, #11–#25 |
| Integracje | XTB API, IBKR Flex Queries, Degiro (unofficial), Trading212, eToro, Capital.com |
| Hotspots | HS-004, HS-012, HS-020 |

#### 3. **Instrument Registry** (Supporting)

| Element | Wartość |
|---|---|
| Odpowiedzialność | Klasyfikacja instrumentów, mapowanie ISIN, typ podatkowy |
| Actors | System, Użytkownik (korekta) |
| Commands | ClassifyInstrument, OverrideClassification |
| Aggregates | Instrument, InstrumentClassification |
| Events | #26–#36 |
| Hotspots | HS-008 |

#### 4. **Exchange Rate Service** (Generic/Supporting)

| Element | Wartość |
|---|---|
| Odpowiedzialność | Pobieranie i cache'owanie kursów NBP |
| Actors | System (scheduler) |
| Commands | FetchNBPRate, FetchNBPRateBatch |
| Aggregates | DailyExchangeRate |
| Events | #37–#42 |
| Integracje | API NBP (api.nbp.pl) |
| Hotspots | HS-005, HS-018 |

#### 5. **Tax Calculation Engine** (CORE DOMAIN) ⭐

| Element | Wartość |
|---|---|
| Odpowiedzialność | Obliczanie podatku — FIFO matching, przeliczenia walutowe, wynik per instrument type, agregacja roczna |
| Actors | System (triggered by import/user action) |
| Commands | CalculateAnnualTax, ApplyFIFO, CalculateDividendTax, ApplyPriorYearLoss, RecalculateAfterChange |
| Aggregates | **TaxPositionLedger** (per ISIN, per user), **AnnualTaxCalculation**, **PriorYearLossRegister** |
| Events | #43–#74, #86–#92 |
| Policies | FIFOMatchingPolicy, CryptoSeparationPolicy, LossCarryForwardPolicy, CFDNetResultPolicy |
| Hotspots | HS-001, HS-003, HS-006, HS-009, HS-011, HS-016, HS-021, HS-022, HS-023, HS-024, HS-028 |

**Mariusz Gil:**
> "To jest wasze CORE DOMAIN. Tu jest cała wartość biznesowa. To musi być najlepiej zaprojektowane, najlepiej przetestowane, najlepiej udokumentowane. Reszta to supporting i generic — można kupić, zintegrować, uprościć. ALE Tax Calculation Engine — to jest wasz MOAT."

#### 6. **Tax Declaration** (Core Supporting)

| Element | Wartość |
|---|---|
| Odpowiedzialność | Generowanie PIT-38, PIT/ZG, XML, PDF, eksport |
| Actors | Użytkownik |
| Commands | GeneratePIT38Preview, ExportXML, ExportPDF, GeneratePITZG |
| Aggregates | TaxDeclaration, DeclarationAttachment |
| Events | #93–#99 |
| Integracje | e-Deklaracje XML schema, PDF generator |
| Hotspots | HS-019, HS-025 |

#### 7. **Audit & Reporting** (Supporting)

| Element | Wartość |
|---|---|
| Odpowiedzialność | Audit trail, raporty FIFO, porównanie z PIT-8C |
| Actors | Użytkownik, Audytor (przyszłość) |
| Commands | GenerateAuditReport, ComparePIT8C, GenerateFIFOReport |
| Aggregates | AuditReport |
| Events | #100–#105 |
| Hotspots | HS-013, HS-029 |

#### 8. **Billing & Subscription** (Generic)

| Element | Wartość |
|---|---|
| Odpowiedzialność | Płatności, subskrypcje, plany cenowe |
| Actors | Użytkownik |
| Commands | SelectPlan, ProcessPayment, RequestRefund |
| Aggregates | Subscription |
| Events | #106–#110 |
| Integracje | Stripe / Przelewy24 |

### Context Map (relacje)

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│   [Broker Integration]──ACL──→[Tax Calculation Engine]      │
│          ↑                            ↑     ↑               │
│          │                            │     │               │
│   [Instrument Registry]───────────────┘     │               │
│                                             │               │
│   [Exchange Rate Service]───────────────────┘               │
│                                                             │
│   [Tax Calculation Engine]──→[Tax Declaration]              │
│          │                                                  │
│          └──→[Audit & Reporting]                            │
│                                                             │
│   [Identity & Access]──────→ all contexts (auth)            │
│   [Billing & Subscription]── generic, standalone            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

Relacje:
- Broker Integration → Tax Calc: **ACL** (Anti-Corruption Layer) — parsery tłumaczą format brokera na domenowy model transakcji
- Instrument Registry → Tax Calc: **Conformist** — Tax Calc używa klasyfikacji instrumentów
- Exchange Rate → Tax Calc: **Open Host Service** — Tax Calc pyta o kurs, serwis odpowiada
- Tax Calc → Tax Declaration: **Customer-Supplier** — Declaration konsumuje gotowe obliczenia
- Tax Calc → Audit: **Published Language** — Audit czyta dane przez zdefiniowany interfejs

---

## 17:00–18:00 — Retrospektywa Dnia 1

**Brandolini:** "Co wiemy, czego nie wiemy, co nas zaskoczyło?"

**Każdy uczestnik:**

> **Tomasz [DP]:** "Wiemy więcej niż się spodziewałem. Ale hotspot HS-001 — granica doradztwo vs. narzędzie — to jest existential risk. Jeśli to źle rozwiążemy, cały biznes może mieć problem prawny."

> **Mec. Wiśniewska:** "Zaskoczyła mnie skala edge case'ów. Spin-offy, ESOP, multi-currency FIFO... To nie jest prosty kalkulator."

> **Mariusz Gil:** "Core domain jest jasny — Tax Calculation Engine. Widzę co najmniej 3 strategie w nim: FIFOStrategy, CryptoTaxStrategy, DividendTaxStrategy. To nie jest jeden monolit."

> **Marek [senior-dev]:** "Boję się maintenance parsowania CSV. Każdy broker to osobny adapter. To jest ACL na sterydach."

> **Michał P. [security]:** "Dane finansowe + NIP + pełna historia transakcji = złoty cel dla atakujących. To musi być szyfrowane at rest i in transit, z audit logiem."

> **Kasia [QA]:** "Policzyłam: mamy minimum 15 typów instrumentów × 3 typy operacji × waluta × broker = setki kombinacji do przetestowania."

> **Łukasz [risk]:** "Jestem sceptyczny. Odpowiedzialność za błędne obliczenia + ryzyko prawne doradztwa + maintenance parserów = wysoki risk. Ale rynek jest."

> **Aleksandra [perf]:** "50k userów × 500 transakcji × FIFO matching = musi być szybkie. Pre-compute jest konieczne."

> **Sylwester [SRE]:** "Sezon podatkowy = 6 tygodni peak. Reszta roku — cisza. Infrastruktura musi być elastyczna."

> **Brandolini:** "Dobry dzień. Widzę jeden mega-hotspot: odpowiedzialność prawna. I jeden mega-insight: core domain jest w obliczeniach podatkowych, NIE w UI, NIE w imporcie. Jutro zaczynamy od tego."

---

# DZIEŃ 2 — Process Level + Design Level + Business Case

## 09:00–10:00 — Review Dnia 1 + Dogrywka

**Mariusz Gil** otwiera:

> "Przespaliśmy się z tym. Agenci AI przeanalizowali zdarzenia nocą. Marek, co macie?"

**Marek [senior-dev]:**

> "Trzy rzeczy. Po pierwsze — brakuje nam zdarzeń związanych z ROLLBACK obliczenia. Co jeśli user zaimportuje nowy plik i chce cofnąć? Po drugie — nie mamy zdarzeń dla partial import — user wgrywa CSV z 1000 transakcjami, 980 się parsuje, 20 ma błędy. Co z tymi 20? Po trzecie — Kasia znalazła case: user kupuje akcje za PLN na GPW i sprzedaje za USD na NYSE (dual listing). FIFO mówi że to ten sam instrument, ale waluty są różne."

Nowe zdarzenia dodane na ścianę:

| # | Domain Event | Zgłosił |
|---|---|---|
| 111 | Obliczenie zostało wycofane (rollback) | Marek [senior-dev] |
| 112 | Częściowy import został zaakceptowany (z pominięciem błędnych) | Kasia [QA] |
| 113 | Błędne transakcje z importu zostały oznaczone do ręcznej korekty | Kasia [QA] |
| 114 | Wykryto dual-listed instrument (ten sam ISIN, różne giełdy/waluty) | Kasia [QA] |
| 115 | Użytkownik potwierdził FIFO matching manualnie | Zofia [front] |
| 116 | Wygenerowano porównanie rok-do-roku | Joanna [audytor] |
| 117 | System wykrył potencjalną optymalizację podatkową (ale NIE zasugerował) | Łukasz [risk] — "informacja, nie rekomendacja!" |
| 118 | Użytkownik wyeksportował transakcje do doradcy podatkowego | Tomasz [DP] |

**Mec. Nowak** przychodzi z nocną analizą:

> "Zrobiłem tabelę UPO. Polska ma 90+ umów o unikaniu podwójnego opodatkowania. Każda ma inną stawkę WHT na dywidendy. Najczęstsze: USA 15%, UK 10%, Niemcy 15%, Irlandia 15%. Ale to stawki z UPO — broker może pobrać więcej jeśli nie ma W-8BEN lub equivalent. System musi znać zarówno stawkę UPO jak i faktycznie pobrany WHT."

---

## 10:00–12:30 — Process Level: Kluczowe Przepływy

### Przepływ 1: Import Transakcji z Zagranicznego Brokera

```
Actor: Użytkownik (Inwestor)

[Użytkownik] ──SelectBroker──→ «BrokerConnection»
    │
    ├── [Użytkownik] ──UploadCSV──→ «ImportSession»
    │       │
    │       ├── Event: FileUploaded
    │       ├── Policy: DetectFormatPolicy ──→ Event: FormatRecognized / FormatUnrecognized
    │       ├── Policy: ParseTransactionsPolicy ──→ Event: TransactionsParsed
    │       │       │
    │       │       ├── [jeśli błędy] Event: ParsingErrorsDetected
    │       │       │       │
    │       │       │       └── Read Model: ErrorReport
    │       │       │               │
    │       │       │               └── [Użytkownik] ──ResolveErrors──→ Event: ErrorsResolved
    │       │       │
    │       │       └── Policy: ValidateTransactionsPolicy
    │       │               │
    │       │               ├── Event: DuplicatesDetected ──→ [Użytkownik] ──ResolveDuplicates
    │       │               ├── Event: UnknownInstrumentDetected ──→ [Użytkownik] ──ClassifyInstrument
    │       │               └── Event: TransactionsValidated
    │       │
    │       └── Event: ImportCompleted / ImportPartiallyCompleted
    │
    └── [Użytkownik] ──ConnectAPI──→ «BrokerConnection» (alternatywa)
            │
            └── Policy: FetchTransactionsPolicy ──→ ...same flow
```

**Gil pytanie kontrolne:**

> "What could go wrong? Co jeśli user wgra plik z 2024 myśląc że to 2025? Co jeśli wgra ten sam plik dwa razy? Co jeśli plik ma transakcje z dwóch lat?"

**Kasia [QA]:** "Duplikacja — porównanie hash transakcji (data + instrument + ilość + cena). Plik z dwóch lat — wyodrębniamy tylko transakcje z wybranego roku. Pomyłka roku — warning z preview."

### Przepływ 2: Obliczenie Podatku — Akcje Zagraniczne (FIFO + NBP)

```
Trigger: ImportCompleted / UserRequestedCalculation

Policy: TriggerCalculationPolicy
    │
    ├── Command: FetchRequiredNBPRates
    │       │
    │       └── «ExchangeRateService» ──→ Event: RatesFetched
    │
    └── Command: CalculateStockTax
            │
            └── «TaxPositionLedger» (per ISIN)
                    │
                    ├── Step 1: Zbierz wszystkie BUY transakcje (cross-broker), posortuj FIFO
                    ├── Step 2: Dla każdego SELL:
                    │       ├── Match z najstarszym BUY (FIFO)
                    │       ├── Przelicz koszt nabycia: ilość × cena_buy × kurs_NBP(dzień-1 buy)
                    │       ├── Dodaj prowizję buy: prowizja_buy × kurs_NBP(dzień-1 buy)
                    │       ├── Przelicz przychód: ilość × cena_sell × kurs_NBP(dzień-1 sell)
                    │       ├── Odejmij prowizję sell: prowizja_sell × kurs_NBP(dzień-1 sell)
                    │       ├── Zysk/Strata = przychód - koszt_nabycia - prowizje
                    │       └── Event: TransactionTaxCalculated
                    │
                    ├── Step 3: Zsumuj per instrument type
                    │       └── Event: InstrumentTypeTaxAggregated
                    │
                    └── Step 4: Zastosuj straty z lat poprzednich
                            └── Event: PriorYearLossApplied
```

**Przykład liczbowy (Tomasz na tablicy):**

> "Kupujesz 100 akcji Apple 15.03.2025 po $170, prowizja $1. Kurs NBP z 14.03.2025 = 4,05 PLN/USD.
> Sprzedajesz 100 akcji Apple 20.09.2025 po $200, prowizja $1. Kurs NBP z 19.09.2025 = 3,95 PLN/USD.
>
> Koszt nabycia: 100 × $170 × 4,05 = 68 850 PLN
> Prowizja zakupu: $1 × 4,05 = 4,05 PLN
> Przychód: 100 × $200 × 3,95 = 79 000 PLN
> Prowizja sprzedaży: $1 × 3,95 = 3,95 PLN
>
> Dochód: 79 000 - 68 850 - 4,05 - 3,95 = **10 142 PLN**
> Podatek: 10 142 × 19% = **1 926,98 PLN**
>
> UWAGA: Mimo że w USD zysk = $3000 × 4,00 (średni) = 12 000 PLN, w rzeczywistości wychodzi 10 142 PLN bo kurs PLN się UMOCNIŁ. Różnica kursowa zjadła zysk. I TAK JEST PRAWIDŁOWO."

**Mariusz Gil:** "Ten przykład musi być w dokumentacji domeny. I w testach. Bo to jest kontraintuicyjne."

### Przepływ 3: Kryptowaluty

```
Trigger: ImportCompleted (crypto transactions)

«TaxPositionLedger» (CRYPTO bucket — osobny!)
    │
    ├── Filtruj: tylko sprzedaż krypto za FIAT (PLN/EUR/USD)
    │       (wymiana krypto↔krypto = ignoruj, art. 17 ust. 1f)
    │
    ├── FIFO matching: buy → sell
    │       ├── Koszt nabycia: cena_buy × kurs_NBP(dzień-1)
    │       └── Przychód: cena_sell × kurs_NBP(dzień-1)
    │
    ├── Staking/airdrop: przychód = wartość rynkowa w momencie otrzymania
    │       └── Koszt = 0 (lub koszty energii dla mining — kontrowersyjne)
    │
    └── Event: CryptoTaxCalculated
            │
            └── UWAGA: strata z krypto NIE kompensuje zysku z akcji
                (osobna pozycja w PIT-38)
```

**Kasia [QA]:**
> "Edge case: user kupuje BTC za EUR na Bitstamp, przenosi na Binance, wymienia na ETH, wymienia na USDT, wypłaca USDT na konto bankowe. Które transakcje są podatkowe?"

**Tomasz:** "Tylko wypłata USDT na konto bankowe — bo to jest zamiana krypto na fiat. ALE — USDT to stablecoin, więc krypto-na-krypto do USDT nie jest zdarzeniem. Wypłata USDT na bank... to jest SPRZEDAŻ krypto. Koszt nabycia — trzeba cofnąć FIFO do oryginalnego zakupu BTC za EUR."

**Gil:** "How deep does this rabbit hole go?"

**Tomasz:** "Bardzo głęboko. Dlatego sugeruję: v1 = proste scenariusze (kupno-sprzedaż za fiat). V2 = cross-chain, staking. V3 = DeFi. Albo nigdy."

### Przepływ 4: Dywidendy Zagraniczne z UPO

```
Trigger: DividendReceived (foreign)

[System] ──IdentifyCountry──→ «DividendTaxCalculator»
    │
    ├── Read Model: UPO Rate Table
    │       │
    │       └── Stawka WHT z UPO (np. USA→PL = 15%)
    │
    ├── Input: Faktycznie pobrany WHT (z danych brokera)
    │
    ├── Obliczenie:
    │       ├── Dywidenda brutto w PLN = kwota × kurs_NBP(dzień-1)
    │       ├── WHT zapłacony = faktycznie pobrany WHT × kurs_NBP(dzień-1)
    │       ├── Podatek PL = dywidenda_brutto × 19%
    │       ├── Do zapłaty w PL = max(0, Podatek_PL - WHT_zapłacony)
    │       │
    │       └── Uwaga: jeśli WHT > 19% → do zapłaty = 0
    │               (nadpłata za granicą nie jest zwracana przez PL)
    │
    └── Event: DividendTaxCalculated
    │
    └── Generuj PIT/ZG per kraj
```

**Mec. Nowak — tabela najczęstszych UPO:**

| Kraj | WHT z UPO | WHT bez UPO | Uwagi |
|---|---|---|---|
| USA | 15% | 30% | Wymaga W-8BEN |
| UK | 10% | 10% | Brak dodatkowego WHT |
| Niemcy | 15% | 26,375% | Solidaritätszuschlag |
| Irlandia | 15% | 25% | Wiele spółek tech zarejestrowanych w IE |
| Holandia | 15% | 15% | Shell, ASML, etc. |
| Szwajcaria | 15% | 35% | Bardzo wysoki bez UPO |
| Kanada | 15% | 25% | |
| Japonia | 10% | 20,42% | |
| Australia | 15% | 30% | |
| Luksemburg | 15% | 15% | Fundusze |

> **Mec. Nowak:** "Ta tabela to uproszczenie. W rzeczywistości stawki zależą od procentu udziałów w spółce, typu inwestora (osoba fizyczna vs. instytucja), i czy spełnione są warunki beneficial ownership. Dla indywidualnego inwestora z mniejszościowym udziałem — te stawki są prawidłowe."

### Przepływ 5: Straty z Lat Poprzednich

```
Actor: Użytkownik

[Użytkownik] ──EnterPriorYearLoss──→ «PriorYearLossRegister»
    │
    ├── Input: Rok straty, kwota, typ (akcje/pochodne vs. krypto)
    │
    ├── Walidacja:
    │       ├── Czy strata jest z ostatnich 5 lat? (art. 9 ust. 3)
    │       ├── Czy kwota odpisu ≤ 50% straty?
    │       └── Czy typ straty pasuje do typu dochodu?
    │
    ├── Policy: LossDeductionPolicy
    │       ├── Automatycznie zastosuj max 50% z najstarszej straty
    │       ├── ALBO: pokaż opcje i pozwól user wybrać
    │       │       └── ⚠️ HOTSPOT HS-001: "pokaż opcje" = doradztwo?
    │       │           Decyzja: pokazujemy MOŻLIWOŚCI, nie REKOMENDACJĘ
    │       │           "Masz stratę 10 000 PLN z 2023. Możesz odliczyć od 0 do 5 000 PLN."
    │       │           NIE: "Rekomendujemy odliczenie 5 000 PLN"
    │       │
    │       └── Event: PriorYearLossApplied
    │
    └── Read Model: LossDeductionSummary
```

---

## 13:15–15:00 — Business Case Deep Dive

### Problem Statement

> W Polsce na koniec 2025 roku jest szacunkowo **2,5 mln aktywnych rachunków maklerskich** (dane KDPW). Z tego ok. **800 tys.** korzysta z zagranicznych brokerów (IBKR, Degiro, eToro, Trading212, XTB zagraniczny oddział). Każdy z tych 800 tys. musi SAMODZIELNIE obliczyć i złożyć PIT-38. Nie dostaje PIT-8C.
>
> Koszt rozliczenia u doradcy podatkowego: **300-2000 PLN** w zależności od liczby transakcji.
> Czas samodzielnego rozliczenia: **4-40 godzin** w zależności od złożoności.
> Ryzyko błędu: **BARDZO WYSOKIE** — przeliczenia walut, FIFO cross-broker, spin-offy.

### Target Personas

**Persona 1: "Marcin, Day Trader" (wiek 28)**
- 3000+ transakcji rocznie
- XTB (CFD) + IBKR (akcje USA)
- Handluje akcjami, CFD na indeksy, krypto na Binance
- Nie ma czasu na ręczne rozliczenie
- Gotów zapłacić 200 PLN za automatyczne rozliczenie
- Pain: "W zeszłym roku siedziałem nad Excelem 3 dni. I tak nie wiem czy dobrze policzyłem."

**Persona 2: "Anna, Inwestor Pasywny" (wiek 42)**
- 20-50 transakcji rocznie
- Degiro (ETF-y), IBKR (dywidendy USA)
- Kupuje i trzyma. Reinwestuje dywidendy.
- Chce mieć pewność że rozliczenie jest poprawne
- Gotowa zapłacić 50-100 PLN
- Pain: "Doradca podatkowy bierze 500 PLN za moje proste rozliczenie. Musi być tańsza opcja."

**Persona 3: "Krzysztof, Krypto Native" (wiek 24)**
- 500+ transakcji krypto, kilka giełd
- Binance, Kraken, MetaMask (DeFi)
- Krypto + trochę akcji memowych na Trading212
- Kompletnie nie rozumie polskiego prawa podatkowego
- Gotów zapłacić 100-150 PLN
- Pain: "Mam transakcje na 5 giełdach i w 3 portfelach. Nie wiem co jest podatkowe a co nie."

### Rozmiar Rynku

| Segment | Szacunek | Uzasadnienie |
|---|---|---|
| **TAM** (Total Addressable Market) | 2,5 mln rachunków × 100 PLN ARPU = **250 mln PLN/rok** | Wszyscy inwestorzy w PL |
| **SAM** (Serviceable Available) | 800 tys. z zagranicznymi brokerami × 150 PLN = **120 mln PLN/rok** | Ci, którzy MUSZĄ liczyć sami |
| **SOM** (Serviceable Obtainable) | 50 tys. userów × 120 PLN = **6 mln PLN/rok** (rok 3) | Realistyczny target rok 3 |

### Analiza Konkurencji

| Narzędzie | Kraj | Co robi | Ograniczenia | Cena |
|---|---|---|---|---|
| **pit.pl / e-pity.pl** | PL | Generowanie PIT-38, ale user musi SAM wpisać kwoty | Brak importu, brak obliczeń | Free / 30 PLN |
| **ZenLedger / CoinTracker** | USA | Krypto tax w USA | Nie znają polskiego prawa, brak NBP | $49-$199 |
| **Koinly** | Global | Krypto tax, multi-country | PL support ograniczony, nie zna FIFO PL, brak PIT-38 | €49-€279 |
| **Divly** | SE | Krypto+stocks, multi-country | PL w "beta", brak PIT-8C porównania | €49-€149 |
| **Excel / Google Sheets** | - | DIY | Ręcznie, błędogenne, bez NBP | Free |
| **Doradca podatkowy** | PL | Pełna obsługa | Drogi, długi czas oczekiwania | 300-2000 PLN |

**Mariusz Gil:** "Nikt nie robi tego dobrze dla polskiego rynku. To jest gap."

**Łukasz [risk]:** "Gap jest, ale pytanie czy jest wystarczająco duży i czy barrier to entry jest wystarczająco wysoki."

### Model Przychodów

**Rekomendacja zespołu: Freemium + Per-Year Pricing**

| Plan | Cena | Zawartość |
|---|---|---|
| **Free** | 0 PLN | Import z 1 brokera, max 50 transakcji, podgląd obliczenia (bez eksportu) |
| **Basic** | 79 PLN / rok podatkowy | 1-3 brokerów, max 500 transakcji, PIT-38 XML, audit trail |
| **Pro** | 149 PLN / rok podatkowy | Unlimited brokerów, unlimited transakcji, krypto, dywidendy, PIT/ZG, porównanie PIT-8C |
| **Advisor** | 49 PLN / klient / rok | Dla doradców podatkowych — multi-client dashboard |

**Unit Economics (rok 3):**
- 50 000 userów (10k free, 25k basic, 12k pro, 3k advisor × 10 klientów)
- Revenue: 25k × 79 + 12k × 149 + 3k × 49 × 10 = 1 975k + 1 788k + 1 470k = **5,2 mln PLN/rok**
- Infrastructure: ~300k PLN/rok (seasonal, mostly dormant)
- Team: 8-10 osób × ~25k PLN/mies = ~2,5 mln PLN/rok
- Margin: ~45%

### Kluczowy Problem Prawny

**Mec. Wiśniewska — prezentacja:**

> "Art. 2 ust. 1 ustawy o doradztwie podatkowym (Dz.U. 1996 nr 102 poz. 475 z późn. zm.):
> *'Czynności doradztwa podatkowego obejmują: 1) udzielanie podatnikom porad, opinii i wyjaśnień z zakresu ich obowiązków podatkowych i celnych...'*
>
> Pytanie: czy AUTOMATYCZNE obliczenie podatku z sugestią kwoty do zapłaty to 'porada' czy 'kalkulacja'?
>
> Moja interpretacja: jeśli system oblicza na podstawie DANYCH UŻYTKOWNIKA i PRZEPISÓW PRAWA — to jest narzędzie kalkulacyjne. Jak kalkulator. Kalkulator nie doradza.
>
> ALE: jeśli system mówi 'powinieneś odliczyć stratę teraz, bo za 2 lata przepadnie' — to jest doradztwo.
>
> ALE 2: jeśli system mówi 'UWAGA: masz stratę z 2021, ostatni rok na odliczenie' — to jest... informacja? Granica jest cienka.
>
> Rekomendacja: jasny disclaimer w regulaminie + brak sugestii optymalizacyjnych + opcja exportu do doradcy."

**Tomasz [DP]:**

> "Dodam od siebie: Ministerstwo Finansów jest coraz bardziej przychylne narzędziom cyfrowym. e-Urząd Skarbowy, Twój e-PIT — oni CHCĄ żeby ludzie składali poprawne zeznania. Narzędzie które pomaga — raczej będą wspierać niż zwalczać. Pod warunkiem że nie udaje doradcy."

---

## 15:00–16:30 — Architecture & Technical Concerns

### Propozycja Architektury (Marek [senior-dev])

```
                    ┌─────────────────────┐
                    │    Frontend (SPA)    │
                    │  React + TypeScript  │
                    └──────────┬──────────┘
                               │ HTTPS
                    ┌──────────┴──────────┐
                    │    API Gateway       │
                    │  (Auth, Rate Limit)  │
                    └──────────┬──────────┘
                               │
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
   ┌────┴────┐          ┌─────┴─────┐          ┌─────┴─────┐
   │ Import  │          │   Tax     │          │Declaration│
   │ Service │          │  Calc     │          │  Service  │
   │         │          │  Engine   │          │           │
   └────┬────┘          └─────┬─────┘          └─────┬─────┘
        │                     │                      │
   ┌────┴────┐          ┌─────┴─────┐          ┌─────┴─────┐
   │ Broker  │          │  Domain   │          │  Template │
   │ Adapters│          │  Model    │          │  Engine   │
   │ (ACL)   │          │  (Core)   │          │  (PIT-38) │
   └─────────┘          └───────────┘          └───────────┘
        │                     │
   External:             External:
   - Broker APIs         - NBP API
   - CSV files           - Tax Rules DB
```

**Michał P. [security]:**

> "Wymogi:
> 1. Szyfrowanie at rest (AES-256) dla wszystkich danych użytkownika
> 2. TLS 1.3 in transit
> 3. Zero-trust: każdy serwis autentykuje każdy request
> 4. Audit log: kto, kiedy, co — niemutowalny
> 5. GDPR: data retention policy, right to erasure, data portability
> 6. Penetration testing przed każdym sezonem podatkowym
> 7. SOC 2 Type II — cel na rok 2
> 8. NIE przechowujemy haseł do kont brokerskich — OAuth only
> 9. NIP jako PII — szyfrowany osobno, hashowany do wyszukiwania"

**Bartek [devops]:**

> "Stack proposal:
> - Kubernetes na AWS (EKS)
> - Seasonal auto-scaling (skalowanie do zera poza sezonem? nie, ale do minimum)
> - PostgreSQL z encryption at rest
> - Redis dla cache NBP
> - S3 dla przechowywania CSV (encrypted, lifecycle policy)
> - GitHub Actions CI/CD
> - Terraform IaC
> - Datadog monitoring"

**Sylwester [SRE]:**

> "SLA propozycja:
> - Obliczenia: max 30 sekund dla 5000 transakcji
> - Import: max 60 sekund dla pliku 50k wierszy
> - Availability: 99.9% w sezonie podatkowym (luty-kwiecień)
> - RPO: 1 godzina, RTO: 4 godziny
> - Monitoring: każde obliczenie logowane z checksumą — jeśli coś się zmieni w logice, widzimy impact"

**Aleksandra [perf]:**

> "Benchmarki do walidacji:
> - FIFO matching: 10 000 transakcji w < 5 sekund
> - NBP rate lookup: < 10ms (cached)
> - PIT-38 generation: < 3 sekundy
> - Concurrent users: 5 000 w sezonie podatkowym
> - Pre-compute: po każdej zmianie (import/edycja), przelicz w tle"

---

## 16:30–17:30 — Risk Assessment & Go/No-Go

### Risk Register

| ID | Kategoria | Ryzyko | P | I | Score | Owner | Mitigation | Status |
|---|---|---|---|---|---|---|---|---|
| R-001 | Prawne | Aplikacja zostanie uznana za doradztwo podatkowe | M | H | **HIGH** | Łukasz | Opinia prawna, disclaimer, brak sugestii optymalizacyjnych | OPEN |
| R-002 | Prawne | Błąd w obliczeniach → user dostaje karę z US → pozywa nas | M | H | **HIGH** | Łukasz | Regulamin, ubezpieczenie OC, audit trail, beta z doradcami | OPEN |
| R-003 | Techniczne | Broker zmienia format CSV → import pada | H | M | **HIGH** | Ania | Versioned parsers, community reporting, monitoring | OPEN |
| R-004 | Techniczne | API NBP niedostępne w sezonie | L | H | **MEDIUM** | Sylwester | Cache, fallback na ostatni znany kurs, retry | OPEN |
| R-005 | Biznesowe | Rynek za mały / users nie chcą płacić | M | H | **HIGH** | Łukasz | Validate z landing page + waitlist PRZED budową | OPEN |
| R-006 | Regulacyjne | Zmiana przepisów podatkowych (np. inna stawka, inne zasady FIFO) | M | M | **MEDIUM** | Mec. Wiśniewska | Versioned tax rules, monitoring legislacji | OPEN |
| R-007 | Security | Wyciek danych finansowych użytkowników | L | H | **HIGH** | Michał P. | Encryption, pentest, SOC 2, bug bounty | OPEN |
| R-008 | Techniczne | FIFO cross-broker daje inne wyniki niż doradca podatkowy | M | H | **HIGH** | Marek | Test suite z realnymi case'ami od Tomasza, walidacja z doradcami | OPEN |
| R-009 | Operacyjne | Peak sezon → system pada → users nie mogą rozliczyć PIT-38 | L | H | **HIGH** | Sylwester | Load testing, auto-scaling, pre-compute, CDN | OPEN |
| R-010 | Biznesowe | Konkurent (np. e-pity.pl) doda tę samą funkcjonalność | M | M | **MEDIUM** | Łukasz | Szybkość, jakość, doradcy jako kanał dystrybucji | OPEN |
| R-011 | Techniczne | Corporate actions (spin-off, merger) — za dużo edge cases | H | M | **HIGH** | Marek | V1 without corporate actions, manual override | OPEN |
| R-012 | Prawne | Broker zabroni scrape'owania / użycia nieoficjalnego API | M | M | **MEDIUM** | Mec. Wiśniewska | CSV upload jako primary, API jako secondary | OPEN |
| R-013 | Techniczne | Krypto — łańcuch transakcji niemożliwy do odtworzenia (DeFi) | H | M | **HIGH** | Marek | V1: tylko CEX (giełdy centralne), DeFi = out of scope | OPEN |
| R-014 | Biznesowe | Sezonowość — przychody tylko luty-kwiecień | H | M | **HIGH** | Łukasz | Plan Advisor (doradcy podatkowi) daje recurring revenue, raportowanie YTD | OPEN |
| R-015 | Operacyjne | Support zalewany pytaniami "czy moje obliczenie jest poprawne" | H | M | **HIGH** | Łukasz | Self-service audit trail, FAQ, community forum, chatbot | OPEN |
| R-016 | Techniczne | Multi-year FIFO — user nie ma danych z poprzednich lat | H | M | **HIGH** | Marek | Manual opening balance, import historyczny | OPEN |
| R-017 | Prawne | US wysyła zapytanie o nasze obliczenia — czy musimy ujawnić algorytm? | L | M | **LOW** | Mec. Wiśniewska | Tajemnica przedsiębiorstwa, udostępniamy dane usera nie algorytm | OPEN |
| R-018 | Security | Phishing — fałszywa strona podszywająca się pod nas | M | M | **MEDIUM** | Michał P. | DMARC, monitoring domen, edukacja userów | OPEN |
| R-019 | Techniczne | Rounding errors w obliczeniach (grosze się kumulują) | M | L | **LOW** | Marek | Decimal arithmetic, nie float. Zaokrąglanie per przepisy (do pełnego złotego) | OPEN |
| R-020 | Biznesowe | Doradcy podatkowi lobbują przeciwko nam | L | M | **LOW** | Łukasz | Plan Advisor — doradcy jako PARTNERZY, nie konkurenci | OPEN |
| R-021 | Techniczne | Timezone mismatch — transakcja w NYC o 23:00 = następny dzień w PL | M | M | **MEDIUM** | Marek | Konfiguracja timezone per broker, walidacja z userem | OPEN |
| R-022 | Regulacyjne | KNF / UODO kontrola | L | M | **LOW** | Łukasz | Compliance from day 1, GDPR audit | OPEN |
| R-023 | Operacyjne | User importuje 500k transakcji (krypto bot) | M | M | **MEDIUM** | Aleksandra | Rate limits, tiered pricing, async processing | OPEN |
| R-024 | Techniczne | PIT-38 XML schema zmiana przez MF | M | M | **MEDIUM** | Ania | Monitoring schema MF, versioned templates | OPEN |
| R-025 | Biznesowe | Free tier attracts users who never convert | H | L | **MEDIUM** | Łukasz | Aggressive paywall (obliczenie za darmo, export za $) | OPEN |

### Głosowanie Go/No-Go

| Uczestnik | Głos | Uzasadnienie |
|---|---|---|
| **Alberto Brandolini** | **CONDITIONAL GO** | "Domena jest wystarczająco złożona żeby stanowić barierę wejścia. Core domain jest jasny. ALE: nie zaczynajcie od wszystkiego. Start z akcjami zagranicznymi + FIFO. To jest wasz killer feature." |
| **Mariusz Gil** | **CONDITIONAL GO** | "Model domenowy jest bogaty i ciekawy. To jest prawdziwy DDD project, nie CRUD. Warunek: TDD od dnia zero w Tax Calculation Engine. Każda reguła podatkowa = test. Jeśli test nie przechodzi, reguła nie istnieje." |
| **Mec. Wiśniewska** | **CONDITIONAL GO** | "Pod warunkiem opinii prawnej potwierdzającej że to narzędzie kalkulacyjne, nie doradztwo. Regulamin z wyłączeniem odpowiedzialności. Ubezpieczenie OC." |
| **Mec. Nowak** | **GO** | "UPO to skończony zbiór — 90+ umów ale się nie zmieniają co rok. To jest database, nie rocket science. Warto." |
| **Joanna Makowska** | **CONDITIONAL GO** | "Audit trail jest kluczowy. Jeśli user dostanie kontrolę z US, musi umieć wydrukować skąd wzięły się jego liczby. Bez audit trail — nie wypuszczajcie." |
| **Tomasz Kędzierski** | **GO** | "Miałem klienta który zapłacił mi 2000 PLN za rozliczenie 4000 transakcji z IBKR. Gdyby istniało takie narzędzie — zapłaciłby 149 PLN. Rynek jest. Boli mnie to jako doradcę, ale rynek jest." |
| **Marek [senior-dev]** | **CONDITIONAL GO** | "Pod warunkiem że v1 ma ograniczony scope: akcje + ETF + dywidendy + FIFO. Bez krypto, bez CFD, bez corporate actions. Te dojdą w v2/v3." |
| **Ania [data]** | **CONDITIONAL GO** | "Pod warunkiem że zaczniemy od 3 brokerów: IBKR, Degiro, XTB. Reszta w kolejnych sprintach. I CSV-first, API-second." |
| **Kasia [QA]** | **CONDITIONAL GO** | "Pod warunkiem że Tomasz da nam 20 realnych zestawów transakcji do testów. Z oczekiwanymi wynikami. To jest nasz golden dataset." |
| **Michał P. [security]** | **CONDITIONAL GO** | "Pod warunkiem security review przed każdym release i encryption from day 1. Nie dodajemy security później — albo od początku, albo wcale." |
| **Łukasz [risk]** | **CONDITIONAL GO** | "Ryzyka są zarządzalne. Warunek: walidacja rynku PRZED budową. Landing page, waitlist, 1000 zapisów = green light do development." |
| **Aleksandra [perf]** | **GO** | "Obliczenia są CPU-bound ale nie rocket science. FIFO jest O(n) per instrument. Z pre-compute damy radę." |
| **Sylwester [SRE]** | **GO** | "Sezonowość jest wyzwaniem ale nie blokerem. AWS Lambda dla obliczeń, cold start akceptowalny." |
| **Bartek [devops]** | **GO** | "Stack jest standardowy. Nic egzotycznego. Terraform + EKS + RDS. 3 miesiące do MVP infra." |
| **Zofia [front]** | **GO** | "UI jest skomplikowane ale doable. Najważniejsze: drill-down obliczenia i jasny audit trail." |
| **Michał W. [QA]** | **CONDITIONAL GO** | "Test automation pipeline z property-based testing dla reguł podatkowych. Testy nie mogą być ręczne." |
| **Paweł [front]** | **GO** | "Użytkownicy potrzebują tego. Widziałem fora — ludzie się męczą z Excelem." |

### Ostateczna Rekomendacja

## ✅ CONDITIONAL GO

**Warunki brzegowe (MUST before development starts):**

1. ✅ Opinia prawna potwierdzająca model "narzędzie kalkulacyjne"
2. ✅ Walidacja rynku: landing page + 1000 zapisów na waitlist
3. ✅ 20 realnych zestawów testowych od Tomasza (doradca podatkowy)
4. ✅ Ubezpieczenie OC dla spółki
5. ✅ Scope v1 zamrożony: akcje + ETF + dywidendy zagraniczne + FIFO + 3 brokerzy (IBKR, Degiro, XTB)

**Warunki brzegowe (MUST before public release):**

6. ✅ Security review + pentest
7. ✅ Audit trail w pełni funkcjonalny
8. ✅ Disclaimer prawny w UI i regulaminie
9. ✅ GDPR compliance potwierdzone
10. ✅ Load test na 5000 concurrent users

---

## 17:30–18:00 — Wnioski Końcowe i Next Steps

### Scope v1 (MVP)

| In Scope | Out of Scope (v2+) |
|---|---|
| Akcje zagraniczne (FIFO) | CFD |
| ETF | Kryptowaluty |
| Dywidendy zagraniczne (UPO) | Opcje, warranty |
| Import CSV (IBKR, Degiro, XTB) | Corporate actions (spin-off, merger) |
| Kursy NBP | ESOP/RSU |
| PIT-38 XML export | DeFi |
| PIT/ZG per kraj | API brokerów |
| Audit trail | Plan Advisor |
| Straty z lat poprzednich | Korekty zeznań |
| Porównanie z PIT-8C | Integracja e-Urząd Skarbowy |

### Backlog — Epiki i User Stories

#### Epic 1: Import Transakcji

**US-001: Import CSV z Interactive Brokers**
- Priority: **MUST**
- Complexity: **L**
- As a: inwestor korzystający z IBKR
- I want to: wgrać plik Activity Statement (CSV) z IBKR
- So that: moje transakcje zostaną automatycznie zaimportowane

Acceptance Criteria:
```
GIVEN user ma plik CSV z IBKR (Activity Statement / Flex Query)
WHEN user wgrywa plik na stronę
THEN system rozpoznaje format IBKR
AND parsuje transakcje (buy/sell/dividend)
AND wyświetla podgląd z liczbą transakcji per typ
AND pozwala zaakceptować lub odrzucić import
```

**US-002: Import CSV z Degiro**
- Priority: **MUST**
- Complexity: **L**
- Analogicznie do US-001 dla formatu Degiro.

**US-003: Import CSV z XTB**
- Priority: **MUST**
- Complexity: **M**

**US-004: Obsługa błędów importu**
- Priority: **MUST**
- Complexity: **M**
- As a: użytkownik
- I want to: zobaczyć które transakcje się nie zaimportowały i dlaczego
- So that: mogę je poprawić ręcznie

Acceptance Criteria:
```
GIVEN import zawiera transakcje z błędami (brak daty, nieznany instrument)
WHEN import zostanie przetworzony
THEN system importuje poprawne transakcje
AND oznacza błędne z opisem błędu
AND pozwala user'owi edytować lub usunąć błędne transakcje
```

**US-005: Wykrywanie duplikatów**
- Priority: **SHOULD**
- Complexity: **M**

```
GIVEN user wgrywa plik CSV który częściowo pokrywa się z wcześniejszym importem
WHEN system przetwarza plik
THEN wykrywa duplikaty (ta sama data, instrument, ilość, cena)
AND oznacza je jako potencjalne duplikaty
AND pozwala user'owi zdecydować (pomiń / importuj mimo to)
```

#### Epic 2: Obliczanie Podatku

**US-006: FIFO matching dla akcji**
- Priority: **MUST**
- Complexity: **XL**
- As a: inwestor
- I want to: zobaczyć które zakupy zostały dopasowane do których sprzedaży (FIFO)
- So that: mogę zweryfikować poprawność obliczeń

Acceptance Criteria:
```
GIVEN user ma zaimportowane transakcje buy i sell dla tego samego ISIN
WHEN system oblicza podatek
THEN dopasowuje sell do buy metodą FIFO (najstarszy buy pierwszy)
AND oblicza zysk/stratę per dopasowanie
AND wyświetla tabelę matching z kolumnami: data buy, cena buy, data sell, cena sell, ilość, zysk/strata PLN
AND obsługuje partial matching (sprzedano mniej niż kupiono w jednej transzy)
AND działa cross-broker (FIFO per ISIN, nie per broker)
```

**US-007: Przeliczenie walut kursem NBP**
- Priority: **MUST**
- Complexity: **L**
- As a: inwestor handlujący w walutach obcych
- I want to: mieć automatyczne przeliczenie transakcji na PLN kursem NBP

```
GIVEN transakcja jest w walucie obcej (np. USD)
WHEN system oblicza podatek
THEN pobiera kurs średni NBP z ostatniego dnia roboczego PRZED datą transakcji
AND przelicza cenę i prowizję na PLN
AND wyświetla użyty kurs przy każdej transakcji
```

**US-008: Obliczanie podatku od dywidend zagranicznych**
- Priority: **MUST**
- Complexity: **L**

```
GIVEN user otrzymał dywidendę z zagranicznej spółki
AND system zna kraj źródła i stawkę WHT z UPO
WHEN system oblicza podatek od dywidend
THEN przelicza dywidendę brutto na PLN (kurs NBP dzień-1)
AND oblicza polski podatek = 19% × dywidenda_brutto_PLN
AND odejmuje zapłacony WHT (przeliczony na PLN)
AND wyświetla kwotę do dopłaty w PL (lub 0 jeśli WHT ≥ 19%)
```

**US-009: Rozdzielenie koszyków podatkowych**
- Priority: **MUST**
- Complexity: **M**

```
GIVEN user ma transakcje na akcjach, ETF-ach i dywidendach
WHEN system generuje podsumowanie
THEN rozdziela: sekcja C PIT-38 (zyski kapitałowe) vs sekcja D (dywidendy)
AND NIE łączy strat z dywidend z zyskami z akcji
```

**US-010: Straty z lat poprzednich**
- Priority: **SHOULD**
- Complexity: **M**

```
GIVEN user ma stratę z roku 2022 w kwocie 10 000 PLN
AND obecny rok podatkowy to 2025 (w oknie 5-letnim)
WHEN user wprowadza stratę
THEN system pozwala odliczyć max 50% (5 000 PLN) w tym roku
AND wyświetla: "Pozostała strata do odliczenia: 5 000 PLN, ważna do 2027"
AND NIE sugeruje optymalnej kwoty odpisu (brak doradztwa)
```

#### Epic 3: Generowanie Deklaracji

**US-011: Podgląd PIT-38**
- Priority: **MUST**
- Complexity: **M**

```
GIVEN obliczenia roczne są zakończone
WHEN user klika "Podgląd PIT-38"
THEN widzi formularz PIT-38 z wypełnionymi polami
AND może drill-down z każdego pola do transakcji które go tworzą
```

**US-012: Eksport PIT-38 XML**
- Priority: **MUST**
- Complexity: **L**

```
GIVEN user zaakceptował podgląd PIT-38
WHEN user klika "Eksportuj XML"
THEN system generuje plik XML zgodny ze schematem e-Deklaracje MF
AND user może wgrać go do systemu e-Deklaracje
```

**US-013: Generowanie PIT/ZG**
- Priority: **MUST**
- Complexity: **M**

```
GIVEN user ma dochody z więcej niż jednego kraju
WHEN system generuje deklarację
THEN tworzy osobny PIT/ZG dla KAŻDEGO kraju
AND sumuje dochody per kraj
AND uwzględnia stawkę WHT per kraj z UPO
```

#### Epic 4: Audit & Weryfikacja

**US-014: Audit trail obliczeń**
- Priority: **MUST**
- Complexity: **L**

```
GIVEN obliczenie zostało wykonane
WHEN user klika "Pokaż szczegóły"
THEN widzi pełny breakdown: każda transakcja, użyty kurs NBP, FIFO matching, prowizje
AND może wyeksportować do PDF
```

**US-015: Porównanie z PIT-8C**
- Priority: **SHOULD**
- Complexity: **M**

```
GIVEN user wgrał PIT-8C od polskiego brokera
AND user ma zaimportowane transakcje z tego samego brokera
WHEN user klika "Porównaj"
THEN system zestawia kwoty z PIT-8C z własnymi obliczeniami
AND wyświetla różnice (jeśli są) z wyjaśnieniem możliwych przyczyn
```

#### Epic 5: Konto & UX

**US-016: Rejestracja i logowanie**
- Priority: **MUST**
- Complexity: **S**

**US-017: Wybór roku podatkowego**
- Priority: **MUST**
- Complexity: **S**

**US-018: Dashboard — podsumowanie roku**
- Priority: **MUST**
- Complexity: **M**

```
GIVEN user jest zalogowany i ma zaimportowane transakcje
WHEN otwiera dashboard
THEN widzi: łączny przychód, łączny koszt, zysk/strata, szacowany podatek
AND breakdown per typ instrumentu
AND breakdown per broker
AND status: "Gotowe do eksportu" / "Brakuje danych"
```

**US-019: Ręczne dodawanie transakcji**
- Priority: **SHOULD**
- Complexity: **M**

**US-020: Edycja i usuwanie transakcji**
- Priority: **SHOULD**
- Complexity: **S**

**US-021: Multi-broker view**
- Priority: **MUST**
- Complexity: **M**

```
GIVEN user ma dane z 3 brokerów
WHEN przegląda transakcje
THEN może filtrować per broker lub widzieć wszystko razem
AND FIFO działa cross-broker (nie per broker)
```

**US-022: Eksport danych do doradcy podatkowego**
- Priority: **SHOULD**
- Complexity: **S**

```
GIVEN user chce oddać rozliczenie doradcy
WHEN klika "Eksportuj dla doradcy"
THEN system generuje Excel/CSV z: wszystkimi transakcjami, kursami NBP, FIFO matching, obliczeniami
AND doradca może zweryfikować i skorygować
```

**US-023: Responsywny UI**
- Priority: **SHOULD**
- Complexity: **M**

**US-024: Onboarding wizard**
- Priority: **SHOULD**
- Complexity: **M**

```
GIVEN nowy user zakłada konto
WHEN przechodzi onboarding
THEN wizard pyta: z jakich brokerów korzystasz? Jakie instrumenty? Jaki rok rozliczasz?
AND na tej podstawie konfiguruje workspace
```

**US-025: Powiadomienia o terminach**
- Priority: **COULD**
- Complexity: **S**

```
GIVEN zbliża się 30 kwietnia
WHEN user nie wyeksportował jeszcze PIT-38
THEN system wysyła reminder (email / push)
```

---

## Sekcja: Polskie Prawo Podatkowe — Kompendium (prezentacja Tomasz + Mec. Wiśniewska)

### Kto musi składać PIT-38?

Każda osoba fizyczna będąca polskim rezydentem podatkowym, która w danym roku:
- Sprzedała papiery wartościowe (akcje, obligacje, ETF)
- Zamknęła pozycje na instrumentach pochodnych (CFD, opcje, futures)
- Sprzedała kryptowaluty za walutę fiat
- Otrzymała dywidendę z zagranicznej spółki (obowiązek dopłaty)

**NIE musi** składać PIT-38 jeśli:
- Miał tylko dywidendy z polskich spółek (broker pobrał 19% ryczałtowo)
- Miał tylko odsetki z polskich obligacji/lokat (bank pobrał 19%)
- Kupował ale NIE sprzedawał w danym roku

### Terminy

| Zdarzenie | Termin |
|---|---|
| Rok podatkowy | 1 stycznia — 31 grudnia |
| PIT-8C od brokera | Do 28 lutego roku następnego |
| Złożenie PIT-38 | Do 30 kwietnia roku następnego |
| Zapłata podatku | Do 30 kwietnia roku następnego |
| Korekta PIT-38 | Do 5 lat od końca roku złożenia |

### Obliczanie przychodu i kosztu — krok po kroku

**Art. 17 ust. 1 pkt 6 ustawy o PIT:**
> Przychód = kwota należna ze sprzedaży papierów wartościowych

**Art. 22 ust. 1 pkt 38:**
> Koszt uzyskania przychodu = wydatki poniesione na nabycie + prowizje

**Art. 11a ust. 1:**
> Przeliczenie na PLN = kurs średni NBP z ostatniego dnia roboczego poprzedzającego dzień uzyskania przychodu / poniesienia kosztu

**Art. 24 ust. 10:**
> Metoda FIFO — przy sprzedaży części pakietu, uważa się że sprzedano te nabyte najwcześniej

### Tabela: Łączenie koszyków podatkowych

| Instrument A | Instrument B | Czy można łączyć zyski/straty? |
|---|---|---|
| Akcje | ETF | ✅ TAK |
| Akcje | CFD | ✅ TAK (oba w sekcji C PIT-38) |
| Akcje | Opcje | ✅ TAK |
| Akcje | Obligacje | ✅ TAK |
| Akcje | Kryptowaluty | ❌ NIE — osobny koszyk |
| CFD | Kryptowaluty | ❌ NIE |
| Kryptowaluty | Kryptowaluty | ✅ TAK (w ramach koszyka krypto) |
| Dywidendy | Akcje (zyski kapitałowe) | ❌ NIE — osobna sekcja PIT-38 |
| Dywidendy PL | Dywidendy zagraniczne | N/A — PL pobrane ryczałtem, zagraniczne w PIT-38 |

### Sankcje za błędy

**Mec. Wiśniewska:**

> "Art. 56 §1 Kodeksu karnego skarbowego: *'Podatnik, który uchylając się od opodatkowania, nie ujawnia właściwemu organowi przedmiotu lub podstawy opodatkowania lub składa deklarację...'*
>
> Kary:
> - Zaniżenie podatku < 5× minimalnego wynagrodzenia = **wykroczenie skarbowe** — grzywna do 57 000 PLN (2025)
> - Zaniżenie podatku > 5× = **przestępstwo skarbowe** — grzywna do 33,6 mln PLN lub kara pozbawienia wolności
> - Odsetki za zwłokę: ~14,5% rocznie (2025)
> - Czynny żal (art. 16 KKS): jeśli sam się poprawisz PRZED kontrolą — brak kary
>
> W praktyce: US rzadko karze za nieumyślne błędy jeśli podatnik współpracuje. Ale TRZEBA mieć dokumentację skąd wzięły się liczby."

### Co sprawdza US podczas kontroli inwestorów?

**Joanna Makowska:**

> "Z mojego doświadczenia:
> 1. Porównanie PIT-38 z PIT-8C — czy kwoty się zgadzają
> 2. Dla zagranicznych brokerów: czy w ogóle złożono PIT-38 (wymiany informacji CRS/FATCA)
> 3. Kryptowaluty: czy zadeklarowano przychody z giełd (Binance, Coinbase raportują do polskiego fiskusa od 2024 — DAC8)
> 4. Kursy walut: czy użyto prawidłowego kursu NBP
> 5. FIFO: czy kolejność jest prawidłowa
> 6. Prowizje: czy nie zawyżono kosztów
> 7. Straty z lat poprzednich: czy mieszczą się w limitach (5 lat, 50%)"

---

## Open Questions & Action Items

### Open Questions

| ID | Pytanie | Assigned | Due | Status |
|---|---|---|---|---|
| OQ-001 | Czy sugerowanie kwoty odpisu straty to doradztwo podatkowe? | Mec. Wiśniewska | 2026-04-15 | OPEN |
| OQ-002 | Jak traktować overnight fee w CFD? Koszt czy nie? | Tomasz [DP] | 2026-04-15 | OPEN |
| OQ-003 | Czy NBP API ma SLA / rate limits? | Ania [data] | 2026-04-08 | OPEN |
| OQ-004 | Jak rozwiązać timezone mismatch (transakcja NYC → kurs NBP PL)? | Tomasz + Marek | 2026-04-10 | OPEN |
| OQ-005 | Czy staking rewards zablokowane (locked staking) to przychód? | Tomasz [DP] | 2026-04-15 | OPEN |
| OQ-006 | Jakie ubezpieczenie OC jest potrzebne i ile kosztuje? | Łukasz [risk] | 2026-04-12 | OPEN |
| OQ-007 | Czy IBKR Flex Query API jest oficjalne i stabilne? | Ania [data] | 2026-04-08 | OPEN |
| OQ-008 | Jak wygląda schema XML e-Deklaracje dla PIT-38 2025? | Ania [data] | 2026-04-10 | OPEN |
| OQ-009 | Czy dual-listed instruments mają ten sam ISIN? | Kasia [QA] | 2026-04-08 | OPEN |
| OQ-010 | Jak obsłużyć fractional shares (ułamkowe akcje) w FIFO? | Tomasz + Marek | 2026-04-10 | OPEN |

### Action Items

| # | Action | Owner | Priority | Due |
|---|---|---|---|---|
| AI-001 | Uzyskać opinię prawną ws. narzędzie vs. doradztwo | Mec. Wiśniewska + Łukasz | P0 | 2026-04-15 |
| AI-002 | Stworzyć landing page z waitlist | Paweł + Zofia | P0 | 2026-04-12 |
| AI-003 | Tomasz przygotowuje 20 zestawów testowych (golden dataset) | Tomasz [DP] | P0 | 2026-04-20 |
| AI-004 | Zbadać koszt ubezpieczenia OC | Łukasz [risk] | P0 | 2026-04-15 |
| AI-005 | PoC: parser IBKR CSV + FIFO matching | Marek [senior-dev] | P1 | 2026-04-25 |
| AI-006 | PoC: integracja NBP API + cache | Ania [data] | P1 | 2026-04-20 |
| AI-007 | UX wireframes: import flow + dashboard + PIT-38 preview | Zofia [front] | P1 | 2026-04-25 |
| AI-008 | Security threat model (STRIDE) | Michał P. [security] | P1 | 2026-04-20 |
| AI-009 | Zebrać stawki WHT z wszystkich UPO | Mec. Nowak | P1 | 2026-04-30 |
| AI-010 | Infrastruktura PoC: Terraform + EKS + RDS | Bartek [devops] | P2 | 2026-05-05 |

---

## Appendix: Kluczowe Cytaty i Przełomowe Momenty

### Moment zwrotu Dnia 1 — "To jest doradztwo!"

Godzina 14:10. Tomasz [DP] klei kartkę "Zasugerowano optymalny odpis straty". Łukasz [risk] podchodzi, czyta, i mówi:

> **Łukasz:** "STOP. Jeśli system sugeruje użytkownikowi ile straty odliczyć — to jest czynność doradztwa podatkowego w rozumieniu art. 2 ustawy. Czy zdajecie sobie sprawę co to oznacza?"
>
> **Tomasz:** "Łukasz ma rację. Sugerowanie = doradztwo. Doradztwo bez uprawnień = wykroczenie."
>
> **Brandolini:** "To zmienia scope produktu. Różowa kartka, biggest size."
>
> **Mariusz Gil:** "Albo nie. Kalkulator podatkowy w Excelu to też 'narzędzie'. Granica jest w INTENCJI i KOMUNIKACJI. Pokazujesz możliwości, nie dajesz rekomendacji."
>
> **Mec. Wiśniewska:** "Mariusz ma punkt. Ale to trzeba potwierdzić opinią prawną ZANIM napiszecie pierwszą linię kodu."

### Moment zwrotu Dnia 2 — "FIFO jest cross-broker"

Godzina 10:30. Kasia [QA] pyta o scenariusz z Apple kupionymi na IBKR i sprzedanymi na Degiro.

> **Kasia:** "Czy FIFO jest per broker?"
>
> **Tomasz:** "NIE. FIFO jest per instrument (ISIN). Jeśli kupiłeś 100 Apple na IBKR w styczniu i 100 Apple na Degiro w marcu, a sprzedajesz 50 na Degiro w czerwcu — to FIFO mówi, że sprzedajesz te z IBKR ze stycznia. Cross-broker."
>
> **Marek [senior-dev]:** "To zmienia CAŁĄ architekturę. Aggregate TaxPositionLedger nie może być per broker. Musi być per ISIN, cross-broker."
>
> **Mariusz Gil:** "Widzicie? Dlatego robimy Event Storming a nie od razu kodujemy. Ten insight zaoszczędził wam miesiąc refaktoryzacji."

### Cytat zamykający — Brandolini

> **Alberto Brandolini:** "W dwa dni odkryliście więcej niż odkrylibyście w trzy miesiące developmentu. Macie 31 hotspotów, 118 zdarzeń, 8 bounded contexts, i jeden existential risk (doradztwo vs. narzędzie). Jeśli rozwiążecie ten jeden risk — macie produkt. Jeśli nie — macie kosztowny eksperyment. To jest wartość Event Stormingu: wiedzieć ZANIM zbudujecie."

---

*Dokument wygenerowany przez Fully Featured AI Agent Team. Track record sesji Event Storming, 2026-04-01/02.*
*Facylitacja: Alberto Brandolini, Mariusz Gil.*
