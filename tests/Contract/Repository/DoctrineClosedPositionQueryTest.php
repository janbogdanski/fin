<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Infrastructure\Doctrine\DoctrineClosedPositionQueryAdapter;
use App\Tests\Support\SeedsDatabaseUser;
use Doctrine\DBAL\Connection;

final class DoctrineClosedPositionQueryTest extends ClosedPositionQueryContractTestCase
{
    use SeedsDatabaseUser;

    protected function freshUserId(): UserId
    {
        $userId = UserId::generate();
        $this->seedUser($userId);

        return $userId;
    }

    protected function createQuery(): ClosedPositionQueryPort
    {
        return self::getContainer()->get(DoctrineClosedPositionQueryAdapter::class);
    }

    protected function seedPosition(UserId $userId, ClosedPosition $position, TaxCategory $category): void
    {
        /** @var Connection $conn */
        $conn = self::getContainer()->get(Connection::class);

        $conn->insert('closed_positions', [
            'user_id'                => $userId->toString(),
            'tax_category'           => $category->value,
            'buy_transaction_id'     => $position->buyTransactionId->toString(),
            'sell_transaction_id'    => $position->sellTransactionId->toString(),
            'isin'                   => $position->isin->toString(),
            'quantity'               => $position->quantity->__toString(),
            'cost_basis_pln'         => $position->costBasisPLN->__toString(),
            'proceeds_pln'           => $position->proceedsPLN->__toString(),
            'buy_commission_pln'     => $position->buyCommissionPLN->__toString(),
            'sell_commission_pln'    => $position->sellCommissionPLN->__toString(),
            'gain_loss_pln'          => $position->gainLossPLN->__toString(),
            'buy_date'               => $position->buyDate->format('Y-m-d H:i:s'),
            'sell_date'              => $position->sellDate->format('Y-m-d H:i:s'),
            'buy_nbp_rate_currency'  => $position->buyNBPRate->currency()->value,
            'buy_nbp_rate_value'     => $position->buyNBPRate->rate()->__toString(),
            'buy_nbp_rate_date'      => $position->buyNBPRate->effectiveDate()->format('Y-m-d'),
            'buy_nbp_rate_table'     => $position->buyNBPRate->tableNumber(),
            'sell_nbp_rate_currency' => $position->sellNBPRate->currency()->value,
            'sell_nbp_rate_value'    => $position->sellNBPRate->rate()->__toString(),
            'sell_nbp_rate_date'     => $position->sellNBPRate->effectiveDate()->format('Y-m-d'),
            'sell_nbp_rate_table'    => $position->sellNBPRate->tableNumber(),
            'buy_broker'             => $position->buyBroker->toString(),
            'sell_broker'            => $position->sellBroker->toString(),
        ]);
    }
}
