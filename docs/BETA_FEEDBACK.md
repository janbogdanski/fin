# Beta Feedback Setup — TaxPilot

## Metadata

| | |
|---|---|
| Status | DRAFT — requires Product Owner decision |
| Purpose | Feedback channel, triage model and review cadence for closed beta |
| Closes | BETA-BLK-010 when confirmed and filled in |
| Use With | `docs/RUNBOOK.md`, `docs/PROD_BLOCKERS.md` |

---

## What This Covers

1. Where beta users send feedback
2. Who triages and how fast
3. How issues get classified (bug/blocker/backlog)
4. When and how the team reviews beta signals
5. What evidence closes BETA-BLK-010

---

## Feedback Channel

> **Decision needed from PO:** choose one option.

| Option | Pros | Cons |
|---|---|---|
| Dedicated email (e.g. beta@taxpilot.pl) | No external dependency, private, easy to start | Manual, no threading |
| GitHub Issues (private repo) | Threaded, labelled, integrates with backlog | Requires users to have GitHub account |
| Simple typeform/Google Form | Anonymous OK, low friction | Extra tool, aggregated not real-time |
| In-app feedback button | Zero friction, contextual | Requires dev work |

**Recommended for v1 beta:** dedicated email + in-app "Zgłoś problem" form (already exists as `ImportFormatReportController`). Route email to a monitored mailbox.

**Decision:** TBD

---

## Triage Owner and SLA

| Item | Value |
|---|---|
| Primary triage owner | TBD |
| Backup | TBD |
| Check frequency | TBD (recommended: daily during beta season Feb–Apr) |
| P0 response SLA | 15 min — bug causes incorrect tax calculation or data loss |
| P1 response SLA | 24h — feature broken for supported broker |
| P2/suggestion SLA | Next weekly review |

---

## Classification Rules

| Signal | Class | Action |
|---|---|---|
| Wrong tax amount or PIT-38 XML error | **P0** | Stop beta, hotfix, re-verify with golden dataset |
| Import fails for a supported broker (IBKR, Degiro, Revolut, Bossa, XTB) | **P0/P1** | Reproduce, hotfix, or scope-cut the broker |
| UX mylący — może wpłynąć na poprawność danych wejściowych lub złożenie błędnej deklaracji | **P0/P1** | Treat as calculation risk; fix or block flow before public prod |
| Kosmetyczny glitch UI — nie wpływa na flow, dane ani XML | **P2** | Log in BACKLOG.md, fix post-beta |
| Missing broker (unsupported format) | **P2** | Log in BACKLOG.md, communicate to user |
| Feature request / question | **P3** | Log in BACKLOG.md or reply via email |
| Legal / compliance question | **Escalate** | Forward to legal contact; do NOT answer directly |

---

## Weekly Beta Review Rhythm

Recommended cadence during closed beta:

1. **Daily:** Triage owner checks channel, classifies and logs
2. **Weekly (e.g. Monday):** Team reviews all P1+ signals:
   - What broke, what pattern
   - Fix decisions or scope cuts
   - Update `docs/PROD_BLOCKERS.md` if new blocker found
3. **End of beta:** Write beta retro report → `docs/sprints/beta-retro.md`

---

## Exit Criteria (BETA-BLK-010)

The blocker is CLOSED when:

- [ ] Feedback channel is confirmed and active (email or form)
- [ ] Triage owner is named and SLA confirmed
- [ ] At least one weekly review cycle has been completed
- [ ] Process is documented in this file (all TBDs filled in)

**Update status in `docs/PROD_BLOCKERS.md`** when all checkboxes are checked.

---

## Decision Record

| Date | Decision | Who |
|---|---|---|
| 2026-04-14 | DRAFT created | Tech Lead |
| YYYY-MM-DD | Channel: TBD, Owner: TBD, SLA: TBD | Product Owner |
