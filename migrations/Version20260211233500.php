<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211233500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add menu_template column to restaurants for public menu theme selection.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE restaurants ADD menu_template VARCHAR(30) DEFAULT 'showcase' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurants DROP menu_template');
    }
}

