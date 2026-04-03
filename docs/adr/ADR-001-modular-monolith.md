# ADR-001: Modular Monolith zamiast Microservices

## Status
ACCEPTED

## Data
2026-04-03

## Kontekst

Event Storming zidentyfikował 8 Bounded Contexts. Naturalna pokusa to microservices — jeden serwis per BC. Jednak:

- Zespół to 3-4 developerów, nie 30
- Projekt jest w fazie MVP — potrzebujemy szybkiego feedbacku, nie skalowalności
- Bounded Contexts komunikują się synchronicznie (FIFO matching wymaga kursów NBP w tym samym request cycle)
- Distributed transactions (import → obliczenie → deklaracja) byłyby nightmare w microservices
- Observability i debugging jest wielokrotnie prostszy w monolicie

## Decyzja

**Modular Monolith** — jeden deployment, moduły z czystymi granicami.

- Każdy Bounded Context = osobny namespace/moduł w `src/`
- Moduły komunikują się przez Symfony Messenger (in-process events)
- Shared Kernel (`src/Shared/`) zawiera value objects używane cross-module (Money, ISIN, UserId)
- Każdy moduł ma własny publiczny interfejs (Application layer) — inne moduły nie sięgają do Domain bezpośrednio
- Baza danych: shared PostgreSQL, osobne tabele per moduł (nie osobne schematy — zbyt wcześnie)

### Granice modułów enforced przez:
1. **PHPStan rule**: moduł A nie może importować `Infrastructure/` ani `Domain/` modułu B — tylko `Application/` (publiczne API)
2. **Deptrac**: dependency checker — waliduje że nie ma cyklicznych zależności i że Domain nie zależy od Infrastructure

## Konsekwencje

### Pozytywne
- Jeden deployment = prostszy DevOps, CI/CD, monitoring
- Transakcje bazodanowe w jednym procesie — brak distributed transaction problem
- Refactoring granic modułów jest łatwy (rename namespace, nie redesign API)
- Szybki start development — nie trzeba infrastruktury per serwis

### Negatywne
- Skalowanie jest per-aplikacja, nie per-moduł (ale: peak to 5k concurrent — wystarczy)
- Jeden deployment failure = cała apka padnie (mitigacja: health checks, graceful degradation)
- Ryzyko: z czasem granice modułów się rozmyją jeśli nie pilnujemy

### Ścieżka migracji
Jeśli w przyszłości moduł (np. BrokerImport) będzie bottleneckiem:
1. Messenger transport zmienić z `sync://` na `redis://` (async)
2. Wyciągnąć moduł do osobnego deploymentu
3. Komunikacja przez te same Messenger messages — zmienia się transport, nie kod

## Uczestnicy decyzji

| Osoba | Stanowisko |
|---|---|
| Mariusz Gil | "Modular monolith z czystymi granicami. Microservices to premature optimization na tym etapie." |
| Marek [senior-dev] | "Jeden repo, jeden deploy, jeden pipeline. Prostota." |
| Bartek [devops] | "Kubernetes per serwis dla 4 devów to absurd. ECS Fargate z jednym task definition." |
| Sylwester [SRE] | "Monitoring jednej apki vs. 8 serwisów. Wybór oczywisty." |
