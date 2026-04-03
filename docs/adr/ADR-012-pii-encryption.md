# ADR-012: PII Encryption — NIP i dane osobowe

## Status
ACCEPTED

## Data
2026-04-03

## Decyzja

### NIP (RESTRICTED)
- **Szyfrowanie:** `sodium_crypto_secretbox` (XSalsa20-Poly1305) — built-in PHP 8.4
- **Klucz:** 32 bytes, `ENCRYPTION_KEY` w `.env` na serwerze (nie w repo)
- **Blind index:** HMAC-SHA256 z osobnym kluczem (`NIP_HMAC_KEY`) — do wyszukiwania po NIP bez deszyfrowania
- **Doctrine custom type:** `EncryptedNipType` — automatyczny encrypt/decrypt
- **NIE w Redis** — nigdy cachować NIP
- **NIE w logach** — Sentry `before_send` scrub, Monolog processor strip
- **NIE w backupach** — backup DB jest szyfrowany (gpg), lub NIP column encrypted

### v1 (MyDevil): sodium_crypto_secretbox
### v2 (AWS): migracja na AWS KMS (key rotation automatyczna)

### Email (CONFIDENTIAL)
- Szyfrowane at-rest przez PostgreSQL encryption (MyDevil: TDE jeśli dostępne, inaczej application-level)
- W logach: tylko hash

### Data classification matrix

| Dane | Klasyfikacja | Encrypted | Cache | Logi | Retencja |
|---|---|---|---|---|---|
| NIP | RESTRICTED | Column-level | NIGDY | NIGDY | 5 lat (anonymized) |
| Email | CONFIDENTIAL | DB-level | Session ref | Hash only | Do usunięcia konta |
| Transakcje | CONFIDENTIAL | DB-level | Aggregaty only | ID only | 5 lat |
| Obliczenia | CONFIDENTIAL | DB-level | Aggregaty | Summary | 5 lat |
| Kursy NBP | PUBLIC | Nie | Pełny cache | Tak | Bezterminowo |
| ISIN | PUBLIC | Nie | Tak | Tak | Bezterminowo |
| CSV upload | RESTRICTED | Application | NIGDY | Hash only | 30 dni → delete |
| Audit trail | CONFIDENTIAL | DB-level | NIGDY | Read-only | 5 lat (immutable) |
