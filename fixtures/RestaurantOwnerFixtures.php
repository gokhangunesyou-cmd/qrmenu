<?php

namespace App\Fixtures;

use App\Entity\Category;
use App\Entity\CustomerAccount;
use App\Entity\CustomerSubscription;
use App\Entity\Plan;
use App\Entity\Product;
use App\Entity\Restaurant;
use App\Entity\Role;
use App\Entity\Theme;
use App\Entity\Translation;
use App\Entity\User;
use App\Enum\ProductStatus;
use App\Service\PlanPeriod;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RestaurantOwnerFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $ownerRole = $this->getReference(RoleFixtures::ROLE_RESTAURANT_OWNER, Role::class);
        $theme = $this->getReference(ThemeFixtures::THEME_CLASSIC, Theme::class);

        $freePlan = $manager->getRepository(Plan::class)->findOneBy(['code' => 'free']);
        $premiumPlan = $manager->getRepository(Plan::class)->findOneBy(['code' => 'premium']);
        if (!$freePlan instanceof Plan || !$premiumPlan instanceof Plan) {
            return;
        }

        $freeAccount = $this->getOrCreateAccount($manager, 'Free Musteri', 'free@qrmenu.local');
        $premiumAccount = $this->getOrCreateAccount($manager, 'Premium Musteri', 'premium@qrmenu.local');

        $freeRestaurant = $this->getOrCreateRestaurant($manager, $theme, $freeAccount, 'Free Demo Restoran', 'free-demo-restoran', 'free@qrmenu.local');
        $premiumRestaurantOne = $this->getOrCreateRestaurant($manager, $theme, $premiumAccount, 'Premium Demo Restoran 1', 'premium-demo-restoran-1', 'premium@qrmenu.local');
        $premiumRestaurantTwo = $this->getOrCreateRestaurant($manager, $theme, $premiumAccount, 'Premium Demo Restoran 2', 'premium-demo-restoran-2', 'premium2@qrmenu.local');

        $freeUser = $this->getOrCreateUser($manager, 'free@qrmenu.local', 'Free', 'Owner', $ownerRole);
        $freeUser->setCustomerAccount($freeAccount);
        $freeUser->setRestaurant($freeRestaurant);
        $freeUser->addRestaurant($freeRestaurant);

        $premiumUser = $this->getOrCreateUser($manager, 'premium@qrmenu.local', 'Premium', 'Owner', $ownerRole);
        $premiumUser->setCustomerAccount($premiumAccount);
        $premiumUser->setRestaurant($premiumRestaurantOne);
        $premiumUser->addRestaurant($premiumRestaurantOne);
        $premiumUser->addRestaurant($premiumRestaurantTwo);

        $this->ensureSubscription($manager, $freeAccount, $freePlan);
        $this->ensureSubscription($manager, $premiumAccount, $premiumPlan);

        $this->seedDemoMenu($manager, $freeRestaurant);
        $this->seedDemoMenu($manager, $premiumRestaurantOne);
        $this->seedDemoMenu($manager, $premiumRestaurantTwo);

        $manager->persist($freeUser);
        $manager->persist($premiumUser);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            RoleFixtures::class,
            ThemeFixtures::class,
            PlanFixtures::class,
            SuperAdminFixtures::class,
        ];
    }

    private function getOrCreateAccount(ObjectManager $manager, string $name, string $email): CustomerAccount
    {
        $account = $manager->getRepository(CustomerAccount::class)->findOneBy(['name' => $name]);
        if ($account instanceof CustomerAccount) {
            $account->setEmail($email);
            return $account;
        }

        $account = new CustomerAccount($name);
        $account->setEmail($email);
        $manager->persist($account);

        return $account;
    }

    private function getOrCreateRestaurant(
        ObjectManager $manager,
        Theme $theme,
        CustomerAccount $account,
        string $name,
        string $slug,
        string $email,
    ): Restaurant {
        $restaurant = $manager->getRepository(Restaurant::class)->findOneBy(['slug' => $slug]);
        if (!$restaurant instanceof Restaurant) {
            $restaurant = new Restaurant($name, $slug, $theme);
            $manager->persist($restaurant);
        }

        $restaurant->setName($name);
        $restaurant->setEmail($email);
        $restaurant->setIsActive(true);
        $restaurant->setCustomerAccount($account);

        return $restaurant;
    }

    private function getOrCreateUser(
        ObjectManager $manager,
        string $email,
        string $firstName,
        string $lastName,
        Role $ownerRole,
    ): User {
        $user = $manager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $user = new User($email, '', $firstName, $lastName);
        }

        $user->setPasswordHash($this->hasher->hashPassword($user, 'ChangeMe123!'));
        $user->addRole($ownerRole);

        return $user;
    }

    private function ensureSubscription(ObjectManager $manager, CustomerAccount $account, Plan $plan): void
    {
        $subscription = $manager->getRepository(CustomerSubscription::class)->findOneBy([
            'customerAccount' => $account,
            'plan' => $plan,
            'isActive' => true,
        ]);

        $start = new \DateTimeImmutable('today');
        $end = PlanPeriod::addDuration($start, $plan);

        if ($subscription instanceof CustomerSubscription) {
            $subscription->setStartsAt($start);
            $subscription->setEndsAt($end);
            $subscription->setIsActive(true);
            return;
        }

        $subscription = new CustomerSubscription($account, $plan, $start, $end);
        $manager->persist($subscription);
    }

    private function seedDemoMenu(ObjectManager $manager, Restaurant $restaurant): void
    {
        $menu = $this->buildDemoMenu();

        $sort = 1;
        foreach ($menu as $categoryData) {
            $categoryName = $categoryData['name']['tr'];
            $category = $manager->getRepository(Category::class)->findOneBy([
                'restaurant' => $restaurant,
                'name' => $categoryName,
            ]);

            if (!$category instanceof Category) {
                $category = new Category($categoryName, $restaurant);
                $category->setSortOrder($sort);
                $category->setIsActive(true);
                $manager->persist($category);
            }

            $itemSort = 1;
            foreach ($categoryData['products'] as $productData) {
                $productName = $productData['name']['tr'];
                $price = $productData['price'];
                $product = $manager->getRepository(Product::class)->findOneBy([
                    'restaurant' => $restaurant,
                    'category' => $category,
                    'name' => $productName,
                ]);

                if (!$product instanceof Product) {
                    $product = new Product($productName, $price, $category, $restaurant);
                    $manager->persist($product);
                }

                $product->setSortOrder($itemSort);
                $product->setIsActive(true);
                $product->setStatus(ProductStatus::APPROVED);
                $product->setSubmittedAt(new \DateTimeImmutable());
                $itemSort++;
            }

            $sort++;
        }

        $manager->flush();

        foreach ($menu as $categoryData) {
            $categoryName = $categoryData['name']['tr'];
            $category = $manager->getRepository(Category::class)->findOneBy([
                'restaurant' => $restaurant,
                'name' => $categoryName,
            ]);
            if ($category instanceof Category) {
                $this->seedMenuTranslations($manager, 'category', (int) $category->getId(), $categoryData['name']);
            }

            foreach ($categoryData['products'] as $productData) {
                $productName = $productData['name']['tr'];
                $product = $manager->getRepository(Product::class)->findOneBy([
                    'restaurant' => $restaurant,
                    'name' => $productName,
                ]);
                if ($product instanceof Product) {
                    $this->seedMenuTranslations($manager, 'product', (int) $product->getId(), $productData['name']);
                }
            }
        }
    }

    private function seedMenuTranslations(ObjectManager $manager, string $entityType, int $entityId, array $localizedNames): void
    {
        if ($entityId <= 0) {
            return;
        }

        foreach (['tr', 'en', 'ru', 'ar'] as $locale) {
            $translated = $this->resolveLocalizedName($localizedNames, $locale);
            $existing = $manager->getRepository(Translation::class)->findOneBy([
                'entityType' => $entityType,
                'entityId' => $entityId,
                'locale' => $locale,
                'field' => 'name',
            ]);

            if ($existing instanceof Translation) {
                $existing->setValue($translated);
                continue;
            }

            $manager->persist(new Translation($entityType, $entityId, $locale, 'name', $translated));
        }
    }

    /**
     * @param array<string, string> $localizedNames
     */
    private function resolveLocalizedName(array $localizedNames, string $locale): string
    {
        if (isset($localizedNames[$locale])) {
            return $localizedNames[$locale];
        }

        if ($locale === 'ar' && isset($localizedNames['en'])) {
            return $localizedNames['en'];
        }

        if (isset($localizedNames['tr'])) {
            return $localizedNames['tr'];
        }

        if (isset($localizedNames['en'])) {
            return $localizedNames['en'];
        }

        if (isset($localizedNames['ru'])) {
            return $localizedNames['ru'];
        }

        return '';
    }

    /**
     * @return array<int, array{
     *     name: array{tr: string, en: string, ru: string, ar?: string},
     *     products: array<int, array{name: array{tr: string, en: string, ru: string, ar?: string}, price: string}>
     * }>
     */
    private function buildDemoMenu(): array
    {
        return [
            [
                'name' => ['tr' => 'Başlangıçlar', 'en' => 'Starters', 'ru' => 'Закуски', 'ar' => 'المقبلات'],
                'products' => [
                    ['name' => ['tr' => 'Humus', 'en' => 'Hummus', 'ru' => 'Хумус'], 'price' => '130.00'],
                    ['name' => ['tr' => 'Haydari', 'en' => 'Haydari Yogurt Dip', 'ru' => 'Хайдари'], 'price' => '125.00'],
                    ['name' => ['tr' => 'Muhammara', 'en' => 'Muhammara', 'ru' => 'Мухаммара'], 'price' => '135.00'],
                    ['name' => ['tr' => 'Baba Ganuş', 'en' => 'Baba Ghanoush', 'ru' => 'Баба гануш'], 'price' => '140.00'],
                    ['name' => ['tr' => 'Rus Salatası', 'en' => 'Russian Salad', 'ru' => 'Русский салат'], 'price' => '120.00'],
                    ['name' => ['tr' => 'İçli Köfte', 'en' => 'Stuffed Bulgur Meatballs', 'ru' => 'Ичли кёфте'], 'price' => '160.00'],
                ],
            ],
            [
                'name' => ['tr' => 'Çorbalar', 'en' => 'Soups', 'ru' => 'Супы'],
                'products' => [
                    ['name' => ['tr' => 'Mercimek Çorbası', 'en' => 'Lentil Soup', 'ru' => 'Чечевичный суп'], 'price' => '95.00'],
                    ['name' => ['tr' => 'Ezogelin Çorbası', 'en' => 'Ezogelin Soup', 'ru' => 'Суп эзогелин'], 'price' => '105.00'],
                    ['name' => ['tr' => 'Tavuk Suyu Çorbası', 'en' => 'Chicken Broth Soup', 'ru' => 'Куриный суп'], 'price' => '115.00'],
                    ['name' => ['tr' => 'Borş Çorbası', 'en' => 'Borscht', 'ru' => 'Борщ'], 'price' => '135.00'],
                ],
            ],
            [
                'name' => ['tr' => 'Salatalar', 'en' => 'Salads', 'ru' => 'Салаты'],
                'products' => [
                    ['name' => ['tr' => 'Çoban Salata', 'en' => 'Shepherd Salad', 'ru' => 'Пастуший салат'], 'price' => '115.00'],
                    ['name' => ['tr' => 'Mevsim Salata', 'en' => 'Seasonal Salad', 'ru' => 'Сезонный салат'], 'price' => '120.00'],
                    ['name' => ['tr' => 'Gavurdağı Salata', 'en' => 'Walnut Tomato Salad', 'ru' => 'Салат гавурдагы'], 'price' => '145.00'],
                    ['name' => ['tr' => 'Fattoush Salatası', 'en' => 'Fattoush Salad', 'ru' => 'Салат фаттуш'], 'price' => '150.00'],
                ],
            ],
            [
                'name' => ['tr' => 'Pide Çeşitleri', 'en' => 'Pide Varieties', 'ru' => 'Виды пиде'],
                'products' => [
                    ['name' => ['tr' => 'Kaşarlı Pide', 'en' => 'Cheese Pide', 'ru' => 'Пиде с сыром'], 'price' => '220.00'],
                    ['name' => ['tr' => 'Kıymalı Pide', 'en' => 'Minced Meat Pide', 'ru' => 'Пиде с фаршем'], 'price' => '240.00'],
                    ['name' => ['tr' => 'Kuşbaşı Pide', 'en' => 'Diced Meat Pide', 'ru' => 'Пиде с мясом кубиками'], 'price' => '260.00'],
                    ['name' => ['tr' => 'Karışık Pide', 'en' => 'Mixed Pide', 'ru' => 'Ассорти пиде'], 'price' => '275.00'],
                    ['name' => ['tr' => 'Lahmacun', 'en' => 'Turkish Flatbread with Mince', 'ru' => 'Лахмаджун'], 'price' => '110.00'],
                ],
            ],
            [
                'name' => ['tr' => 'Kebaplar', 'en' => 'Kebabs', 'ru' => 'Кебабы'],
                'products' => [
                    ['name' => ['tr' => 'Adana Kebap', 'en' => 'Adana Kebab', 'ru' => 'Адана-кебаб'], 'price' => '330.00'],
                    ['name' => ['tr' => 'Urfa Kebap', 'en' => 'Urfa Kebab', 'ru' => 'Урфа-кебаб'], 'price' => '325.00'],
                    ['name' => ['tr' => 'Beyti Kebap', 'en' => 'Beyti Kebab', 'ru' => 'Бейти-кебаб'], 'price' => '355.00'],
                    ['name' => ['tr' => 'Ali Nazik Kebap', 'en' => 'Ali Nazik Kebab', 'ru' => 'Кебаб Али Назик'], 'price' => '365.00'],
                    ['name' => ['tr' => 'Tavuk Şavurma', 'en' => 'Chicken Shawarma', 'ru' => 'Шаурма с курицей'], 'price' => '295.00'],
                ],
            ],
            [
                'name' => ['tr' => 'Izgaralar', 'en' => 'Grill', 'ru' => 'Гриль'],
                'products' => [
                    ['name' => ['tr' => 'Tavuk Şiş', 'en' => 'Chicken Shish', 'ru' => 'Куриный шашлык'], 'price' => '290.00'],
                    ['name' => ['tr' => 'Izgara Köfte', 'en' => 'Grilled Meatballs', 'ru' => 'Котлеты на гриле'], 'price' => '285.00'],
                    ['name' => ['tr' => 'Kuzu Pirzola', 'en' => 'Lamb Chops', 'ru' => 'Бараньи котлеты'], 'price' => '390.00'],
                    ['name' => ['tr' => 'Somon Izgara', 'en' => 'Grilled Salmon', 'ru' => 'Лосось на гриле'], 'price' => '420.00'],
                ],
            ],
            [
                'name' => ['tr' => 'Ana Yemekler', 'en' => 'Main Courses', 'ru' => 'Основные блюда'],
                'products' => [
                    ['name' => ['tr' => 'İskender Kebap', 'en' => 'Iskender Kebab', 'ru' => 'Искендер-кебаб'], 'price' => '355.00'],
                    ['name' => ['tr' => 'Karnıyarık', 'en' => 'Stuffed Eggplant', 'ru' => 'Фаршированный баклажан'], 'price' => '295.00'],
                    ['name' => ['tr' => 'Beef Stroganoff', 'en' => 'Beef Stroganoff', 'ru' => 'Бефстроганов'], 'price' => '360.00'],
                    ['name' => ['tr' => 'Pelmeni', 'en' => 'Pelmeni Dumplings', 'ru' => 'Пельмени'], 'price' => '265.00'],
                    ['name' => ['tr' => 'Kabsa Pilavı', 'en' => 'Kabsa Rice', 'ru' => 'Рис кабса'], 'price' => '320.00'],
                    ['name' => ['tr' => 'Mandi Pilavı', 'en' => 'Mandi Rice', 'ru' => 'Рис манди'], 'price' => '335.00'],
                ],
            ],
            [
                'name' => ['tr' => 'Tatlılar', 'en' => 'Desserts', 'ru' => 'Десерты'],
                'products' => [
                    ['name' => ['tr' => 'Baklava', 'en' => 'Baklava', 'ru' => 'Баклава'], 'price' => '155.00'],
                    ['name' => ['tr' => 'Künefe', 'en' => 'Kunefe', 'ru' => 'Кюнефе'], 'price' => '165.00'],
                    ['name' => ['tr' => 'Sütlaç', 'en' => 'Rice Pudding', 'ru' => 'Рисовый пудинг'], 'price' => '125.00'],
                    ['name' => ['tr' => 'Medovik', 'en' => 'Honey Cake', 'ru' => 'Медовик'], 'price' => '170.00'],
                    ['name' => ['tr' => 'Basbousa', 'en' => 'Basbousa', 'ru' => 'Басбуса'], 'price' => '150.00'],
                ],
            ],
            [
                'name' => ['tr' => 'Soğuk İçecekler', 'en' => 'Cold Drinks', 'ru' => 'Холодные напитки'],
                'products' => [
                    ['name' => ['tr' => 'Kola', 'en' => 'Cola', 'ru' => 'Кола'], 'price' => '55.00'],
                    ['name' => ['tr' => 'Limonata', 'en' => 'Lemonade', 'ru' => 'Лимонад'], 'price' => '70.00'],
                    ['name' => ['tr' => 'Şalgam Suyu', 'en' => 'Turnip Juice', 'ru' => 'Сок шалгама'], 'price' => '50.00'],
                    ['name' => ['tr' => 'Portakal Suyu', 'en' => 'Orange Juice', 'ru' => 'Апельсиновый сок'], 'price' => '70.00'],
                    ['name' => ['tr' => 'Nar Suyu', 'en' => 'Pomegranate Juice', 'ru' => 'Гранатовый сок'], 'price' => '80.00'],
                ],
            ],
            [
                'name' => ['tr' => 'Alkolsüz İçecekler', 'en' => 'Non-Alcoholic Beverages', 'ru' => 'Безалкогольные напитки'],
                'products' => [
                    ['name' => ['tr' => 'Ayran', 'en' => 'Ayran', 'ru' => 'Айран'], 'price' => '45.00'],
                    ['name' => ['tr' => 'Maden Suyu', 'en' => 'Mineral Water', 'ru' => 'Минеральная вода'], 'price' => '40.00'],
                    ['name' => ['tr' => 'Soda', 'en' => 'Club Soda', 'ru' => 'Содовая'], 'price' => '40.00'],
                    ['name' => ['tr' => 'Şalgam Acılı', 'en' => 'Spicy Turnip Juice', 'ru' => 'Острый шалгам'], 'price' => '55.00'],
                    ['name' => ['tr' => 'Şalgam Acısız', 'en' => 'Mild Turnip Juice', 'ru' => 'Мягкий шалгам'], 'price' => '55.00'],
                ],
            ],
            [
                'name' => ['tr' => 'Alkollü İçecekler', 'en' => 'Alcoholic Beverages', 'ru' => 'Алкогольные напитки'],
                'products' => [
                    ['name' => ['tr' => 'Kadeh Şarap Beyaz', 'en' => 'White Wine (Glass)', 'ru' => 'Белое вино (бокал)'], 'price' => '190.00'],
                    ['name' => ['tr' => 'Kadeh Şarap Kırmızı', 'en' => 'Red Wine (Glass)', 'ru' => 'Красное вино (бокал)'], 'price' => '200.00'],
                    ['name' => ['tr' => 'Bira Şişe', 'en' => 'Beer (Bottle)', 'ru' => 'Пиво (бутылка)'], 'price' => '180.00'],
                    ['name' => ['tr' => 'Bira Fıçı', 'en' => 'Draft Beer', 'ru' => 'Разливное пиво'], 'price' => '170.00'],
                    ['name' => ['tr' => 'Rakı Kadeh', 'en' => 'Raki (Glass)', 'ru' => 'Ракы (бокал)'], 'price' => '230.00'],
                ],
            ],
            [
                'name' => ['tr' => 'Sıcak Kahveler', 'en' => 'Hot Coffees', 'ru' => 'Горячие кофе'],
                'products' => [
                    ['name' => ['tr' => 'Türk Kahvesi', 'en' => 'Turkish Coffee', 'ru' => 'Турецкий кофе'], 'price' => '70.00'],
                    ['name' => ['tr' => 'Espresso', 'en' => 'Espresso', 'ru' => 'Эспрессо'], 'price' => '70.00'],
                    ['name' => ['tr' => 'Americano', 'en' => 'Americano', 'ru' => 'Американо'], 'price' => '75.00'],
                    ['name' => ['tr' => 'Latte', 'en' => 'Latte', 'ru' => 'Латте'], 'price' => '82.00'],
                    ['name' => ['tr' => 'Cappuccino', 'en' => 'Cappuccino', 'ru' => 'Капучино'], 'price' => '82.00'],
                ],
            ],
            [
                'name' => ['tr' => 'Soğuk Kahveler', 'en' => 'Iced Coffees', 'ru' => 'Холодные кофе'],
                'products' => [
                    ['name' => ['tr' => 'Iced Americano', 'en' => 'Iced Americano', 'ru' => 'Айс американо'], 'price' => '85.00'],
                    ['name' => ['tr' => 'Iced Latte', 'en' => 'Iced Latte', 'ru' => 'Айс латте'], 'price' => '90.00'],
                    ['name' => ['tr' => 'Iced Mocha', 'en' => 'Iced Mocha', 'ru' => 'Айс мокка'], 'price' => '95.00'],
                    ['name' => ['tr' => 'Cold Brew', 'en' => 'Cold Brew', 'ru' => 'Колд брю'], 'price' => '95.00'],
                    ['name' => ['tr' => 'Frappe', 'en' => 'Frappe', 'ru' => 'Фраппе'], 'price' => '92.00'],
                ],
            ],
        ];
    }
}
