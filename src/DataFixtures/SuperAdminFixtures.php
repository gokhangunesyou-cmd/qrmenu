<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Restaurant;
use App\Entity\Role;
use App\Entity\SiteSetting;
use App\Entity\SiteWidget;
use App\Entity\Theme;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SuperAdminFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Ensure roles exist
        $roleOwner = $manager->getRepository(Role::class)->findOneBy(['name' => 'ROLE_RESTAURANT_OWNER']);
        if ($roleOwner === null) {
            $roleOwner = new Role('ROLE_RESTAURANT_OWNER', 'Restaurant owner');
            $manager->persist($roleOwner);
        }

        $roleSuperAdmin = $manager->getRepository(Role::class)->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
        if ($roleSuperAdmin === null) {
            $roleSuperAdmin = new Role('ROLE_SUPER_ADMIN', 'Super administrator');
            $manager->persist($roleSuperAdmin);
        }

        // Ensure a default theme exists
        $theme = $manager->getRepository(Theme::class)->findOneBy(['slug' => 'default']);
        if ($theme === null) {
            $theme = new Theme('default', 'Varsayılan Tema', []);
            $manager->persist($theme);
        }

        // Ensure default site theme settings exist
        $activeTheme = $manager->getRepository(SiteSetting::class)->findOneBy(['keyName' => 'site_theme_active']);
        if ($activeTheme === null) {
            $activeTheme = new SiteSetting('site_theme_active', 'theme_1');
            $manager->persist($activeTheme);
        }

        $paletteSetting = $manager->getRepository(SiteSetting::class)->findOneBy(['keyName' => 'site_theme_palette']);
        if ($paletteSetting === null) {
            $paletteSetting = new SiteSetting('site_theme_palette', json_encode([
                'primary' => '#0f766e',
                'primaryDark' => '#134e4a',
                'primaryLight' => '#ccfbf1',
                'accent' => '#f59e0b',
                'background' => '#f0fdfa',
                'surface' => '#ffffff',
                'text' => '#0f172a',
                'muted' => '#64748b',
                'border' => 'rgba(15, 118, 110, 0.18)',
                'highlight' => '#ecfeff',
                'heroGlow' => 'rgba(15, 118, 110, 0.22)',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            $manager->persist($paletteSetting);
        }

        // Seed default widgets if empty
        $widgetRepo = $manager->getRepository(SiteWidget::class);
        if (count($widgetRepo->findAll()) === 0) {
            $widgets = [
                new SiteWidget('home_hero', 'hero', [
                    'eyebrow' => 'Kurumsal QR Menu Altyapisi',
                    'title' => 'Tek panelden menuyu yonetin, masaya ozel QR deneyimiyle servisi hizlandirin.',
                    'description' => 'Altay QR Menu; restoranlar, zincir markalar ve franchise operasyonlari icin tasarlandi. Urun, kategori, masa, personel ve PDF cikti sureclerini tek akista yonetir.',
                    'primary_text' => 'Planlari Incele',
                    'primary_url' => '#plans',
                    'secondary_text' => 'Tum Ozellikleri Gor',
                    'secondary_url' => '#features',
                    'badge' => 'Yonetim + QR + PDF tek sistem',
                    'image_url' => '/assets/slider/banner-1.svg',
                ]),
                new SiteWidget('home_slider', 'slider', [
                    'title' => 'One Cikan Cozumler',
                    'note' => 'Bu alan Super Admin panelinden yonetilen slider icerikleriyle otomatik guncellenir.',
                ]),
                new SiteWidget('home_features', 'features', [
                    'title' => 'Guclu Ozellikler',
                    'note' => 'Panel, icerik ve QR operasyonlarini hizlandiran, zengin ve esnek altyapi.',
                    'items_json' => [
                        ['title' => 'Kategori ve Urun Yonetimi', 'body' => 'Kategori, urun, fiyat ve aciklamalari panelden hizlica ekleyin, guncelleyin, silin.'],
                        ['title' => 'Coklu Dil Destegi', 'body' => 'Menunuzu birden fazla dilde yayinlayin, misafir diline gore otomatik gosterim saglayin.'],
                        ['title' => 'Urun Fotograf ve Medya Arsivi', 'body' => 'Gorselleri panelde arsivleyin, yeniden kullanin, urunlere kolayca baglayin.'],
                        ['title' => 'QR Kod Olusturucu', 'body' => 'Masa bazli QR uretin, tek tikla indirin, servis alanina gore gruplayin.'],
                        ['title' => 'Toplu Fiyat Guncelleme', 'body' => 'Yuzde veya sabit tutar ile onlarca urunun fiyatini ayni anda degistirin.'],
                        ['title' => 'PDF & QR Sablonlari', 'body' => 'Etiket, masa karti ve PDF tasarimlarini hazir sablonlarla yonetin.'],
                    ],
                ]),
                new SiteWidget('home_panel', 'panel', [
                    'title' => 'Panel Gorunumleri',
                    'note' => 'Panel uzerinden icerik, gorsel ve QR akisini tek yerden yonetin.',
                    'shots_json' => [
                        ['title' => 'Panel Genel Bakis', 'body' => 'Restoran, sube ve icerik durumunu tek ekranda izleyin.', 'image' => '/assets/panel/panel-1.svg'],
                        ['title' => 'Menu Duzenleyici', 'body' => 'Kategori, urun, fiyat ve gorselleri dakikalar icinde guncelleyin.', 'image' => '/assets/panel/panel-2.svg'],
                        ['title' => 'QR & PDF Kurulumu', 'body' => 'QR kod, masa karti ve PDF sablonlarini tek akista yonetin.', 'image' => '/assets/panel/panel-3.svg'],
                    ],
                ]),
                new SiteWidget('home_plans', 'plans', [
                    'title' => 'Planlar',
                    'note' => 'Butcenize gore baslayin, operasyon buyudukce ayni altyapida bir ust pakete gecin.',
                    'badge' => 'En Cok Tercih Edilen',
                ]),
                new SiteWidget('home_references', 'references', [
                    'title' => 'Referanslar',
                    'note' => 'Farkli segmentlerden restoran markalarinin operasyonuna uyumlu bir altyapi.',
                    'items_json' => [
                        ['title' => 'Anadolu Mutfaklari', 'body' => '3 subede tek panelden menu ve PDF etiketi yonetimi.'],
                        ['title' => 'Kiyi Bistro Group', 'body' => 'Sezona gore hizli fiyat guncelleme ve masa bazli QR dagitimi.'],
                        ['title' => 'Gurme Sokak Lezzetleri', 'body' => 'Operasyon ekibi ile merkez ekip arasinda rol bazli kontrollu yonetim.'],
                    ],
                ]),
                new SiteWidget('home_blog', 'blog', [
                    'title' => 'Blog',
                    'note' => 'Dijital menu, restoran operasyonu ve buyume stratejilerine dair icerikler.',
                ]),
                new SiteWidget('home_contact', 'contact', [
                    'title' => 'Iletisim',
                    'note' => 'Satis demosu, fiyatlandirma ve onboarding sureci icin bize ulasin.',
                    'email' => 'iletisim@altayqrmenu.com',
                    'phone' => '+90 850 000 00 00',
                ]),
            ];

            $order = 0;
            foreach ($widgets as $widget) {
                $widget->setSortOrder($order);
                $widget->setIsActive(true);
                $manager->persist($widget);
                ++$order;
            }
        }

        // Check if demo restaurant exists
        $restaurant = $manager->getRepository(Restaurant::class)->findOneBy(['slug' => 'demo-restoran']);
        if ($restaurant === null) {
            $restaurant = new Restaurant('Demo Restoran', 'demo-restoran', $theme);
            $manager->persist($restaurant);
        }

        // Check if admin user exists
        $existingUser = $manager->getRepository(User::class)->findOneBy(['email' => 'admin@qrmenu.test']);
        if ($existingUser !== null) {
            // Update password hash to ensure it works
            $hashedPassword = $this->passwordHasher->hashPassword($existingUser, 'admin123');
            $existingUser->setPasswordHash($hashedPassword);
            $manager->flush();

            return;
        }

        // Create admin user with properly hashed password
        $user = new User(
            email: 'admin@qrmenu.test',
            passwordHash: '', // temporary, will be set below
            firstName: 'Admin',
            lastName: 'User',
        );

        $hashedPassword = $this->passwordHasher->hashPassword($user, 'admin123');
        $user->setPasswordHash($hashedPassword);
        $user->setRestaurant($restaurant);
        $user->addRole($roleOwner);
        $user->addRole($roleSuperAdmin);

        $manager->persist($user);
        $manager->flush();
    }
}
