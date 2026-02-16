<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Restaurant;
use App\Entity\RestaurantPopup;
use App\Entity\Theme;
use PHPUnit\Framework\TestCase;

final class RestaurantPopupTest extends TestCase
{
    public function testPopupDefaultsToActive(): void
    {
        $popup = $this->createPopup();

        self::assertTrue($popup->isActive());
    }

    public function testPopupVisibilityDependsOnScheduleAndActiveFlag(): void
    {
        $startsAt = new \DateTimeImmutable('2026-02-10 10:00:00');
        $endsAt = new \DateTimeImmutable('2026-02-10 14:00:00');
        $popup = $this->createPopup($startsAt, $endsAt);

        self::assertFalse($popup->isVisibleAt(new \DateTimeImmutable('2026-02-10 09:59:00')));
        self::assertTrue($popup->isVisibleAt(new \DateTimeImmutable('2026-02-10 10:00:00')));
        self::assertTrue($popup->isVisibleAt(new \DateTimeImmutable('2026-02-10 12:30:00')));
        self::assertTrue($popup->isVisibleAt(new \DateTimeImmutable('2026-02-10 14:00:00')));
        self::assertFalse($popup->isVisibleAt(new \DateTimeImmutable('2026-02-10 14:01:00')));

        $popup->setIsActive(false);

        self::assertFalse($popup->isVisibleAt(new \DateTimeImmutable('2026-02-10 12:30:00')));
    }

    public function testPopupButtonFieldsCanBeUpdated(): void
    {
        $popup = $this->createPopup();

        self::assertNull($popup->getButtonLabel());
        self::assertNull($popup->getButtonUrl());

        $popup->setButtonLabel('Urune Git');
        $popup->setButtonUrl('/menu/demo-r-1/product/abc');

        self::assertSame('Urune Git', $popup->getButtonLabel());
        self::assertSame('/menu/demo-r-1/product/abc', $popup->getButtonUrl());
    }

    private function createPopup(?\DateTimeImmutable $startsAt = null, ?\DateTimeImmutable $endsAt = null): RestaurantPopup
    {
        $theme = new Theme('default', 'Default', []);
        $restaurant = new Restaurant('Demo', 'demo', $theme);

        return new RestaurantPopup(
            $restaurant,
            'Acilis Kampanyasi',
            $startsAt ?? new \DateTimeImmutable('-1 hour'),
            $endsAt ?? new \DateTimeImmutable('+1 hour')
        );
    }
}
