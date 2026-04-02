# Guardrails for LLM-Assisted Development

## Problem

LLM agents (Claude, GPT, Copilot) writing code have a fundamental flaw: **they assume instead of verifying.** An agent "thinks" a method returns a string, writes a caller expecting a string, but the actual method returns an int. The code compiles (in dynamic languages) or even passes superficial tests — but fails in production.

This is not occasional. This is the **#1 source of bugs in LLM-generated code.**

Root causes:
- Agent has stale or incomplete context about the codebase
- Agent confuses similar-but-different APIs (e.g., `DateTime` vs `DateTimeImmutable`)
- Agent hallucinates method signatures, class names, or config values
- Agent writes code that satisfies its internal model of the system, not the actual system
- Multiple agents working in parallel develop incompatible assumptions

---

## Principle: VERIFY, THEN CODE

**Never trust an LLM's "memory" of your codebase. Make it read the actual file before every decision.**

This applies to:
- Method signatures — READ the file
- Constructor parameters — READ the file
- Return types — READ the file
- Class/interface names — GLOB or GREP
- File existence — check filesystem
- Config values — READ the config
- Database schema — READ the migration

"I think it's X" is a bug waiting to happen. `Read(file_path)` is verification.

---

*Reviewed by: Senior Developer (production LLM experience) + QA Lead (testing LLM-generated code). Feedback incorporated.*

---

## Guardrails — Ranked by Effectiveness

### Tier 0: Git Hooks (Shortest Feedback Loop)

#### 0. Pre-Commit Hooks

**What:** Run static analysis + linting BEFORE code enters the repository.

**Why:** Catching errors at commit time is cheaper than catching them in CI (seconds vs minutes). Agent produces code → developer commits → hook rejects immediately if types are wrong.

**How:**
- `pre-commit` with: lint (ECS/Prettier), static analysis (PHPStan/mypy), basic tests (unit only)
- Runs in <30 seconds (fast subset, not full suite)
- Full CI runs on push/PR (not blocked by slow tests)

**Real bug example:** Agent writes `$user->getId()` but method is `$user->id()`. Pre-commit PHPStan catches in 3 seconds, not 5 minutes in CI.

### Tier 1: Automated Static Checks (Zero Ongoing Cost)

These run in CI and catch bugs without human intervention.

#### 1. Static Analysis at Maximum Level

**What:** PHPStan max / mypy strict / TypeScript strict / eslint with type-checking rules.

**Why:** Catches 60%+ of "I assumed this returns X" bugs at compile time. Type mismatches between what agent wrote and what actually exists.

**How:**
- Set strictness to maximum from day 1 (not "we'll increase later")
- Zero tolerance: 0 errors in CI, no baseline exceptions for new code
- Treat warnings as errors

**What it catches:** Wrong return types, wrong argument types, accessing properties that don't exist, calling methods that don't exist, null safety violations.

#### 2. Dependency / Architecture Analysis

**What:** Deptrac (PHP) / ArchUnit (Java) / dependency-cruiser (JS) / similar.

**Why:** Catches "I assumed module A can import from module B" — which is the #1 cross-agent consistency bug.

**How:**
- Define allowed dependency graph in config
- Run in CI — violation = build failed
- When agent creates a file in wrong module → caught immediately

**What it catches:** Wrong imports, circular dependencies, layer violations (domain importing infrastructure).

#### 3. Coding Standard Enforcement

**What:** ECS / php-cs-fixer / Prettier / Black / rustfmt.

**Why:** Eliminates an entire category of "is it tabs or spaces, camelCase or snake_case" disagreements between agents. One canonical style.

**How:**
- Auto-fix on commit (or in CI)
- Zero warnings policy

**What it catches:** Inconsistent naming, formatting, import ordering between agents.

---

### Tier 2: Test-Based (Moderate Cost, High Value)

#### 4. TDD — Test Before Implementation

**What:** Write the test FIRST, then the implementation.

**Why:** The test is the agent's statement of assumptions. If the assumption is wrong, the test fails for the wrong reason (RED phase produces unexpected error, not expected failure). This surfaces wrong assumptions BEFORE implementation.

**How:**
- Agent writes test → runs it → verifies it fails for the RIGHT reason → implements → runs again → GREEN
- If RED phase error is "class not found" or "method not found" instead of expected assertion failure → agent's assumption was wrong → stop and investigate

**What it catches:** Wrong assumptions about behavior, interfaces, and contracts.

#### 5. Golden Dataset / Hand-Calculated Expected Values

**What:** A set of inputs with manually calculated expected outputs, verified by a domain expert (not by an LLM).

**Why:** The LLM cannot game this. If the LLM's calculation gives a different result than the human's calculation, the LLM is wrong. This is the ultimate "source of truth" for business logic.

**How:**
- Domain expert (accountant, tax advisor, data scientist) prepares inputs + expected outputs
- Tests compare system output against expert's output
- Zero tolerance for differences (not "close enough" — exact match)

**What it catches:** Math errors, rounding bugs, business rule misunderstandings, formula errors.

#### 6. Mutation Testing

**What:** Infection (PHP) / Stryker (JS) / mutmut (Python) / cargo-mutants (Rust).

**Why:** Catches "fake tests" — tests that ALWAYS pass regardless of code correctness. Agent writes a test, implementation is wrong, but test passes anyway because the test doesn't actually verify the right thing.

**How:**
- Mutation testing tool changes code (e.g., `>` to `>=`, `+` to `-`)
- Runs tests against mutated code
- If tests still pass → test is useless ("mutant survived")
- MSI (Mutation Score Indicator) > 80% for critical code

**What it catches:** Tests that don't test, dead code, untested branches.

#### 7. Snapshot / Approval Tests

**What:** Freeze the output of a function. If output changes, test fails. Developer must explicitly approve the change.

**Why:** Prevents "silent drift" — agent refactors code, output subtly changes (e.g., rounding difference), no test catches it because tests check structure not values.

**How:**
- `assertMatchesSnapshot($output)` — stores expected output on first run
- Any change to output → test fails → diff shown → developer approves or rejects
- Critical for: serialization formats, API responses, generated documents (XML, PDF)

**What it catches:** Silent output changes, rounding drift, format changes.

#### 8. Property-Based Tests

**What:** Generate random inputs, verify invariants hold.

**Why:** Agent tests happy path (buy 100 shares, sell 100 shares). Property test generates: buy 0.001 shares, buy 999999 shares, buy then sell then buy then sell. Finds edge cases no agent imagined.

**How:**
- Define invariants ("sum of sold quantity ≤ sum of bought quantity", "output is always sorted", "serialize then deserialize = identity")
- Generator produces thousands of random inputs
- Framework checks invariants hold for all inputs

**What it catches:** Edge cases, overflow, precision loss, ordering bugs.

---

### Tier 3: Process-Based (Human + LLM Collaboration)

#### 9. "Read Before Write" Prompt Discipline

**What:** Every agent prompt MUST list specific files to read before writing.

**Why:** If you don't tell the agent to read the file, it will use its "memory" (which may be stale or wrong). Explicit read instructions force verification.

**How:**
```
# BAD prompt:
"Create a UserRepository that implements the interface"

# GOOD prompt:
"Read src/Identity/Domain/Repository/UserRepositoryInterface.php
 Read src/Shared/Domain/ValueObject/UserId.php
 Then create a Doctrine implementation in src/Identity/Infrastructure/"
```

**What it catches:** Wrong method signatures, wrong imports, wrong constructor args.

#### 10. Multi-Agent Review Pipeline

**What:** After implementation agent finishes, run 3-4 review agents in parallel: code reviewer, security auditor, QA lead, domain expert.

**Why:** Implementation agent has blind spots (confirmation bias). Review agents start fresh, find issues implementation agent missed. Different agents have different "hallucinations" — cross-checking finds contradictions.

**How:**
- After each sprint / feature: launch review agents
- Each reviews ALL code, not just their specialty
- Collect findings → fix → re-review if needed

**What it catches:** Logic errors, security holes, missing edge cases, architecture violations, domain rule misunderstandings.

#### 11. Pipeline After EVERY Agent (Not At The End)

**What:** Run full CI pipeline (tests + static analysis + dependency check) immediately after each agent finishes, BEFORE starting next agent.

**Why:** If Agent A introduces a type error and Agent B builds on Agent A's work, you get cascading wrong assumptions. Catching Agent A's error immediately prevents waste.

**How:**
- Agent finishes → run tests + PHPStan + Deptrac → fix issues → THEN start next agent
- Never batch: "we'll run tests at the end"

**What it catches:** Inter-agent assumption mismatches, cascading errors.

#### 12. Integration Tests Between Agent Outputs

**What:** After multiple agents deliver code, write tests that exercise the FULL FLOW across all agents' code.

**Why:** Each agent's code passes unit tests in isolation. But Agent A's output format may not match Agent B's expected input format. Only integration tests catch this.

**How:**
- End-to-end test: input → Agent A's code → Agent B's code → Agent C's code → verify output
- Use real (or realistic) data, not mocks
- Run with real database (testcontainers)

**What it catches:** Interface mismatches between agents, wrong assumptions about data formats, missing wiring.

---

### Tier 4: Domain-Specific Custom Rules

#### 13. Custom Static Analysis Rules

**What:** Project-specific rules in PHPStan / ESLint / custom linters.

Examples:
- "No `float` in financial calculations namespace" — catches precision bugs
- "No `new DateTime()` (use `DateTimeImmutable`)" — catches mutability bugs
- "No `echo` or `print_r` in src/" — catches debug leftovers
- "All classes in Domain/ must be `final readonly`" — catches architecture violations

**How:** Write custom PHPStan rules / ESLint plugins. Run in CI.

#### 14. Mutation Score Gates (replaces coverage gates)

**What:** Enforce minimum MSI (Mutation Score Indicator) instead of line coverage.

**Why:** Line coverage is easily gamed — agent generates empty tests that "cover" code but verify nothing. MSI measures whether tests actually detect changes in code. An agent that writes `assertTrue(true)` gets 100% coverage but 0% MSI.

**How:**
- Domain logic: MSI > 80%
- Run as nightly job (mutation testing is slow: minutes to hours)
- NOT in per-agent feedback loop — too slow
- CI fails if MSI drops below threshold on PR diff

**Note:** Coverage gates (line coverage > X%) have low ROI with LLM agents. Agents are excellent at generating tests that hit every line but verify nothing. Prefer MSI.

---

### Tier 5: Resilience & Regression (Post-Review additions)

#### 15. Regression Gate on Golden Dataset

**What:** On every PR, automatically compare golden dataset results before/after. If ANY result changes, PR requires manual approval.

**Why:** Agent "fixes" one bug, silently changes output of 3 other calculations. Without regression gate, this goes unnoticed until a user files a tax return with wrong numbers.

**How:**
- Golden dataset tests produce deterministic output files
- CI compares output against committed snapshots
- ANY diff → PR blocked → developer reviews diff → approves or rejects
- This is a specialized form of snapshot testing applied to business-critical flows

**Real bug example:** Agent refactored rounding logic, changed `HALF_UP` to `HALF_EVEN`. All tests passed (assertions checked "approximately equal"). Golden dataset regression gate caught: tax amount changed from 1927 PLN to 1926 PLN.

#### 16. Contract Testing Between Modules

**What:** Pact-style consumer-driven contracts between modules/services, or schema validation for external APIs.

**Why:** When Agent A writes a producer and Agent B writes a consumer, they may develop incompatible assumptions about the data format. Unit tests pass for both (mocked interfaces), but integration fails.

**How:**
- For external APIs (NBP, broker APIs): record response schema, validate on every CI run
- For inter-module communication: define explicit DTOs at module boundaries, validate with PHPStan
- For multi-service architectures: Pact or similar contract testing framework

#### 17. Chaos / Fault Injection Testing

**What:** Deliberately inject failures (API timeouts, DB errors, malformed data) and verify graceful degradation.

**Why:** LLM agents NEVER write tests for failure modes unprompted. They test the happy path. What happens when NBP API returns 500? When database connection drops mid-transaction? When CSV contains binary garbage?

**How:**
- Wrapper that randomly injects: timeouts, connection errors, malformed responses
- Run as part of integration test suite
- Verify: no data corruption, meaningful error messages, recovery without restart

#### 18. Flaky Test Quarantine

**What:** Automatically detect and isolate tests that pass/fail non-deterministically.

**Why:** LLM agents generate tests with hidden dependencies on: system time, execution order, global state, random data. These tests pass 95% of the time, masking real bugs.

**How:**
- Run test suite 3x in CI on suspected flaky failures
- If test fails 1/3 or 2/3 runs → quarantine (move to separate suite)
- Alert + investigate quarantined tests
- Never ignore a flaky test — it's a symptom of a real problem

---

### Tier 6: Process & Governance

#### 19. Prompt Versioning

**What:** Treat prompts as code — version control, review, regression test.

**Why:** The prompt IS the specification. If the prompt changes ("add error handling" → "add comprehensive error handling"), agent behavior changes unpredictably. Without version control, you can't trace why agent output changed.

**How:**
- Store system prompts / agent briefs in repository (e.g., `prompts/` directory)
- Review prompt changes in PR (just like code changes)
- Tag prompt versions with sprint/release
- When agent output regresses, diff the prompts first

#### 20. Context Management Strategy

**What:** Explicit rules for how much context to give agents.

**Why:** Too little context → agent hallucinates. Too much context → agent gets confused, ignores important parts. There is a sweet spot.

**How:**
- File > 500 lines → give agent specific line range, not whole file
- Always list specific files to read (never "read the project")
- For multi-file changes: list interfaces first, implementations second
- Refresh context every conversation (never assume agent "remembers" from before)
- Rule of thumb: 5-10 specific files per agent task, not 50

#### 21. Rollback Strategy

**What:** Defined procedure for reverting agent-generated changes that passed CI but caused problems.

**Why:** LLM changes can be large (20+ files in one agent run). If something goes wrong, you need to revert surgically.

**How:**
- Frequent small commits (per agent, not per sprint)
- Each agent's work on a separate branch or worktree
- `git diff` review before merge (not after)
- If in doubt: squash merge to main → easy single-commit revert

---

## Anti-Patterns — What Doesn't Work

| Anti-Pattern | Why It Fails |
|---|---|
| "Trust the agent, it's smart" | The agent is confident AND wrong. Confidence ≠ correctness. |
| "We'll add tests later" | Later never comes. And the agent already built on wrong assumptions. |
| "Run tests only at the end" | Cascading errors. Agent B builds on Agent A's bug. |
| "PHPStan level 5 is enough" | Level 5 misses half the type errors. Max or nothing. |
| "The agent read the file last conversation" | Context is stale. Files changed. READ AGAIN. |
| "One big agent does everything" | No cross-checking. One agent's hallucination goes uncaught. |
| "Mock everything in tests" | Mocks encode assumptions. If assumption is wrong, mock is wrong, test passes, bug ships. |
| "Agent-written tests verify agent-written code" | Confirmation bias. Both have the same wrong assumption. Need external truth (golden dataset). |

---

## Real Bug Examples — What Each Guardrail Catches

| Bug | How It Happened | Which Guardrail Catches It |
|---|---|---|
| Agent wrote `$user->getId()` but method is `$user->id()` | Agent assumed Laravel convention in Symfony project | **PHPStan** (Tier 1) |
| Agent used `bcmath` in one file, `brick/math` everywhere else | Agent #3 didn't know about Agent #1's convention | **Code review** (Tier 3) + coding standards doc in prompt |
| Tax rounding was "always down" but law says "mathematical" (>=50gr up) | Agent assumed common misconception, didn't read the statute | **Golden dataset** (Tier 2) — hand-calculated values differ |
| `sanitize()` method copy-pasted 5 times across 5 adapters | 5 agents wrote 5 adapters independently | **Code review** (Tier 3) → refactor to trait |
| FIFO aggregate scoped per TaxYear but FIFO is cross-year | Agent assumed year boundary = FIFO boundary | **Domain expert review** (Tier 3) — tax advisor caught it |
| Commission allocation divided by `remainingQuantity` not `originalQuantity` | Agent assumed current state = initial state | **Property-based test** (Tier 2) — invariant `sum(allocated) == total` fails |
| `Money::of()` rounded to 2 decimal places immediately | Agent applied "clean output" assumption to intermediate calculations | **Golden dataset** (Tier 2) — cumulative rounding error across 5000 transactions |
| DOMDocument created without XXE protection | Agent focused on generation, forgot about XML parsing attacks | **Security review agent** (Tier 3) |
| Test always passes because assertion checks `assertTrue(count > 0)` on always-non-empty mock | Agent wrote test that confirms its own assumption | **Mutation testing** (Tier 2) — mutant survives |
| Agent "delegated" WHT cap validation "to layer above" which doesn't exist | Cross-agent TODO lost in code comment | **Integration test** (Tier 3) — full flow test exposes missing validation |

---

## Checklist — Minimum Viable Guardrails

For any LLM-assisted development project, implement AT MINIMUM:

- [ ] Pre-commit hooks: lint + static analysis (Tier 0)
- [ ] Static analysis at maximum strictness — zero errors on new code (Tier 1)
- [ ] TDD — test before implementation (Tier 2)
- [ ] "Read before write" in every agent prompt (Tier 3)
- [ ] Fast pipeline (types + lint) after every agent, full pipeline after every feature (Tier 3)
- [ ] At least one golden dataset test with hand-calculated values (Tier 2)
- [ ] Multi-agent review after each sprint (Tier 3)
- [ ] Frequent commits — one per agent task, easy revert (Tier 6)

For financial / medical / legal domains, ADD:

- [ ] Mutation testing MSI > 80% on critical code — nightly job (Tier 2)
- [ ] Snapshot / approval tests on all generated documents (Tier 2)
- [ ] Golden dataset regression gate on every PR (Tier 5)
- [ ] Custom static analysis rules for domain invariants (Tier 4)
- [ ] Integration tests across agent boundaries (Tier 3)
- [ ] Property-based tests for business logic invariants (Tier 2)
- [ ] Contract tests for external APIs (Tier 5)
- [ ] Chaos / fault injection in integration tests (Tier 5)
- [ ] Prompt versioning in repository (Tier 6)
- [ ] Context management rules in agent briefs (Tier 6)
