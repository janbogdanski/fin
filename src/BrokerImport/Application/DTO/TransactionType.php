<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\DTO;

enum TransactionType: string
{
    case BUY = 'BUY';
    case SELL = 'SELL';
    case DIVIDEND = 'DIVIDEND';
    case WITHHOLDING_TAX = 'WITHHOLDING_TAX';
    case FEE = 'FEE';
    case TRANSFER_IN = 'TRANSFER_IN';
    case TRANSFER_OUT = 'TRANSFER_OUT';
    case CORPORATE_ACTION = 'CORPORATE_ACTION';
}
