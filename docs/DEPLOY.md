# Deploy — MyDevil

## Architektura

```
GitHub Actions CI (ci.yml) → [wszystkie 3 stage'y zielone] → deploy.yml
                                                                    ↓
                                              rsync → ~/domains/taxpilot.pl/
                                                          ↓
                                              composer install --no-dev
                                              tailwindcss --minify
                                              doctrine:migrations:migrate
                                              cache:warmup
                                              smoke test HTTP 200/302
```

## GitHub Secrets — wymagane

Dodaj w: **GitHub repo → Settings → Secrets and variables → Actions**

| Secret | Przykład | Opis |
|--------|---------|------|
| `MYDEVIL_HOST` | `s42.mydevil.net` | SSH hostname serwera |
| `MYDEVIL_USER` | `janbogdanski` | Login SSH |
| `MYDEVIL_SSH_KEY` | (klucz prywatny) | Klucz prywatny Ed25519 |
| `MYDEVIL_DEPLOY_PATH` | `~/domains/taxpilot.pl` | Ścieżka docelowa |
| `MYDEVIL_DOMAIN` | `taxpilot.pl` | Domena do smoke testu |
| `APP_SECRET` | `php -r "echo bin2hex(random_bytes(32));"` | Symfony app secret |
| `DATABASE_URL` | `postgresql://user:pass@localhost:5432/dbname?serverVersion=17` | Postgres na MyDevil |
| `ENCRYPTION_KEY` | `php -r "echo base64_encode(random_bytes(32));"` | AES-256 dla NIP/names |
| `NIP_HMAC_KEY` | `php -r "echo base64_encode(random_bytes(32));"` | HMAC dla NIP wyszukiwania |
| `REDIS_URL` | `redis://:HASLO@127.0.0.1:PORT` | Redis devil app (local) |
| `MAILER_DSN` | `smtp://user:pass@smtp.mydevil.net:587` | SMTP — patrz niżej |

## Generowanie wartości sekretów

```bash
# APP_SECRET
php -r "echo bin2hex(random_bytes(32));"

# ENCRYPTION_KEY i NIP_HMAC_KEY
php -r "echo base64_encode(random_bytes(32));"
```

> ⚠️ ENCRYPTION_KEY i NIP_HMAC_KEY muszą być **stałe po pierwszym deploy**.
> Zmiana = utrata dostępu do zaszyfrowanych danych w DB (NIP, imię, nazwisko).

## Konfiguracja MyDevil — jednorazowa

### 1. SSH key

```bash
# Lokalnie: wygeneruj klucz Ed25519
ssh-keygen -t ed25519 -C "taxpilot-deploy" -f ~/.ssh/taxpilot_deploy

# Klucz publiczny → dodaj na MyDevil
cat ~/.ssh/taxpilot_deploy.pub
# Panel MyDevil: SSH Keys → Add key

# Klucz prywatny → GitHub Secret MYDEVIL_SSH_KEY
cat ~/.ssh/taxpilot_deploy
```

### 2. PHP version

W panelu MyDevil: **WWW → taxpilot.pl → PHP version → PHP 8.4**

LUB przez SSH:
```bash
devil www set taxpilot.pl php /usr/local/bin/php84
```

### 3. Document root

Panel MyDevil: **WWW → taxpilot.pl → Document root → zmień na: `~/domains/taxpilot.pl/public`**

### 4. Baza danych

Panel MyDevil: **MySQL/PostgreSQL → Create database**
- Ustaw PostgreSQL 17 (jeśli dostępny) lub najnowszy
- Zapisz host, user, password, dbname → do `DATABASE_URL`

### 5. Redis

```bash
# SSH na serwer
devil tools install redis
# Notuj port i ustaw hasło
devil redis set PASSWORD twoje-silne-haslo
# Sprawdź port
devil redis list
```

### 6. Pierwsze uruchomienie

```bash
# SSH na serwer — jednorazowe
mkdir -p ~/domains/taxpilot.pl/var/cache ~/domains/taxpilot.pl/var/log
mkdir -p ~/domains/taxpilot.pl/var/uploads
```

## Mailer

### Opcja A: MyDevil SMTP (najprostsza)
```
MAILER_DSN=smtp://login%40domena.pl:haslo@mail.mydevil.net:587?encryption=tls
```

### Opcja B: Resend (lepsza dostarczalność, darmowe 3k/mies)
```
# Zainstaluj: composer require symfony/http-client nyholm/psr7
MAILER_DSN=resend+api://API_KEY@default
```

### Opcja C: Mailgun
```
MAILER_DSN=mailgun+https://KEY:DOMAIN@default
```

## Flow automatyczny

Po każdym `git push main`:
1. CI uruchamia 3 stage'y (lint + testy + security)
2. Jeśli CI ✅ → deploy.yml startuje automatycznie
3. rsync + migrate + cache warmup + smoke test
4. Jeśli smoke test ❌ → deployment oznaczony jako failed (rollback ręczny)

## Rollback

```bash
# SSH na serwer
cd ~/domains/taxpilot.pl

# Sprawdź poprzedni working commit
git log --oneline -5   # (git nie jest na serwerze — rollback = re-deploy poprzedniego taga)
```

Alternatywnie: zmień kod, push do main → automatyczny re-deploy.

## Zmienne środowiskowe: co jest gdzie

| Gdzie | Zawartość |
|-------|-----------|
| `.env` (git) | Domyślne wartości, `CHANGE_ME` placeholdery |
| `.env.test` (git) | Wartości testowe dla CI |
| `.env.local` (serwer, NIE w git) | Produkcyjne sekrety — nadpisuje `.env` |
| GitHub Secrets | Źródło dla `.env.local` — deploy pisze go na serwer |
