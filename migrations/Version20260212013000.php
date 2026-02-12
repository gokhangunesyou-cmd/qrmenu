<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212013000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product view counters and restaurant-level menu analytics/whatsapp settings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurants ADD count_product_views BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE restaurants ADD count_product_detail_views BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE restaurants ADD whatsapp_order_enabled BOOLEAN DEFAULT false NOT NULL');

        $this->addSql('ALTER TABLE products ADD menu_view_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE products ADD detail_view_count INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX idx_products_restaurant_views ON products (restaurant_id, menu_view_count)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_products_restaurant_views');
        $this->addSql('ALTER TABLE products DROP menu_view_count');
        $this->addSql('ALTER TABLE products DROP detail_view_count');

        $this->addSql('ALTER TABLE restaurants DROP count_product_views');
        $this->addSql('ALTER TABLE restaurants DROP count_product_detail_views');
        $this->addSql('ALTER TABLE restaurants DROP whatsapp_order_enabled');
    }
}

