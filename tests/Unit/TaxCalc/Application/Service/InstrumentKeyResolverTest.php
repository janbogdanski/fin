<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Application\Service;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\TransactionType;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TransactionId;
use App\TaxCalc\Application\Service\IsinInstrumentKeyResolver;
use App\TaxCalc\Application\Service\IsinWithSymbolFallbackKeyResolver;
use App\TaxCalc\Application\Service\SymbolInstrumentKeyResolver;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class InstrumentKeyResolverTest extends TestCase
{
    // --- IsinInstrumentKeyResolver ---

    public function testIsinResolverReturnsIsinString(): void
    {
        $resolver = new IsinInstrumentKeyResolver();
        $tx = $this->makeTx(ISIN::fromString('US0378331005'), 'AAPL');

        self::assertSame('US0378331005', $resolver->resolveKey($tx));
    }

    public function testIsinResolverReturnsNullWhenNoIsin(): void
    {
        $resolver = new IsinInstrumentKeyResolver();
        $tx = $this->makeTx(null, 'AAPL.US');

        self::assertNull($resolver->resolveKey($tx));
    }

    public function testIsinResolverReturnsNullWhenNoIsinAndNoSymbol(): void
    {
        $resolver = new IsinInstrumentKeyResolver();
        $tx = $this->makeTx(null, '');

        self::assertNull($resolver->resolveKey($tx));
    }

    // --- SymbolInstrumentKeyResolver ---

    public function testSymbolResolverReturnsSymbol(): void
    {
        $resolver = new SymbolInstrumentKeyResolver();
        $tx = $this->makeTx(null, 'AAPL.US');

        self::assertSame('AAPL.US', $resolver->resolveKey($tx));
    }

    public function testSymbolResolverIgnoresIsin(): void
    {
        // Symbol resolver must return the symbol even when an ISIN is present.
        $resolver = new SymbolInstrumentKeyResolver();
        $tx = $this->makeTx(ISIN::fromString('US0378331005'), 'AAPL');

        self::assertSame('AAPL', $resolver->resolveKey($tx));
    }

    public function testSymbolResolverReturnsNullWhenEmptySymbol(): void
    {
        $resolver = new SymbolInstrumentKeyResolver();
        $tx = $this->makeTx(ISIN::fromString('US0378331005'), '');

        self::assertNull($resolver->resolveKey($tx));
    }

    public function testSymbolResolverReturnsNullWhenNoIsinAndNoSymbol(): void
    {
        $resolver = new SymbolInstrumentKeyResolver();
        $tx = $this->makeTx(null, '');

        self::assertNull($resolver->resolveKey($tx));
    }

    // --- IsinWithSymbolFallbackKeyResolver ---

    public function testFallbackResolverPrefersIsin(): void
    {
        $resolver = new IsinWithSymbolFallbackKeyResolver();
        $tx = $this->makeTx(ISIN::fromString('US0378331005'), 'AAPL');

        self::assertSame('US0378331005', $resolver->resolveKey($tx));
    }

    public function testFallbackResolverUsesSymbolWhenNoIsin(): void
    {
        $resolver = new IsinWithSymbolFallbackKeyResolver();
        $tx = $this->makeTx(null, 'AAPL.US');

        self::assertSame('AAPL.US', $resolver->resolveKey($tx));
    }

    public function testFallbackResolverReturnsNullWhenBothEmpty(): void
    {
        $resolver = new IsinWithSymbolFallbackKeyResolver();
        $tx = $this->makeTx(null, '');

        self::assertNull($resolver->resolveKey($tx));
    }

    private function makeTx(?ISIN $isin, string $symbol): NormalizedTransaction
    {
        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: $isin,
            symbol: $symbol,
            type: TransactionType::BUY,
            date: new \DateTimeImmutable('2025-01-01'),
            quantity: BigDecimal::of('1'),
            pricePerUnit: Money::of('100.00', CurrencyCode::USD),
            commission: Money::of('0.00', CurrencyCode::USD),
            broker: BrokerId::of('test'),
            description: '',
            rawData: [],
        );
    }
}
