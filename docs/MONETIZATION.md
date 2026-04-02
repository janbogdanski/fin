# TaxPilot -- Strategia Monetyzacji

Data: 2026-04-02

---

## Model: Pay-per-use + Free tier

Jednorazowa oplata za rok podatkowy (nie subskrypcja). Uzytkownik placi raz, rozlicza rok, wraca za rok.

---

## Plany cenowe

| Plan | Cena | Zakres |
|---|---|---|
| **Free** | 0 PLN | 1 broker, max 30 pozycji, PIT-38 XML |
| **Standard** | 99 PLN | Unlimited brokerow i pozycji, PIT-38 + PIT/ZG XML, audit trail |
| **Pro** | 199 PLN | Wszystko ze Standard + cross-year FIFO (przenoszenie lotow z lat ubieglych) + odliczanie strat z lat poprzednich (art. 9 ust. 3) |

### Uzasadnienie cen

- **Free** -- hook na przetestowanie. 30 pozycji pokrywa casual inwestora z 1 brokerem. Generuje word-of-mouth.
- **Standard (99 PLN)** -- ponizej PodatekGieldowy (149 PLN) i TaxAll (149 PLN). Pokrywa 80% userow.
- **Pro (199 PLN)** -- cross-year FIFO to prawdziwa wartosc dla power userow. Tansza niz biuro rachunkowe (500-2000 PLN).

---

## Value gate

Uzytkownik importuje CSV i widzi wyniki za darmo (do limitu). Paywall pojawia sie przy:
- Generowaniu XML (powyzej free tier)
- Cross-year FIFO (Pro)
- Odliczaniu strat (Pro)

Kluczowe: uzytkownik widzi wartosc ZANIM placi. Nie kupuje kota w worku.

---

## Platnosci: Stripe

### Architektura

Port `PaymentGatewayPort` w warstwie domeny. Adapter Stripe w warstwie infrastruktury. Mozliwosc wymiany providera bez zmian w domenie.

### MVP flow

```
Uzytkownik klika "Kup"
    |
    v
Backend tworzy Stripe Checkout Session
    |
    v
Redirect do Stripe hosted checkout
    |
    v
Stripe przetwarza platnosc
    |
    v
Webhook: checkout.session.completed
    |
    v
Backend aktywuje plan dla UserId + rok podatkowy
    |
    v
Redirect do /dashboard z aktywnym planem
```

### Komponenty Stripe MVP

| Komponent | Opis |
|---|---|
| `Checkout Session` | Stripe-hosted payment page. Zero PCI compliance po stronie TaxPilot. |
| `Webhook endpoint` | `POST /api/stripe/webhook` -- odbiera `checkout.session.completed`. Weryfikuje sygnature (`Stripe-Signature`). Idempotentny (deduplikacja po `session.id`). |
| `PaymentGatewayPort` | Interfejs domenowy: `createCheckoutSession(UserId, Plan, TaxYear): CheckoutUrl` |
| `StripePaymentAdapter` | Implementacja portu. Uzywa `stripe/stripe-php` SDK. |

### Bezpieczenstwo

- Webhook signature verification (HMAC SHA-256)
- Idempotentnosc -- powtorzony webhook nie aktywuje planu ponownie
- Stripe API key w env, nigdy w kodzie
- Checkout Session z `metadata` (userId, plan, taxYear) do reconciliation
