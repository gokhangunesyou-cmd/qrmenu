<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Plan;

final class PlanPeriod
{
    public static function addDuration(\DateTimeImmutable $date, Plan $plan): \DateTimeImmutable
    {
        $months = self::durationMonthsForCode($plan->getCode());

        return $date->modify(sprintf('+%d months', $months));
    }

    public static function durationMonthsForCode(string $planCode): int
    {
        return strtolower($planCode) === 'free' ? 3 : 12;
    }
}

