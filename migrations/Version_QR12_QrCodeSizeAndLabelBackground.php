<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_QR12_QrCodeSizeAndLabelBackground extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'QR-12: Add qr size and label background image fields to qr_codes table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE qr_codes ADD COLUMN qr_size SMALLINT NOT NULL DEFAULT 512');
        $this->addSql('ALTER TABLE qr_codes ADD COLUMN label_background_storage_path VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE qr_codes DROP COLUMN qr_size');
        $this->addSql('ALTER TABLE qr_codes DROP COLUMN label_background_storage_path');
    }
}
