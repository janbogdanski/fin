# ADR-007: Doctrine XML Mapping — separacja od Domain

## Status
ACCEPTED

## Data
2026-04-03

## Kontekst

Clean Architecture (ADR-003) wymaga: Domain layer nie zależy od Doctrine. Ale Doctrine ORM potrzebuje wiedzieć jak mapować domain entities na tabele.

### Opcje mapowania Doctrine:
1. **PHP Attributes na entity** — `#[ORM\Entity]`, `#[ORM\Column]` — proste, ale: Doctrine dependency w Domain
2. **XML mapping** — pliki `.orm.xml` w Infrastructure — verbose, ale: Domain czysta
3. **PHP mapping (fluent)** — PHP pliki w Infrastructure — mniej popularne, słabsze IDE support
4. **YAML mapping** — deprecated w Doctrine 3

## Decyzja

**XML mapping** — pliki w `src/{Module}/Infrastructure/Doctrine/mapping/`.

Domain entities NIE mają żadnych atrybutów Doctrine. Mapping jest w Infrastructure, zgodnie z Dependency Rule.

### Przykład

```php
// src/TaxCalc/Domain/Model/TaxPositionLedger.php
// ZERO importów Doctrine!
final class TaxPositionLedger
{
    private Uuid $id;
    private UserId $userId;
    private ISIN $isin;
    private TaxYear $taxYear;
    // ...pure domain logic...
}
```

```xml
<!-- src/TaxCalc/Infrastructure/Doctrine/mapping/TaxPositionLedger.orm.xml -->
<entity name="App\TaxCalc\Domain\Model\TaxPositionLedger"
        table="tax_position_ledger">
    <id name="id" type="uuid" column="id"/>
    <embedded name="userId" class="App\Shared\Domain\ValueObject\UserId"/>
    <embedded name="isin" class="App\Shared\Domain\ValueObject\ISIN"/>
    <!-- ... -->
</entity>
```

### Doctrine configuration

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        mappings:
            TaxCalc:
                type: xml
                dir: '%kernel.project_dir%/src/TaxCalc/Infrastructure/Doctrine/mapping'
                prefix: 'App\TaxCalc\Domain\Model'
                alias: TaxCalc
            # repeat per module...
```

## Konsekwencje

### Pozytywne
- Domain entities = pure PHP classes, zero framework dependency
- Domain testowalne z `new Entity(...)` — bez Doctrine kernel boot
- Mapping jest infrastrukturalny detal — widoczny gdzie powinien być
- Deptrac potwierdza: Domain nie importuje Doctrine

### Negatywne
- XML jest verbose i less discoverable niż attributes
- IDE autocompletion dla XML mapping jest słabe (mitigacja: Symfony plugin dla PhpStorm)
- Dwa miejsca do aktualizacji przy zmianie entity (entity + mapping)
- Doctrine migrations nie widzą zmian w entity automatycznie — trzeba `make migrate-diff`

### Alternatywy do rozważenia w przyszłości
- Doctrine 4 może wspierać separate mapping classes (PHP fluent) lepiej — rozważ migrację
- Jeśli XML okaże się zbyt uciążliwy: PHP mapping files jako kompromis (wciąż w Infrastructure)

## Uczestnicy decyzji

| Osoba | Stanowisko |
|---|---|
| Mariusz Gil | "Jeśli widzę `#[ORM\Entity]` na domain entity — code review odrzucony. XML mapping. Bez dyskusji." |
| Marek [senior-dev] | "Verbose, ale poprawne. Domain musi być czysta." |
