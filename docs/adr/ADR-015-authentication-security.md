# ADR-015: Authentication Security — Magic Link

## Status
ACCEPTED

## Data
2026-04-03

## Decyzja

**Magic link (email) jako jedyna metoda autentykacji. Brak haseł.**

### Specyfikacja tokenu
- **Entropia:** 256-bit (32 bytes), cryptographically random (`random_bytes(32)`)
- **Format:** URL-safe base64 (`base64url_encode`)
- **Expiry:** 15 minut od wygenerowania
- **Single-use:** token invalidowany po pierwszym użyciu (`used_at` NOT NULL → reject)
- **Storage:** hash tokenu w DB (SHA-256), NIE plaintext

### Rate limiting
- Max **3 magic link requests** per email per 15 minut
- Max **10 magic link requests** per IP per godzinę
- Max **50 magic link requests** per IP per dzień

### Email enumeration protection
- Response ZAWSZE: "Jeśli ten email istnieje w naszym systemie, wysłaliśmy link do logowania."
- Brak różnicy w response time (constant-time comparison)

### Session
- Session ID: regenerowany po login (Symfony default)
- Session duration: 7 dni (sliding)
- Max concurrent sessions: 3 per user
- Session bound to: User-Agent (strict)
- Sensitive operations (NIP change, data export, account delete): wymagają ponownego magic link

### Cookie
- `Secure` (HTTPS only)
- `HttpOnly` (no JS access)
- `SameSite=Lax`
- `Path=/`

### Przyszłość (v2)
- Opcjonalny TOTP (Google Authenticator) dla userów którzy chcą 2FA
- WebAuthn/passkeys jako alternatywa
