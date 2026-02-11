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

        if ($subscription instanceof CustomerSubscription) {
            return;
        }

        $start = new \DateTimeImmutable('today');
        $end = $start->modify('+1 year');
        $subscription = new CustomerSubscription($account, $plan, $start, $end);
        $manager->persist($subscription);
    }

    private function seedDemoMenu(ObjectManager $manager, Restaurant $restaurant): void
    {
        $menu = [
            'Baslangiclar' => [
                ['Mercimek Corbasi', '95.00'],
                ['Peynir Tabagi', '165.00'],
            ],
            'Ana Yemekler' => [
                ['Tavuk Izgara', '280.00'],
                ['Kasap Kofte', '320.00'],
            ],
            'Icecekler' => [
                ['Ayran', '45.00'],
                ['Soda', '35.00'],
            ],
        ];

        $sort = 1;
        foreach ($menu as $categoryName => $products) {
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
            foreach ($products as [$productName, $price]) {
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

        foreach ($menu as $categoryName => $products) {
            $category = $manager->getRepository(Category::class)->findOneBy([
                'restaurant' => $restaurant,
                'name' => $categoryName,
            ]);
            if ($category instanceof Category) {
                $this->seedMenuTranslations($manager, 'category', (int) $category->getId(), $categoryName);
            }

            foreach ($products as [$productName, $price]) {
                $product = $manager->getRepository(Product::class)->findOneBy([
                    'restaurant' => $restaurant,
                    'name' => $productName,
                ]);
                if ($product instanceof Product) {
                    $this->seedMenuTranslations($manager, 'product', (int) $product->getId(), $productName);
                }
            }
        }
    }

    private function seedMenuTranslations(ObjectManager $manager, string $entityType, int $entityId, string $source): void
    {
        if ($entityId <= 0) {
            return;
        }

        foreach ($this->menuTranslationMap($source) as $locale => $translated) {
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
     * @return array<string, string>
     */
    private function menuTranslationMap(string $source): array
    {
        $map = [
            'Baslangiclar' => ['en' => 'Starters', 'es' => 'Entrantes', 'ar' => 'المقبلات', 'hi' => 'स्टार्टर्स', 'zh' => '前菜'],
            'Ana Yemekler' => ['en' => 'Main Courses', 'es' => 'Platos Principales', 'ar' => 'الأطباق الرئيسية', 'hi' => 'मुख्य व्यंजन', 'zh' => '主菜'],
            'Icecekler' => ['en' => 'Drinks', 'es' => 'Bebidas', 'ar' => 'المشروبات', 'hi' => 'पेय', 'zh' => '饮品'],
            'Mercimek Corbasi' => ['en' => 'Lentil Soup', 'es' => 'Sopa de Lentejas', 'ar' => 'شوربة العدس', 'hi' => 'मसूर सूप', 'zh' => '扁豆汤'],
            'Peynir Tabagi' => ['en' => 'Cheese Plate', 'es' => 'Tabla de Quesos', 'ar' => 'طبق أجبان', 'hi' => 'चीज प्लेट', 'zh' => '奶酪拼盘'],
            'Tavuk Izgara' => ['en' => 'Grilled Chicken', 'es' => 'Pollo a la Parrilla', 'ar' => 'دجاج مشوي', 'hi' => 'ग्रिल्ड चिकन', 'zh' => '烤鸡'],
            'Kasap Kofte' => ['en' => 'Butcher Meatballs', 'es' => 'Albondigas del Chef', 'ar' => 'كفتة الجزار', 'hi' => 'कसाई कोफ्ता', 'zh' => '招牌肉丸'],
            'Ayran' => ['en' => 'Ayran', 'es' => 'Ayran', 'ar' => 'عيران', 'hi' => 'आइरन', 'zh' => '酸奶饮品'],
            'Soda' => ['en' => 'Soda', 'es' => 'Soda', 'ar' => 'صودا', 'hi' => 'सोडा', 'zh' => '苏打水'],
        ];

        return $map[$source] ?? ['en' => $source, 'es' => $source, 'ar' => $source, 'hi' => $source, 'zh' => $source];
    }
}
