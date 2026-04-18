-- Dev seed: fixed test account with PRO tier
-- Usage: make seed
-- Login: make login-link (prints URL to paste in browser)
-- Compatible with migration: Version20260415000000
-- WARNING: dev-only — never run against staging or production

DO $$
DECLARE
    -- fixed sentinel UUIDs for dev seed (not valid v4/v7 — intentionally distinct)
    v_user_id UUID := '00000000-0000-4000-8000-000000000001';
    v_email TEXT := 'dev@taxpilot.local';
BEGIN
    INSERT INTO users (id, email, created_at, referral_code, bonus_transactions)
    VALUES (v_user_id, v_email, NOW(), 'TAXPILOT-DEV00001', 0)
    ON CONFLICT (email) DO NOTHING;

    INSERT INTO payments (id, user_id, stripe_session_id, product_code, amount_cents, currency, status, created_at)
    VALUES (
        '00000000-0000-4000-8000-000000000002',
        v_user_id,
        'dev-seed-pro-session',
        'PRO',
        19900,
        'PLN',
        'PAID',
        NOW()
    )
    ON CONFLICT (stripe_session_id) DO NOTHING;
END $$;
