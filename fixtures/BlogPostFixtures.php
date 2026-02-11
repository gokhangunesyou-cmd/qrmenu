<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Entity\BlogPost;
use App\Entity\Translation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class BlogPostFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $posts = [
            [
                'title' => 'QR Menu Nedir? Restoranlar Icin Uygulama Rehberi',
                'slug' => 'qr-menu-nedir-restoranlar-icin-uygulama-rehberi',
                'metaTitle' => 'QR Menu Nedir? Restoranlar Icin Uygulama Rehberi',
                'metaDescription' => 'QR menu nedir, restoranlarda nasil kurulur ve servis hizina nasil etki eder? Adim adim uygulama rehberi.',
                'publishedAt' => '2026-02-01 10:00:00',
                'body' => "QR menu, musterinin masadaki karekodunu telefon kamerasi ile okutarak dijital menuye ulasmasini saglayan sistemdir.\n\nRestoranlar icin en buyuk fayda menu degisikliklerini tek panelden aninda yayina alabilmektir. Basili menu maliyeti azalir, fiyat guncellemesi hizlanir ve hata riski duser.\n\nKurulum adimlari:\n1) Restoran menu kategorilerini sisteme ekleyin.\n2) Her urun icin aciklama, fiyat ve alerjen bilgisi girin.\n3) Masalara ozel QR kodlari olusturun.\n4) Personeli panel kullanimina alin ve yayin akisini belirleyin.\n\nDogru kurguda QR menu, hem operasyonu hem de musteri deneyimini iyilestiren bir altyapiya donusur.",
            ],
            [
                'title' => 'Karekod Menu ile Basili Menu Maliyetini Azaltmanin 7 Yolu',
                'slug' => 'karekod-menu-ile-basili-menu-maliyetini-azaltmanin-7-yolu',
                'metaTitle' => 'Karekod Menu ile Basili Menu Maliyetini Azaltmanin 7 Yolu',
                'metaDescription' => 'Karekod menu altyapisi ile baski maliyetini dusurun, menu guncelleme hizini artirin. Restoranlar icin 7 uygulanabilir yontem.',
                'publishedAt' => '2026-02-03 11:00:00',
                'body' => "Basili menu maliyeti, sik fiyat degisimi yapan restoranlarda ciddi bir gider kalemidir. Karekod menu yapisi ile bu maliyet belirgin sekilde azaltilabilir.\n\n1) Tekrar baski ihtiyacini kaldirin.\n2) Sezonluk urunleri panelden ac-kapa yonetin.\n3) Kampanya urunlerini anlik guncelleyin.\n4) Yanlis fiyatli urun riskini azaltin.\n5) Coklu sube fiyat farklarini merkezi yonetin.\n6) Personel egitimini tek panelde standartlastirin.\n7) Menu versiyon gecmisini takip ederek hatalari geriye alin.\n\nKarekod menu, sadece teknolojik bir vitrin degil; dogru uygulandiginda finansal bir optimizasyon aracidir.",
            ],
            [
                'title' => 'Restoranlar Icin Dijital Menu Secerken Dikkat Edilmesi Gereken 10 Kriter',
                'slug' => 'restoranlar-icin-dijital-menu-secerken-dikkat-edilmesi-gereken-10-kriter',
                'metaTitle' => 'Restoranlar Icin Dijital Menu Secerken 10 Kriter',
                'metaDescription' => 'Dijital menu seciminde panel kolayligi, hiz, coklu dil, SEO ve raporlama gibi kritik noktalar. Restoranlar icin kontrol listesi.',
                'publishedAt' => '2026-02-05 14:00:00',
                'body' => "Dijital menu secimi yaparken sadece gorunum odakli karar vermek uzun vadede operasyonel sorunlara yol acar.\n\nKontrol listesi:\n1) Menu guncelleme hizi\n2) Mobil acilis performansi\n3) Coklu dil destegi\n4) Alerjen ve icerik alanlari\n5) Masa bazli QR yonetimi\n6) Coklu sube senaryosu\n7) Yetki ve rol yonetimi\n8) Raporlama ve okunma olcumleri\n9) SEO uyumlu sayfa yapisi\n10) Destek ve onboarding sureci\n\nBu 10 kriter, restoranin hem bugunku ihtiyacini hem de buyume planini destekleyen bir sistem secmesini kolaylastirir.",
            ],
            [
                'title' => 'QR Menu Fiyatlari Neye Gore Degisir? Paket Karsilastirma Rehberi',
                'slug' => 'qr-menu-fiyatlari-neye-gore-degisir-paket-karsilastirma-rehberi',
                'metaTitle' => 'QR Menu Fiyatlari Neye Gore Degisir?',
                'metaDescription' => 'QR menu fiyatlarini belirleyen faktorler: sube sayisi, kullanici limiti, entegrasyon ve destek kapsami. Paket secim rehberi.',
                'publishedAt' => '2026-02-07 09:30:00',
                'body' => "QR menu fiyatlari tek bir etikete indirgenemez. Fiyatlarin degisiminde teknik kapsam belirleyicidir.\n\nTemel fiyat faktorleri:\n- Sube ve masa sayisi\n- Kullanici/rol limiti\n- PDF veya etiket cikti ihtiyaci\n- Coklu dil ve kampanya yonetimi\n- Ozel entegrasyon talepleri\n- Destek suresi ve SLA\n\nPaket secerken sadece yillik ucrete degil, operasyonel surecleri ne kadar azalttigina bakmak gerekir. En dogru paket, gunluk is yukunu azaltan pakettir.",
            ],
            [
                'title' => 'Cafe ve Restoranlarda QR Kod Menu Kurulumu: 1 Gunde Canliya Alma Plani',
                'slug' => 'cafe-ve-restoranlarda-qr-kod-menu-kurulumu-1-gunde-canliya-alma-plani',
                'metaTitle' => 'Cafe ve Restoranlarda QR Kod Menu Kurulumu',
                'metaDescription' => 'Cafe ve restoranlar icin 1 gunde QR kod menu yayina alma plani. Hazirlik, test ve canliya gecis adimlari.',
                'publishedAt' => '2026-02-09 16:15:00',
                'body' => "Dogru planlama ile QR kod menu kurulumu ayni gun icinde yayina alinabilir.\n\nSabah hazirlik:\n- Kategori ve urun listesi netlestirilir\n- Fiyat, aciklama ve alerjen verileri toplanir\n\nOglen kurulum:\n- Veriler panele islenir\n- Masa QR kodlari uretilir\n- Mobil acilis testleri yapilir\n\nAksam canliya gecis:\n- Personel 30 dakikalik hizli egitim alir\n- Masalara QR kartlari yerlestirilir\n- Ilk servis turunda veri ve akis kontrol edilir\n\nBu plan ile restoran, uzun projelere gerek kalmadan dijital menuye kontrollu sekilde gecis yapar.",
            ],
        ];

        foreach ($posts as $row) {
            $existing = $manager->getRepository(BlogPost::class)->findOneBy(['slug' => $row['slug']]);

            if ($existing instanceof BlogPost) {
                $post = $existing;
                $post->setTitle($row['title']);
                $post->setBody($row['body']);
            } else {
                $post = new BlogPost($row['title'], $row['slug'], $row['body']);
                $manager->persist($post);
            }

            $post->setMetaTitle($row['metaTitle']);
            $post->setMetaDescription($row['metaDescription']);
            $post->setIsPublished(true);
            $post->setPublishedAt(new \DateTimeImmutable($row['publishedAt'] . ' +03:00'));
            $post->setUpdatedAt(new \DateTimeImmutable());
        }

        $manager->flush();

        $localizedDefaults = $this->localizedDefaults();
        foreach ($posts as $row) {
            $post = $manager->getRepository(BlogPost::class)->findOneBy(['slug' => $row['slug']]);
            if (!$post instanceof BlogPost) {
                continue;
            }

            $postId = (int) $post->getId();
            if ($postId <= 0) {
                continue;
            }

            foreach ($localizedDefaults as $locale => $valuesBySlug) {
                $value = $valuesBySlug[$row['slug']] ?? null;
                if (!is_array($value)) {
                    continue;
                }

                $this->upsertTranslation($manager, $postId, $locale, 'title', (string) ($value['title'] ?? ''));
                $this->upsertTranslation($manager, $postId, $locale, 'body', (string) ($value['body'] ?? ''));
                $this->upsertTranslation($manager, $postId, $locale, 'meta_title', (string) ($value['meta_title'] ?? ''));
                $this->upsertTranslation($manager, $postId, $locale, 'meta_description', (string) ($value['meta_description'] ?? ''));
            }
        }

        $manager->flush();
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function localizedDefaults(): array
    {
        return [
            'en' => [
                'qr-menu-nedir-restoranlar-icin-uygulama-rehberi' => [
                    'title' => 'What Is a QR Menu? Practical Guide for Restaurants',
                    'meta_title' => 'What Is a QR Menu? Practical Guide for Restaurants',
                    'meta_description' => 'What is a QR menu, how is it deployed, and how does it improve service speed? A practical step-by-step guide.',
                    'body' => "A QR menu lets guests scan a table code and instantly open your digital menu on mobile.\n\nThe key benefit is operational speed: menu updates are published from one panel without reprinting costs.\n\nSetup plan:\n1) Add categories.\n2) Add products with price and allergen data.\n3) Generate table QR codes.\n4) Define publish workflow for your team.\n\nWith the right setup, QR menu systems improve both operations and guest experience.",
                ],
                'karekod-menu-ile-basili-menu-maliyetini-azaltmanin-7-yolu' => [
                    'title' => '7 Ways to Reduce Printed Menu Costs with QR Menu',
                    'meta_title' => '7 Ways to Reduce Printed Menu Costs with QR Menu',
                    'meta_description' => 'Reduce print costs and update faster with QR menu infrastructure. Seven practical methods for restaurants.',
                    'body' => "Printed menus are expensive when prices and campaigns change frequently.\n\nWith QR menus you can:\n1) Eliminate reprint cycles.\n2) Enable seasonal items instantly.\n3) Update promotions in minutes.\n4) Reduce wrong-price risk.\n5) Manage branch price differences centrally.\n6) Standardize team workflow.\n7) Track version history.\n\nDone right, QR menu is an operational optimization tool.",
                ],
                'restoranlar-icin-dijital-menu-secerken-dikkat-edilmesi-gereken-10-kriter' => [
                    'title' => '10 Criteria for Choosing a Digital Menu Platform',
                    'meta_title' => '10 Criteria for Choosing a Digital Menu Platform',
                    'meta_description' => 'A checklist for restaurants: speed, multilingual support, SEO, workflow, and analytics.',
                    'body' => "Choosing only by design creates long-term operational issues.\n\nChecklist:\n1) Update speed\n2) Mobile performance\n3) Multilingual support\n4) Allergen fields\n5) Table-based QR\n6) Multi-branch support\n7) Role and permission model\n8) Analytics\n9) SEO-friendly pages\n10) Support and onboarding\n\nThese criteria help you choose a system that scales with your growth.",
                ],
                'qr-menu-fiyatlari-neye-gore-degisir-paket-karsilastirma-rehberi' => [
                    'title' => 'What Affects QR Menu Pricing? Package Comparison Guide',
                    'meta_title' => 'What Affects QR Menu Pricing?',
                    'meta_description' => 'Pricing factors: branch count, user limits, integrations, and support scope.',
                    'body' => "QR menu pricing depends on technical and operational scope.\n\nMain factors:\n- Number of branches and tables\n- User and role limits\n- PDF or label output needs\n- Multilingual and campaign management\n- Integration requirements\n- Support/SLA coverage\n\nChoose the package that reduces daily operational load, not only annual cost.",
                ],
                'cafe-ve-restoranlarda-qr-kod-menu-kurulumu-1-gunde-canliya-alma-plani' => [
                    'title' => 'One-Day Go-Live Plan for Cafe and Restaurant QR Menu',
                    'meta_title' => 'One-Day QR Menu Go-Live Plan',
                    'meta_description' => 'Preparation, setup, and launch checklist to publish QR menu in one day.',
                    'body' => "With a clear plan, QR menu can go live in one day.\n\nMorning prep:\n- Finalize product/category list\n- Collect pricing and allergen data\n\nNoon setup:\n- Enter data into panel\n- Generate table QR codes\n- Run mobile tests\n\nEvening launch:\n- Quick staff onboarding\n- Place QR cards on tables\n- Monitor first service cycle\n\nThis approach enables controlled and fast digital transition.",
                ],
            ],
        ];
    }

    private function upsertTranslation(ObjectManager $manager, int $entityId, string $locale, string $field, string $value): void
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return;
        }

        $existing = $manager->getRepository(Translation::class)->findOneBy([
            'entityType' => 'blog_post',
            'entityId' => $entityId,
            'locale' => $locale,
            'field' => $field,
        ]);

        if ($existing instanceof Translation) {
            $existing->setValue($trimmed);
            return;
        }

        $manager->persist(new Translation('blog_post', $entityId, $locale, $field, $trimmed));
    }
}
