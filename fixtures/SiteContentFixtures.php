<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Entity\SiteContent;
use App\Entity\Translation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SiteContentFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $defaults = [
            'home_header_brand' => ['Header Brand', 'Altay QR Menu'],
            'home_header_cta_text' => ['Header CTA Text', 'Panel Girisi'],
            'home_header_cta_url' => ['Header CTA URL', '/admin/login'],
            'home_hero_eyebrow' => ['Hero Eyebrow', 'Kurumsal QR Menu Altyapisi'],
            'home_hero_title' => ['Hero Title', 'Tek panelden menuyu yonetin, masaya ozel QR deneyimiyle servisi hizlandirin.'],
            'home_hero_description' => ['Hero Description', 'Altay QR Menu; restoranlar, zincir markalar ve franchise operasyonlari icin tasarlandi. Urun, kategori, masa, personel ve PDF cikti sureclerini tek akista yonetir.'],
            'home_hero_primary_text' => ['Hero Primary Text', 'Planlari Incele'],
            'home_hero_primary_url' => ['Hero Primary URL', '#plans'],
            'home_hero_secondary_text' => ['Hero Secondary Text', 'Tum Ozellikleri Gor'],
            'home_hero_secondary_url' => ['Hero Secondary URL', '#ozellikler'],
            'home_contact_title' => ['Contact Title', 'Satis ve Destek'],
            'home_contact_note' => ['Contact Note', 'Satis demosu, fiyatlandirma ve onboarding sureci icin bize ulasin.'],
            'home_contact_email' => ['Contact Email', 'iletisim@altayqrmenu.com'],
            'home_contact_phone' => ['Contact Phone', '+90 850 000 00 00'],
            'home_footer_text' => ['Footer Text', 'Altay QR Menu - tum haklari saklidir.'],
            'home_slider_1' => [
                'Kurumsal QR Menu Altyapisi',
                (string) json_encode([
                    'description' => 'Menu, masa ve PDF akislarini tek panelden yonetin.',
                    'imageUrl' => '/assets/slider/banner-1.svg',
                    'kicker' => 'One Cikan Cozum',
                    'note' => 'Super Admin > Slider ekranindan degistirilebilir.',
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            'home_slider_2' => [
                'Toplu QR Olusturma',
                (string) json_encode([
                    'description' => 'Yuzlerce masa icin QR kodlarini toplu olustur ve yonet.',
                    'imageUrl' => '/assets/slider/banner-2.svg',
                    'kicker' => 'Hizli Operasyon',
                    'note' => 'Gorsel URL alanina kendi banner dosyanizi girebilirsiniz.',
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            'home_slider_3' => [
                'Sube Bazli Kontrol',
                (string) json_encode([
                    'description' => 'Merkez ve subeleri tek sistemde, rol bazli olarak yonetin.',
                    'imageUrl' => '/assets/slider/banner-3.svg',
                    'kicker' => 'Merkezi Yonetim',
                    'note' => 'CMS ekranindan header, footer ve iletisim bilgilerini de guncelleyin.',
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
        ];

        $contentByKey = [];
        foreach ($defaults as $key => [$title, $body]) {
            $existing = $manager->getRepository(SiteContent::class)->findOneBy(['keyName' => $key]);
            if (!$existing instanceof SiteContent) {
                $existing = new SiteContent($key, $title, $body);
                $existing->setIsPublished(true);
                $manager->persist($existing);
            }

            $contentByKey[$key] = $existing;
        }

        $manager->flush();

        $translations = $this->localizedDefaults();
        foreach ($translations as $locale => $rows) {
            foreach ($rows as $key => $value) {
                if (!isset($contentByKey[$key])) {
                    continue;
                }

                $contentId = (int) $contentByKey[$key]->getId();
                if ($contentId <= 0) {
                    continue;
                }

                if (is_array($value)) {
                    $this->upsertTranslation($manager, 'site_content', $contentId, $locale, 'title', (string) ($value['title'] ?? ''));
                    $this->upsertTranslation($manager, 'site_content', $contentId, $locale, 'slider_description', (string) ($value['description'] ?? ''));
                    $this->upsertTranslation($manager, 'site_content', $contentId, $locale, 'slider_kicker', (string) ($value['kicker'] ?? ''));
                    $this->upsertTranslation($manager, 'site_content', $contentId, $locale, 'slider_note', (string) ($value['note'] ?? ''));

                    continue;
                }

                $this->upsertTranslation($manager, 'site_content', $contentId, $locale, 'body', (string) $value);
            }
        }

        $manager->flush();
    }

    /**
     * @return array<string, array<string, string|array<string, string>>>
     */
    private function localizedDefaults(): array
    {
        return [
            'en' => [
                'home_header_brand' => 'Altay QR Menu',
                'home_header_cta_text' => 'Admin Login',
                'home_header_cta_url' => '/admin/login',
                'home_hero_eyebrow' => 'Enterprise QR Menu Infrastructure',
                'home_hero_title' => 'Manage your menu from one panel and speed up service with table-specific QR experience.',
                'home_hero_description' => 'Altay QR Menu is built for restaurants, chains, and franchise operations. Manage products, categories, tables, staff, and PDF outputs in one flow.',
                'home_hero_primary_text' => 'View Plans',
                'home_hero_primary_url' => '#plans',
                'home_hero_secondary_text' => 'See All Features',
                'home_hero_secondary_url' => '#ozellikler',
                'home_contact_title' => 'Sales & Support',
                'home_contact_note' => 'Contact us for sales demo, pricing, and onboarding process.',
                'home_contact_email' => 'contact@altayqrmenu.com',
                'home_contact_phone' => '+90 850 000 00 00',
                'home_footer_text' => 'Altay QR Menu - all rights reserved.',
                'home_slider_1' => [
                    'title' => 'Enterprise QR Menu Platform',
                    'description' => 'Manage menu, table, and PDF flows from a single panel.',
                    'kicker' => 'Featured Solution',
                    'note' => 'Editable from Super Admin > Slider.',
                ],
                'home_slider_2' => [
                    'title' => 'Bulk QR Generation',
                    'description' => 'Generate and manage QR codes for hundreds of tables in bulk.',
                    'kicker' => 'Fast Operations',
                    'note' => 'You can set your own banner URL in the image field.',
                ],
                'home_slider_3' => [
                    'title' => 'Branch-based Control',
                    'description' => 'Manage headquarters and branches in one system with role-based access.',
                    'kicker' => 'Central Management',
                    'note' => 'Update header, footer, and contact data from CMS.',
                ],
            ],
            'es' => [
                'home_header_brand' => 'Altay QR Menu',
                'home_header_cta_text' => 'Acceso al Panel',
                'home_header_cta_url' => '/admin/login',
                'home_hero_eyebrow' => 'Infraestructura QR Empresarial',
                'home_hero_title' => 'Administra tu menu desde un solo panel y acelera el servicio con QR por mesa.',
                'home_hero_description' => 'Altay QR Menu esta disenado para restaurantes, cadenas y franquicias. Gestiona productos, categorias, mesas, personal y PDF en un solo flujo.',
                'home_hero_primary_text' => 'Ver Planes',
                'home_hero_primary_url' => '#plans',
                'home_hero_secondary_text' => 'Ver Funciones',
                'home_hero_secondary_url' => '#ozellikler',
                'home_contact_title' => 'Ventas y Soporte',
                'home_contact_note' => 'Contactanos para demo, precios e incorporacion.',
                'home_contact_email' => 'contacto@altayqrmenu.com',
                'home_contact_phone' => '+90 850 000 00 00',
                'home_footer_text' => 'Altay QR Menu - todos los derechos reservados.',
                'home_slider_1' => [
                    'title' => 'Plataforma QR Empresarial',
                    'description' => 'Gestiona menu, mesas y PDF desde un unico panel.',
                    'kicker' => 'Solucion Destacada',
                    'note' => 'Editable desde Super Admin > Slider.',
                ],
                'home_slider_2' => [
                    'title' => 'Generacion Masiva de QR',
                    'description' => 'Crea y administra QR para cientos de mesas en bloque.',
                    'kicker' => 'Operacion Rapida',
                    'note' => 'Puedes usar tu propia URL de banner.',
                ],
                'home_slider_3' => [
                    'title' => 'Control por Sucursal',
                    'description' => 'Gestiona central y sucursales en un solo sistema.',
                    'kicker' => 'Gestion Central',
                    'note' => 'Actualiza header, footer y contacto desde CMS.',
                ],
            ],
            'ar' => [
                'home_header_brand' => 'Altay QR Menu',
                'home_header_cta_text' => 'دخول لوحة التحكم',
                'home_header_cta_url' => '/admin/login',
                'home_hero_eyebrow' => 'منصة قوائم QR للمؤسسات',
                'home_hero_title' => 'أدر قائمتك من لوحة واحدة وسرّع الخدمة عبر QR مخصص لكل طاولة.',
                'home_hero_description' => 'تم تصميم Altay QR Menu للمطاعم والسلاسل والفرنشايز. إدارة المنتجات والفئات والطاولات والموظفين وملفات PDF في تدفق واحد.',
                'home_hero_primary_text' => 'عرض الخطط',
                'home_hero_primary_url' => '#plans',
                'home_hero_secondary_text' => 'عرض الميزات',
                'home_hero_secondary_url' => '#ozellikler',
                'home_contact_title' => 'المبيعات والدعم',
                'home_contact_note' => 'تواصل معنا لعرض المبيعات والتسعير وخطة الإعداد.',
                'home_contact_email' => 'contact@altayqrmenu.com',
                'home_contact_phone' => '+90 850 000 00 00',
                'home_footer_text' => 'Altay QR Menu - جميع الحقوق محفوظة.',
                'home_slider_1' => [
                    'title' => 'منصة QR للمؤسسات',
                    'description' => 'أدر تدفقات القائمة والطاولات وPDF من لوحة واحدة.',
                    'kicker' => 'حل مميز',
                    'note' => 'يمكن تعديلها من Super Admin > Slider.',
                ],
                'home_slider_2' => [
                    'title' => 'إنشاء QR بالجملة',
                    'description' => 'أنشئ وأدر رموز QR لمئات الطاولات دفعة واحدة.',
                    'kicker' => 'تشغيل سريع',
                    'note' => 'يمكنك إدخال رابط البنر الخاص بك.',
                ],
                'home_slider_3' => [
                    'title' => 'تحكم حسب الفرع',
                    'description' => 'إدارة المركز والفروع في نظام واحد بصلاحيات حسب الدور.',
                    'kicker' => 'إدارة مركزية',
                    'note' => 'حدّث بيانات الرأس والتذييل والتواصل من CMS.',
                ],
            ],
            'hi' => [
                'home_header_brand' => 'Altay QR Menu',
                'home_header_cta_text' => 'पैनल लॉगिन',
                'home_header_cta_url' => '/admin/login',
                'home_hero_eyebrow' => 'एंटरप्राइज QR मेन्यू इन्फ्रास्ट्रक्चर',
                'home_hero_title' => 'एक पैनल से मेन्यू मैनेज करें और टेबल-विशेष QR से सेवा तेज करें।',
                'home_hero_description' => 'Altay QR Menu रेस्टोरेंट, चेन और फ्रैंचाइज़ ऑपरेशन्स के लिए बनाया गया है। प्रोडक्ट, कैटेगरी, टेबल, स्टाफ और PDF को एक फ्लो में मैनेज करें।',
                'home_hero_primary_text' => 'प्लान देखें',
                'home_hero_primary_url' => '#plans',
                'home_hero_secondary_text' => 'सभी फीचर देखें',
                'home_hero_secondary_url' => '#ozellikler',
                'home_contact_title' => 'सेल्स और सपोर्ट',
                'home_contact_note' => 'डेमो, प्राइसिंग और ऑनबोर्डिंग के लिए संपर्क करें।',
                'home_contact_email' => 'contact@altayqrmenu.com',
                'home_contact_phone' => '+90 850 000 00 00',
                'home_footer_text' => 'Altay QR Menu - सर्वाधिकार सुरक्षित।',
                'home_slider_1' => [
                    'title' => 'एंटरप्राइज QR मेन्यू प्लेटफॉर्म',
                    'description' => 'मेन्यू, टेबल और PDF फ्लो एक पैनल से चलाएं।',
                    'kicker' => 'मुख्य समाधान',
                    'note' => 'Super Admin > Slider से बदला जा सकता है।',
                ],
                'home_slider_2' => [
                    'title' => 'बल्क QR जनरेशन',
                    'description' => 'सैकड़ों टेबल के लिए QR कोड एक साथ बनाएं और मैनेज करें।',
                    'kicker' => 'तेज़ ऑपरेशन',
                    'note' => 'आप अपनी बैनर URL दे सकते हैं।',
                ],
                'home_slider_3' => [
                    'title' => 'ब्रांच-आधारित नियंत्रण',
                    'description' => 'मुख्यालय और ब्रांच को एक सिस्टम में रोल-बेस्ड तरीके से मैनेज करें।',
                    'kicker' => 'केंद्रीय प्रबंधन',
                    'note' => 'CMS से header, footer और contact अपडेट करें।',
                ],
            ],
            'zh' => [
                'home_header_brand' => 'Altay QR Menu',
                'home_header_cta_text' => '进入后台',
                'home_header_cta_url' => '/admin/login',
                'home_hero_eyebrow' => '企业级二维码菜单平台',
                'home_hero_title' => '在一个后台管理菜单，并通过桌台专属二维码提升服务效率。',
                'home_hero_description' => 'Altay QR Menu 适用于餐厅、连锁与加盟体系。可在一个流程中管理商品、分类、桌台、员工与 PDF 输出。',
                'home_hero_primary_text' => '查看套餐',
                'home_hero_primary_url' => '#plans',
                'home_hero_secondary_text' => '查看全部功能',
                'home_hero_secondary_url' => '#ozellikler',
                'home_contact_title' => '销售与支持',
                'home_contact_note' => '如需演示、报价与上线支持，请联系我们。',
                'home_contact_email' => 'contact@altayqrmenu.com',
                'home_contact_phone' => '+90 850 000 00 00',
                'home_footer_text' => 'Altay QR Menu - 版权所有。',
                'home_slider_1' => [
                    'title' => '企业级二维码菜单',
                    'description' => '在一个后台管理菜单、桌台与 PDF 流程。',
                    'kicker' => '核心方案',
                    'note' => '可在 Super Admin > Slider 中修改。',
                ],
                'home_slider_2' => [
                    'title' => '批量生成二维码',
                    'description' => '可为数百张桌台批量生成并管理二维码。',
                    'kicker' => '高效运营',
                    'note' => '图片地址可替换为你的横幅 URL。',
                ],
                'home_slider_3' => [
                    'title' => '分店级管理',
                    'description' => '总部与分店在同一系统中按角色权限管理。',
                    'kicker' => '集中管理',
                    'note' => '也可在 CMS 更新页眉、页脚与联系信息。',
                ],
            ],
        ];
    }

    private function upsertTranslation(
        ObjectManager $manager,
        string $entityType,
        int $entityId,
        string $locale,
        string $field,
        string $value,
    ): void {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return;
        }

        $existing = $manager->getRepository(Translation::class)->findOneBy([
            'entityType' => $entityType,
            'entityId' => $entityId,
            'locale' => $locale,
            'field' => $field,
        ]);

        if ($existing instanceof Translation) {
            $existing->setValue($trimmed);

            return;
        }

        $manager->persist(new Translation($entityType, $entityId, $locale, $field, $trimmed));
    }
}
