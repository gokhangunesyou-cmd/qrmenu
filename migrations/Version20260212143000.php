<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add popup button label and button URL fields for restaurant popups.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant_popups ADD button_label VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant_popups ADD button_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant_popups DROP button_label');
        $this->addSql('ALTER TABLE restaurant_popups DROP button_url');
    }
}
