<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Restaurant;
use App\Entity\Theme;
use PHPUnit\Framework\TestCase;

final class RestaurantTest extends TestCase
{
    public function testMenuTemplateDefaultsToShowcase(): void
    {
        $restaurant = $this->createRestaurant();

        self::assertSame('showcase', $restaurant->getMenuTemplate());
    }

    public function testMenuTemplateCanBeUpdated(): void
    {
        $restaurant = $this->createRestaurant();

        $restaurant->setMenuTemplate('editorial');

        self::assertSame('editorial', $restaurant->getMenuTemplate());
    }

    public function testEnabledLocalesDefaultToTr(): void
    {
        $restaurant = $this->createRestaurant();

        self::assertSame(['tr'], $restaurant->getEnabledLocales());
    }

    public function testEnabledLocalesNormalizeAndUpdateDefaultLocale(): void
    {
        $restaurant = $this->createRestaurant();
        $restaurant->setDefaultLocale('tr');

        $restaurant->setEnabledLocales([' EN ', 'en', 'ar']);

        self::assertSame(['en', 'ar'], $restaurant->getEnabledLocales());
        self::assertSame('en', $restaurant->getDefaultLocale());
    }

    public function testSettingDefaultLocaleAddsItToEnabledLocales(): void
    {
        $restaurant = $this->createRestaurant();
        $restaurant->setEnabledLocales(['tr']);

        $restaurant->setDefaultLocale('ru');

        self::assertSame('ru', $restaurant->getDefaultLocale());
        self::assertSame(['tr', 'ru'], $restaurant->getEnabledLocales());
    }

    public function testInteractionSettingsDefaultToDisabled(): void
    {
        $restaurant = $this->createRestaurant();

        self::assertFalse($restaurant->isCountProductDetailViews());
        self::assertFalse($restaurant->isWhatsappOrderEnabled());
    }

    public function testInteractionSettingsCanBeUpdated(): void
    {
        $restaurant = $this->createRestaurant();

        $restaurant->setCountProductDetailViews(true);
        $restaurant->setWhatsappOrderEnabled(true);

        self::assertTrue($restaurant->isCountProductDetailViews());
        self::assertTrue($restaurant->isWhatsappOrderEnabled());
    }

    private function createRestaurant(): Restaurant
    {
        $theme = new Theme('default', 'Default', []);

        return new Restaurant('Demo', 'demo', $theme);
    }
}
