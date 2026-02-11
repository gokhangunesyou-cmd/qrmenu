<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add enabled_locales to restaurants for restaurant-based language filtering.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurants ADD enabled_locales JSON NOT NULL DEFAULT \'["tr"]\'');
        $this->addSql('UPDATE restaurants SET enabled_locales = json_build_array(default_locale)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurants DROP enabled_locales');
    }
}
