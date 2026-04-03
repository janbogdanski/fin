# ADR-018: CSV Upload Security

## Status
ACCEPTED

## Data
2026-04-03

## Decyzja

CSV upload to główny wektor ataku. Kontrole bezpieczeństwa:

### File validation
- **Max size:** 50 MB (konfigurowalny)
- **Type check:** magic bytes (nie extension) — sprawdź że plik jest text/CSV
- **Filename:** ignoruj oryginalną nazwę, generuj UUID (`{uuid}.csv`)
- **Encoding:** detect i normalize do UTF-8

### CSV injection prevention
Sanitize ALL string values z CSV — stripuj leading characters:
- `=` (Excel formula)
- `+` (Excel formula)
- `-` (Excel formula)
- `@` (Excel formula)
- `\t` (tab injection)
- `\r` (carriage return injection)

### Rate limiting
- Max **10 uploads** per user per godzinę
- Max **3 concurrent uploads** per user

### Processing
- CSV parsowany w Messenger worker (async) z limitami:
  - Memory: 256 MB
  - Time: 5 minut per plik
  - Max rows: 500 000 (powyżej → error)
- Worker ma minimal permissions (read CSV, write transactions)

### Storage
- **MyDevil (v1):** local storage, katalog poza document root, UUID filename
- **AWS (v2):** S3 SSE-KMS
- **Retention:** 30 dni od uploadu → auto-delete (cron)
- **NIGDY** w Redis cache
- **NIGDY** serwowane bezpośrednio — download przez controller z auth check

### Monitoring
- Alert na: upload > 10MB, upload rate > 5/min per IP, parsing failure rate > 50%
