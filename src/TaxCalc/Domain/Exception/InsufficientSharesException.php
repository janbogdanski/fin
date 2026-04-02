<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Exception;

use App\Shared\Domain\ValueObject\ISIN;
use Brick\Math\BigDecimal;

final class InsufficientSharesException extends \DomainException
{
    public function __construct(ISIN $isin, BigDecimal $remainingToSell)
    {
        parent::__construct(
            "Insufficient shares for {$isin->toString()}: trying to sell {$remainingToSell} but no open positions available. Check FIFO queue or import history.",
        );
    }
}
