# ADR-014: Secrets Management

## Status
ACCEPTED

## Data
2026-04-03

## Decyzja

### v1 (MyDevil)
- Sekrety w `.env.local` na serwerze (NIE w repozytorium)
- `.env.example` z placeholderami w repo
- `.gitignore` zawiera `.env.local`
- Klucze: `ENCRYPTION_KEY`, `NIP_HMAC_KEY`, `DATABASE_URL`, `REDIS_URL`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `MAGIC_LINK_SECRET`, `SENTRY_DSN`
- Backup `.env.local` szyfrowany, przechowywany osobno

### v2 (AWS)
- AWS Secrets Manager dla wszystkich sekretów
- ECS task role z IAM policy (least privilege)
- Automatic rotation: DB password co 90 dni, API keys co 180 dni

### Reguły (obie wersje)
1. **ZERO sekretów w git** — nawet w historii
2. **ZERO sekretów w Docker image** — inject przez env vars
3. **ZERO sekretów w logach** — Monolog processor stripuje
4. **Rotacja:** magic link secret co 90 dni, encryption key — NIE rotować (dane zaszyfrowane starym kluczem)
5. **CI/CD:** GitHub Actions secrets dla deploy credentials only
