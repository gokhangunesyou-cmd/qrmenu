<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_QR9_AddCalories extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add calories column to products table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD calories SMALLINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP calories');
    }
}
