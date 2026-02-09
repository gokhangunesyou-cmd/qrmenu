<?php

namespace App\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class TenantFilter extends SQLFilter
{
    private const TENANT_ENTITIES = [
        'App\Entity\Category',
        'App\Entity\Product',
        'App\Entity\Media',
        'App\Entity\QrCode',
        'App\Entity\RestaurantPage',
        'App\Entity\RestaurantSocialLink',
    ];

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!in_array($targetEntity->getName(), self::TENANT_ENTITIES, true)) {
            return '';
        }

        if (!$targetEntity->hasField('restaurant') && !$targetEntity->hasAssociation('restaurant')) {
            return '';
        }

        try {
            $restaurantId = $this->getParameter('restaurant_id');
        } catch (\InvalidArgumentException) {
            return '';
        }

        $column = $targetEntity->hasAssociation('restaurant')
            ? $targetEntity->getSingleAssociationJoinColumnName('restaurant')
            : 'restaurant_id';

        return sprintf('%s.%s = %s', $targetTableAlias, $column, $restaurantId);
    }
}
