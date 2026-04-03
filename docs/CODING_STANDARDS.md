# TaxPilot — Coding Standards

Established by Developer Guild audit (2026-04-03). These rules are **enforceable** and must be checked in every code review.

## 1. Port interfaces use Value Objects and enums, never primitives for domain concepts

```php
// BAD
public function save(UserId $userId, int $lossYear, string $taxCategory, string $amount): void;

// GOOD
public function save(UserId $userId, TaxYear $lossYear, TaxCategory $taxCategory, BigDecimal $amount): void;
```

**Enforcement:** Code review. PHPStan custom rule planned.

## 2. Application/Domain methods return DTOs, never associative arrays

```php
// BAD — in Application layer
public function resolveUserProfile(UserId $userId): array

// GOOD
public function resolveUserProfile(UserId $userId): UserProfile
```

**Enforcement:** Grep `@return array{` in `src/*/Application/` and `src/*/Domain/` = P1 finding. Infrastructure exempt (DBAL results).

## 3. DTOs use typed Value Objects, not string representations

```php
// BAD
final readonly class PriorYearLossRow {
    public string $taxCategory;
    public string $originalAmount;
}

// GOOD
final readonly class PriorYearLossRow {
    public TaxCategory $taxCategory;
    public BigDecimal $originalAmount;
}
```

## 4. Money/amounts = BigDecimal or Money VO, never string or float

Properties named `*amount*`, `*price*`, `*cost*`, `*proceeds*` must be `BigDecimal` or `Money` typed.

## 5. > 3 parameters on port methods = use Command/DTO object

```php
// BAD
public function save(UserId $userId, int $lossYear, string $taxCategory, string $amount): void;

// GOOD
public function __invoke(SavePriorYearLoss $command): void;
```

## 6. All classes `final readonly` by default

Justified exceptions: Doctrine entities with lifecycle (User, Payment, TaxPositionLedger), Controllers (AbstractController), Doctrine Types, Event Subscribers.

## 7. Domain layer imports nothing from Infrastructure or Application

**Enforcement:** Deptrac (currently 0 violations). CI gate.

## 8. `$row['key']` array access confined to Infrastructure adapters only

Never in Domain or Application layers.

## 9. Enums for all finite domain concepts

`TaxCategory`, `CurrencyCode`, `TransactionType` etc. Any new finite concept = enum, not string.

## 10. PHPStan level max, zero errors

Each `phpstan.neon` ignore pattern must have a comment explaining why. Audit quarterly.

## 11. No `mixed` in Application/Domain type declarations

Acceptable only in Infrastructure (DBAL, HTTP request parsing).

## 12. Bounded Context crossings via Application DTOs only

Deptrac enforces this. Customer-Supplier pattern between BCs.

## 13. Controllers pass typed objects to Twig, not scalar bags

Use ViewModel/DTO for > 3 template variables.

## 14. Port return types use `list<DTO>`, never `list<array{...}>`

Port interfaces = Application's public API. Arrays = leaking infrastructure.

## 15. Controllers use Single Action Controller pattern (`__invoke()`)

Each route = separate controller class with `__invoke()`. No fat controllers with multiple actions.

```php
// BAD
final class LossesController extends AbstractController {
    public function index(): Response { ... }
    public function store(Request $request): Response { ... }
    public function delete(Request $request, string $id): Response { ... }
}

// GOOD
final class ListLossesController extends AbstractController {
    public function __invoke(): Response { ... }
}
final class StoreLossController extends AbstractController {
    public function __invoke(Request $request): Response { ... }
}
final class DeleteLossController extends AbstractController {
    public function __invoke(Request $request, string $id): Response { ... }
}
```

**Rationale:** SRP at controller level. Each class has one reason to change. Constructor injection is cleaner (only inject what that route needs).

## 16. Test ratio minimum 1:1 for Domain/Application code

No merge if Domain/Application coverage drops below 90%.
