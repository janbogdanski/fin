<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create payments table for Stripe billing.
 */
final class Version20260402180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create payments table for Stripe billing integration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE payments (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                stripe_session_id VARCHAR(255) NOT NULL,
                stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
                product_code VARCHAR(20) NOT NULL,
                amount_cents INT NOT NULL,
                currency VARCHAR(3) NOT NULL,
                status VARCHAR(20) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_payments_stripe_session_id ON payments (stripe_session_id)');
        $this->addSql('CREATE INDEX idx_payments_user_id_status ON payments (user_id, status)');
        $this->addSql('CREATE INDEX idx_payments_user_id_product_code_status ON payments (user_id, product_code, status)');

        $this->addSql('COMMENT ON COLUMN payments.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN payments.user_id IS \'(DC2Type:user_id)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE payments');
    }
}
