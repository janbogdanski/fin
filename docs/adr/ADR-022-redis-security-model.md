# ADR-022: Redis Security Model — auth, TLS, network exposure

## Status
ACCEPTED

## Data
2026-04-05

## Kontekst

Redis pełni trzy role w TaxPilot:
1. **Cache aplikacji** — rate-limiter buckets, odpowiedzi API NBP (kurs dnia)
2. **Session storage** — sessiony użytkowników (magic link auth)
3. **Messenger transport** — kolejka asynchronicznych zadań (import CSV)

Sessje zawierają token użytkownika i plan (Free/Standard/Pro). Kompromitacja Redis = możliwość przejęcia sesji użytkownika.

Pytanie: czy Redis wymaga TLS (szyfrowanie in-transit)?

## Analiza per środowisko

### Development (Docker Compose)

Redis dostępny wyłącznie w sieci Docker (`redis:6379`). Nie jest wystawiony na `localhost` ani sieć zewnętrzną. Połączenia w ramach Docker network są izolowane od hosta.

Wymagania:
- Password auth: **wymagane** (domyślnie włączone przez `--requirepass`)
- TLS: **nie wymagane** — ruch nie opuszcza Docker network

### Production v1 — MyDevil.net

Michał P. [security] w ADR-009: *"Redis na MyDevil jest lokalny — nie exposed. OK na start."*

Devil Redis (usługa MyDevil) jest dostępny **wyłącznie z poziomu tego samego konta** poprzez socket lub localhost. Nie istnieje żadne połączenie sieciowe wystawione na zewnątrz.

Wymagania:
- Password auth: **wymagane** — silne hasło generowane przy provisioning (`openssl rand -hex 32`)
- TLS: **nie wymagane** — brak network exposure. TLS nad localhost to overhead bez korzyści bezpieczeństwa.
- Unix socket: **preferowany** nad TCP localhost dla wydajności

Konfiguracja REDIS_URL dla MyDevil:
```
# Przez TCP localhost (domyślny tryb Devil Redis)
REDIS_URL=redis://:STRONG_PASSWORD_HERE@127.0.0.1:DEVIL_REDIS_PORT

# Przez Unix socket (jeśli dostępny)
REDIS_URL=redis://:STRONG_PASSWORD_HERE@/tmp/redis.sock
```

### Production v2 — AWS ElastiCache

ElastiCache Redis (Cluster Mode disabled, replication group) zarządzany przez AWS:
- **AUTH token**: wymagany — generowany przy provisioning, rotowany regularnie
- **TLS in-transit**: **obowiązkowe** — włączone przez atrybut `transit_encryption_enabled = true`
- **TLS at-rest**: włączone przez `at_rest_encryption_enabled = true`
- URL Symfony: `rediss://:AUTH_TOKEN@cluster.endpoint:6380` (scheme `rediss://`)
- Certyfikat AWS RootCA: weryfikowany przez Symfony/Predis (nie `verify_peer=false`)

## Decyzja

**TLS jest wymagane wyłącznie przy AWS ElastiCache (v2).** Na MyDevil (v1) Redis jest lokalny — TLS to overkill.

### Reguła generalna

| Środowisko | Auth | TLS | Uzasadnienie |
|---|---|---|---|
| Docker Dev | Password | Nie | Docker network, nie exposed |
| MyDevil v1 | Password (silne) | Nie | Lokalny socket/localhost, nie exposed |
| AWS ElastiCache v2 | AUTH token | Tak (`rediss://`) | Sieć VPC, managed TLS by AWS |

### Co zmienić natychmiast (v1 pre-launch checklist)

1. Wygenerować silne hasło Redis na MyDevil: `openssl rand -hex 32`
2. Ustawić `REDIS_URL=redis://:GENERATED_PASSWORD@127.0.0.1:PORT` w `.env.prod` (na serwerze, poza repo)
3. Upewnić się że Devil Redis nie jest wystawiony poza localhost (konfiguracja MyDevil — domyślnie OK)
4. Włączyć `bind 127.0.0.1` w konfiguracji Redis (MyDevil: domyślnie OK)
5. Zablokować dostęp do portu Redis przez firewall (MyDevil: domyślnie OK, shared hosting)

### Konfiguracja prod v2 (AWS — przyszłość)

Przy migracji na AWS dodać do Terraform:
```hcl
resource "aws_elasticache_replication_group" "taxpilot" {
  transit_encryption_enabled = true
  at_rest_encryption_enabled = true
  auth_token                 = var.redis_auth_token
  # ...
}
```

Symfony `REDIS_URL`: `rediss://:${REDIS_AUTH_TOKEN}@${ELASTICACHE_ENDPOINT}:6380`

## Konsekwencje

### Pozytywne
- Brak overengineering na v1 — TLS nad lokalnym Redis to zero korzyści bezpieczeństwa
- Ścieżka migracji do AWS jest jasna: jeden env var (`rediss://` zamiast `redis://`)
- Kod aplikacji bez zmian — zmiana tylko konfiguracji

### Negatywne / Ryzyka mitigowane
- Ryzyko: ktoś przypadkowo wystawi port Redis na zewnątrz na MyDevil → mitigacja: MyDevil shared hosting nie pozwala na to bez jawnej konfiguracji; monitorowanie przez `/health` endpoint
- Ryzyko: słabe hasło Redis → mitigacja: obowiązek generowania przez `openssl rand -hex 32` przed deployem

## Uczestnicy decyzji

| Osoba | Stanowisko |
|---|---|
| Michał P. [security] | "Redis lokalny na MyDevil — password auth wystarczy. TLS przy ElastiCache." |
| Bartek [devops] | "Nie wdrażamy TLS-over-localhost. Deploy prostszy, zero gain security." |
| Sylwester [SRE] | "Health endpoint sprawdza Redis. Monitoring pokryje przypadkowy network exposure." |
