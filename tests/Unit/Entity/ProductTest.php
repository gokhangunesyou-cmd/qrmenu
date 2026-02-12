<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Restaurant;
use App\Entity\Theme;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testViewCountersDefaultToZero(): void
    {
        $product = $this->createProduct();

        self::assertSame(0, $product->getMenuViewCount());
        self::assertSame(0, $product->getDetailViewCount());
    }

    public function testViewCountersIncrementAndDoNotGoNegative(): void
    {
        $product = $this->createProduct();

        $product->incrementMenuViewCount();
        $product->incrementMenuViewCount(2);
        $product->incrementDetailViewCount();
        $product->incrementDetailViewCount(3);

        self::assertSame(3, $product->getMenuViewCount());
        self::assertSame(4, $product->getDetailViewCount());

        $product->setMenuViewCount(-10);
        $product->setDetailViewCount(-2);

        self::assertSame(0, $product->getMenuViewCount());
        self::assertSame(0, $product->getDetailViewCount());
    }

    private function createProduct(): Product
    {
        $theme = new Theme('default', 'Default', []);
        $restaurant = new Restaurant('Demo', 'demo', $theme);
        $category = new Category('Main', $restaurant);

        return new Product('Sample Product', '120.00', $category, $restaurant);
    }
}
