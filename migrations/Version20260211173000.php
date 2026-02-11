<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update subscription plans for free/starter/branch10/premium structure';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            INSERT INTO plans (code, name, description, max_restaurants, max_users, yearly_price, is_active, created_at, updated_at)
            VALUES
                ('free', 'Free Plan', '3 aylik ucretsiz abonelik, 1 restoran, 1 kullanici.', 1, 1, 0.00, true, NOW(), NOW()),
                ('starter', 'Baslangic Plan', '599 TL yerine indirimli 0 TL, 1 yillik abonelik, 1 restoran, 1 kullanici.', 1, 1, 0.00, true, NOW(), NOW()),
                ('branch10', '10 Subeli 10 Kullanicili Plan', '2999 TL yerine indirimli 0 TL, 1 yillik abonelik, 10 sube, 10 kullanici.', 10, 10, 0.00, true, NOW(), NOW()),
                ('premium', 'Premium Plan', '5000 TL indirimli 1 yillik abonelik, 10 sube, 10 kullanici, data yukleme destegi, ozel QR tasarim destegi, ozel menu tasarimi, 7/24 iletisim.', 10, 10, 5000.00, true, NOW(), NOW())
            ON CONFLICT (code) DO UPDATE SET
                name = EXCLUDED.name,
                description = EXCLUDED.description,
                max_restaurants = EXCLUDED.max_restaurants,
                max_users = EXCLUDED.max_users,
                yearly_price = EXCLUDED.yearly_price,
                is_active = EXCLUDED.is_active,
                updated_at = NOW()
        ");
        $this->addSql("UPDATE plans SET is_active = false, updated_at = NOW() WHERE code NOT IN ('free', 'starter', 'branch10', 'premium')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM plans WHERE code IN ('starter', 'branch10')");
        $this->addSql("
            INSERT INTO plans (code, name, description, max_restaurants, max_users, yearly_price, is_active, created_at, updated_at)
            VALUES
                ('free', 'Free', '1 restoran 1 kullanici yillik plan', 1, 1, 0.00, true, NOW(), NOW()),
                ('premium', 'Premium', '20 restoran 25 kullanici yillik plan', 20, 25, 0.00, true, NOW(), NOW())
            ON CONFLICT (code) DO UPDATE SET
                name = EXCLUDED.name,
                description = EXCLUDED.description,
                max_restaurants = EXCLUDED.max_restaurants,
                max_users = EXCLUDED.max_users,
                yearly_price = EXCLUDED.yearly_price,
                is_active = EXCLUDED.is_active,
                updated_at = NOW()
        ");
    }
}
