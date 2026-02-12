<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212024500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove restaurant count_product_views setting; keep only detail view analytics toggle.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurants DROP count_product_views');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurants ADD count_product_views BOOLEAN DEFAULT false NOT NULL');
    }
}

