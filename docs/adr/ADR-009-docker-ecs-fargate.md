# ADR-009: Docker Compose (dev) + MyDevil (prod v1) + AWS (prod v2)

## Status
SUPERSEDED (partial) — zmiana z AWS ECS Fargate na MyDevil dla v1/beta. AWS jako ścieżka migracji.

## Data
2026-04-03 (updated)

## Kontekst

Potrzebujemy:
- Dev environment bez instalowania PHP/Postgres/Redis lokalnie
- Production tanio i szybko (MVP/beta: 100-500 userów)
- Koszt-efektywność (startup, nie korporacja)
- Ścieżka migracji gdy userbase urośnie

### Rozważane:
1. ~~**Kubernetes (EKS)**~~ — overkill
2. ~~**ECS Fargate**~~ — za drogi i złożony na start (18k PLN/rok). Dobry na v2.
3. ~~**AWS Lambda**~~ — cold start, Doctrine issues
4. ~~**VPS (Hetzner/DigitalOcean)**~~ — manual, ale viable
5. **MyDevil.net (shared hosting)** — PHP native, tani, SSH, cron, Postgres, Redis

## Decyzja

### v1 (MVP/beta → sezon 2027): **MyDevil.net**
### v2 (jeśli >5k userów): **migracja na AWS ECS Fargate**

### Dlaczego MyDevil na start?

> **Bartek [devops]:** "Nie potrzebujemy Terraform na 100 userów. MyDevil daje PHP 8.4, PostgreSQL, Redis, SSH, crona. Deploy przez Deployer. Koszt: 50-100 PLN/mies zamiast 1500+."
>
> **Sylwester [SRE]:** "Sezon podatkowy to 6 tygodni peak. Dla 500 userów MyDevil wystarczy. Jak będziemy mieli problem ze skalą — to będzie DOBRY problem."
>
> **Łukasz [risk]:** "18 400 PLN/rok na infra zanim mamy pierwszego płacącego klienta to ryzyko. 1 200 PLN/rok to non-issue."
>
> **Mariusz Gil:** "Architektura jest identyczna — Clean Architecture, Symfony, Doctrine. Zmienia się deployment, nie kod. To jest punkt decyzji na MyDevil."

---

## Development

```yaml
# docker-compose.yml — bez zmian, Docker Compose local
services:
  app:           # PHP-FPM + Symfony
  postgres:      # PostgreSQL 17
  redis:         # Redis 7 (cache + queue)
  messenger:     # Symfony Messenger worker
  mailpit:       # email testing
```

`make dev` → cały stack gotowy. `make test` → testy w kontenerze. Zero lokalnych zależności.

---

## Production v1 — MyDevil.net

### Architektura

```
┌──────────────────────────────────────────┐
│  Cloudflare (DNS + CDN + WAF + SSL)      │
│       │                                  │
│  MyDevil.net                             │
│  ├── PHP 8.4 (FastCGI)                   │
│  ├── Symfony 7.2                         │
│  ├── PostgreSQL 17                       │
│  ├── Redis (Devil Redis)                 │
│  ├── Cron (Messenger worker)             │
│  ├── Storage (CSV uploads, PDFs)         │
│  └── SMTP (magic link emails)            │
│                                          │
│  Zewnętrzne:                             │
│  ├── Cloudflare R2 (backup CSV, encrypted)│
│  ├── Stripe (płatności)                  │
│  └── NBP API (kursy walut)              │
│                                          │
└──────────────────────────────────────────┘
```

### Co MyDevil zapewnia

| Usługa | Dostępność | Uwagi |
|---|---|---|
| PHP 8.4 | ✅ native | FastCGI, opcache, JIT |
| PostgreSQL 17 | ✅ | Osobna instancja per konto |
| Redis | ✅ (Devil Redis) | Cache + Messenger transport |
| SSH | ✅ | Deploy, debugging, CLI |
| Cron | ✅ | Messenger worker co minutę |
| Let's Encrypt SSL | ✅ | Ale używamy Cloudflare SSL |
| Composer | ✅ | Na SSH |
| Git | ✅ | Deploy pull |

### Messenger Worker via Cron

MyDevil nie ma long-running procesów. Messenger worker uruchamiany przez cron:

```cron
# Co minutę — przetwarza max 10 messages, max 55 sekund
* * * * * cd /home/taxpilot/domains/taxpilot.pl/app && php bin/console messenger:consume async --limit=10 --time-limit=55 --no-interaction >> /home/taxpilot/var/log/messenger.log 2>&1
```

Dla v1 (mały ruch) to wystarczy. Import CSV 1000 wierszy przetwarza się w ~2-3 cyklach crona.

### Deploy — Deployer

```php
// deploy.php (Deployer)
host('taxpilot.pl')
    ->set('remote_user', 'taxpilot')
    ->set('deploy_path', '/home/taxpilot/domains/taxpilot.pl/app')
    ->set('branch', 'main');

task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'artisan:migrate', // doctrine:migrations:migrate
    'deploy:cache:clear',
    'deploy:publish',
]);
```

`make deploy` → deploy na produkcję. ~30 sekund.

### Szyfrowanie NIP — bez AWS KMS

Na MyDevil nie ma KMS. Alternatywa:

```php
// sodium_crypto_secretbox — built-in PHP 8.4
// Klucz w .env (ENCRYPTION_KEY=base64:...)
// NIE w repozytorium — osobno na serwerze

final readonly class NIPEncryptor
{
    public function __construct(
        private string $key, // 32 bytes, z env
    ) {}

    public function encrypt(string $nip): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($nip, $nonce, $this->key);
        return base64_encode($nonce . $cipher);
    }

    public function decrypt(string $encrypted): string
    {
        $decoded = base64_decode($encrypted);
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return sodium_crypto_secretbox_open($cipher, $nonce, $this->key);
    }
}
```

### Backup

```cron
# Codziennie o 3:00 — dump PostgreSQL + upload do Cloudflare R2
0 3 * * * /home/taxpilot/scripts/backup.sh
```

`backup.sh` — pg_dump → gzip → encrypt (gpg) → upload do Cloudflare R2 (S3-compatible, darmowe 10GB).

### Email (magic link)

MyDevil SMTP lub zewnętrzny (Resend.com / Mailgun). Koszt: ~0 PLN na start (limity free tier wystarczą dla beta).

### Cloudflare (CDN + WAF + SSL)

Cloudflare free tier przed MyDevil:
- SSL termination (Full Strict)
- CDN dla statycznych assetów (Tailwind, Stimulus)
- WAF basic rules (bot protection, rate limiting)
- DDoS protection
- Page Rules (cache static, bypass dynamic)
- Koszt: **0 PLN**

### Monitoring

| Narzędzie | Koszt | Co monitoruje |
|---|---|---|
| Sentry (free tier) | 0 PLN | Errors, exceptions, performance |
| UptimeRobot (free) | 0 PLN | Uptime, alerting |
| Cloudflare Analytics | 0 PLN | Traffic, threats, cache |
| Custom health endpoint | 0 PLN | `/health` — DB, Redis, Messenger status |
| Log rotation na MyDevil | 0 PLN | `var/log/` z logrotate |

### Koszt v1

| Pozycja | Koszt/mies | Koszt/rok |
|---|---|---|
| MyDevil (hosting) | ~80 PLN | ~960 PLN |
| Cloudflare (free) | 0 PLN | 0 PLN |
| Cloudflare R2 (backup) | ~5 PLN | ~60 PLN |
| Sentry (free) | 0 PLN | 0 PLN |
| Resend/Mailgun (free tier) | 0 PLN | 0 PLN |
| Domena (.pl) | — | ~50 PLN |
| **TOTAL** | **~85 PLN** | **~1 070 PLN** |

vs. AWS: **18 400 PLN/rok**. Oszczędność: **17 330 PLN/rok**.

---

## Ścieżka migracji: MyDevil → AWS

### Trigger migracji

Migrujemy na AWS gdy:
1. Sezon podatkowy (luty-kwiecień) powoduje timeout/błędy na MyDevil
2. >5 000 aktywnych userów
3. Potrzebujemy auto-scaling
4. Wymagania compliance (SOC 2, dedicated infra)

### Co się zmienia

| Element | MyDevil | AWS |
|---|---|---|
| Hosting | Shared hosting | ECS Fargate |
| DB | MyDevil PostgreSQL | RDS PostgreSQL |
| Redis | Devil Redis | ElastiCache (AUTH + TLS) |
| Storage | Local + R2 backup | S3 (SSE-KMS) |
| NIP encryption | sodium_crypto_secretbox | AWS KMS |
| Worker | Cron co minutę | ECS task (long-running) |
| Deploy | Deployer (SSH) | GitHub Actions → ECR → ECS |
| WAF | Cloudflare free | Cloudflare Pro / AWS WAF |
| Monitoring | Sentry + UptimeRobot | Grafana Cloud + CloudWatch |
| SSL | Cloudflare | Cloudflare / ACM |
| Koszt | ~1k PLN/rok | ~18k PLN/rok |

### Co się NIE zmienia

**ZERO zmian w kodzie aplikacji.** Clean Architecture gwarantuje:
- Domain layer: bez zmian
- Application layer: bez zmian
- Infrastructure layer: zmiana konfiguracji (env vars), nie kodu
- Symfony Messenger: zmiana transport DSN, nie handlerów
- Doctrine: zmiana DATABASE_URL, nie mappingów
- Flysystem: zmiana adapter (local → S3), nie kodu

Migracja = nowy Terraform + zmiana env vars + DNS switch.

## Konsekwencje

### Pozytywne
- **17x taniej na start** — 1k vs 18k PLN/rok
- **Zero complexity infra** — deploy to `make deploy`, nie Terraform pipeline
- **Szybki start** — konto MyDevil w 10 minut, deploy w 30 minut
- **Validate before invest** — nie wydajemy na infra zanim walidujemy rynek
- **Clean Architecture = portable** — migracja to config, nie rewrite

### Negatywne
- **Brak auto-scaling** — mitigacja: Cloudflare cache + pre-compute + MyDevil wystarczy na 500 userów
- **Shared hosting** — inni userzy na tym samym serwerze, noisy neighbor risk — mitigacja: MyDevil ma SSD NVMe, dobra izolacja, monitoring
- **Brak managed backups** — mitigacja: custom backup script + Cloudflare R2
- **Cron-based worker** — max 1 message/sec throughput — mitigacja: wystarczy na v1, async import czeka max minutę
- **Brak KMS** — mitigacja: sodium_crypto_secretbox jest cryptographically sound, klucz w .env na serwerze

## Uczestnicy decyzji

| Osoba | Stanowisko |
|---|---|
| Bartek [devops] | "MyDevil na start. Deploy to SSH + Deployer. Jak urośniemy — ECS Fargate." |
| Sylwester [SRE] | "500 userów na MyDevil — spokojnie. Cloudflare chroni edge. Monitor health endpoint." |
| Łukasz [risk] | "Validate first, scale later. 17k PLN oszczędności na infra." |
| Aleksandra [perf] | "Pre-compute obliczenia po imporcie. Cache w Redis. MyDevil daje radę." |
| Michał P. [security] | "Cloudflare SSL+WAF+DDoS. sodium_crypto_secretbox na NIP. Redis na MyDevil jest lokalny — nie exposed. OK na start." |
