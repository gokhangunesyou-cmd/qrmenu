<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_QR11_QrCodeCustomization extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'QR-11: Add customization fields to qr_codes table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE qr_codes ADD COLUMN foreground_color VARCHAR(7) DEFAULT NULL');
        $this->addSql('ALTER TABLE qr_codes ADD COLUMN background_color VARCHAR(7) DEFAULT NULL');
        $this->addSql('ALTER TABLE qr_codes ADD COLUMN border_radius SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE qr_codes ADD COLUMN logo_storage_path VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE qr_codes DROP COLUMN foreground_color');
        $this->addSql('ALTER TABLE qr_codes DROP COLUMN background_color');
        $this->addSql('ALTER TABLE qr_codes DROP COLUMN border_radius');
        $this->addSql('ALTER TABLE qr_codes DROP COLUMN logo_storage_path');
    }
}
