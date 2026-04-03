# ADR-002: PHP 8.4 + Symfony 7.2 jako stack technologiczny

## Status
ACCEPTED

## Data
2026-04-03

## Kontekst

Potrzebujemy stacku który:
- Obsłuży złożoną domenę (obliczenia podatkowe, FIFO, CQRS)
- Ma dojrzały ekosystem (ORM, queue, security, validation)
- Zespół zna i jest produktywny
- Umożliwia TDD i Clean Architecture
- Działa w Docker bez lokalnych runtime'ów

Rozważane opcje:
1. **PHP + Symfony** — dojrzały, DDD-friendly, Messenger jako bus, Doctrine ORM
2. **TypeScript + Node.js (Fastify/NestJS)** — shared types FE↔BE, ale ekosystem DDD słabszy
3. **Java + Spring Boot** — enterprise, ale overweight dla MVP
4. **Go** — performance, ale brak ORM, verbose dla domain modeling

## Decyzja

**PHP 8.4+ z Symfony 7.2+**

### Backend

| Komponent | Technologia | Powód |
|---|---|---|
| Język | PHP 8.4 | readonly classes, enums, typed properties, match expressions, fibers, JIT |
| Framework | Symfony 7.2 | Messenger, Security, Validator, Serializer, Mailer — all-in-one |
| Command/Query Bus | Symfony Messenger | CQRS out-of-the-box, sync + async transports |
| ORM | Doctrine 3.x | Mature, XML mapping (separation from domain), migrations |
| Math | brick/math (BigDecimal) | Precyzja finansowa — NIGDY `float` dla pieniędzy |
| HTTP Client | Symfony HttpClient | NBP API, Stripe webhooks |
| Validation | Symfony Validator | DTO validation, custom constraints |
| Serialization | Symfony Serializer | JSON ↔ DTO, CSV parsing support |

### Frontend

| Komponent | Technologia | Powód |
|---|---|---|
| Templates | Twig | Server-rendered, SEO, SSR native |
| Interaktywność | Turbo (Hotwire) | Turbo Frames dla partial updates, Turbo Streams dla real-time |
| JS behavior | Stimulus | Lightweight, progressive enhancement |
| CSS | Tailwind 4.x | Utility-first, szybki development |
| Build | Symfony AssetMapper / Vite | Bez webpack, prosty setup |

### Dlaczego Twig+Hotwire zamiast React SPA?

To jest apka formularzowa: upload CSV → tabele transakcji → formularz PIT-38.
- Nie potrzebuje offline capability
- Nie potrzebuje complex client-side state management
- Server-rendered HTML = accessibility out-of-the-box
- Jeden stack (PHP), jeden deployment, brak CORS, brak API versioning
- Turbo Frames dają dynamiczne fragmenty (drill-down, filtry) bez SPA overhead

### Infrastruktura

| Komponent | Technologia |
|---|---|
| Dev environment | Docker Compose |
| Database | PostgreSQL 17 |
| Cache/Queue | Redis 7 |
| Storage | Flysystem + AWS S3 |
| Payments | Stripe PHP SDK |
| Auth | Symfony Security + custom magic link |
| CI/CD | GitHub Actions |
| Deploy | AWS ECS Fargate + RDS + ElastiCache |
| IaC | Terraform |
| Monitoring | Grafana Cloud + Sentry |

### Quality tools

| Narzędzie | Rola |
|---|---|
| PHPStan (level max) | Static analysis, type safety |
| PHP-CS-Fixer | Coding standards (PSR-12) |
| Rector | Automated refactoring, upgrades |
| PHPUnit | Unit, integration, golden dataset tests |
| Infection | Mutation testing (MSI > 80%) |
| Deptrac | Dependency analysis (enforce layer boundaries) |

## Konsekwencje

### Pozytywne
- Jeden język (PHP) front+back — brak context switching
- Symfony Messenger = CQRS bez dodatkowych bibliotek
- Doctrine XML mapping = domain entities czyste od infrastructure
- Tailwind+Twig = szybkie UI development bez SPA complexity
- Docker Compose = `make dev` i gotowe

### Negatywne
- PHP nie ma native TypeScript-style type inference — mitigacja: PHPStan level max
- Twig+Hotwire ogranicza complex UI patterns — mitigacja: Stimulus controllers dla interaktywnych komponentów (file upload, tables)
- brick/math jest verbose — mitigacja: Money value object enkapsuluje całą arytmetykę

## Uczestnicy decyzji

| Osoba | Stanowisko |
|---|---|
| Marek [senior-dev] | "Symfony to naturalny wybór. Messenger jako bus, Doctrine ORM, Security. Nie wymyślamy koła." |
| Mariusz Gil | "Symfony Messenger jako command bus + query bus to sprawdzony pattern w polskiej społeczności DDD." |
| Aleksandra [perf] | "PHP 8.4 z JIT — 10k transakcji FIFO < 2 sekundy. Wystarczy." |
| Paweł [front] | "Twig+Hotwire = mniej kodu, mniej complexity. Jeśli za rok potrzebujemy React — frontend jest za Turbo Frames, podmiana fragment po fragmencie." |
