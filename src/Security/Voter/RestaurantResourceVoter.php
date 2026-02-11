<?php

namespace App\Security\Voter;

use App\Entity\Category;
use App\Entity\Media;
use App\Entity\Product;
use App\Entity\QrCode;
use App\Entity\RestaurantPage;
use App\Entity\RestaurantSocialLink;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Verifies that the authenticated user owns the resource being accessed.
 * Returns 404-equivalent denial for cross-tenant access attempts.
 */
class RestaurantResourceVoter extends Voter
{
    private const SUPPORTED_ATTRIBUTES = ['VIEW', 'EDIT', 'DELETE'];

    private const SUPPORTED_CLASSES = [
        Category::class,
        Product::class,
        Media::class,
        QrCode::class,
        RestaurantPage::class,
        RestaurantSocialLink::class,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, self::SUPPORTED_ATTRIBUTES, true)) {
            return false;
        }

        foreach (self::SUPPORTED_CLASSES as $class) {
            if ($subject instanceof $class) {
                return true;
            }
        }

        return false;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Super admins can access any resource
        if ($user->isSuperAdmin()) {
            return true;
        }

        $accessibleRestaurantIds = $user->getAccessibleRestaurantIds();
        if ($accessibleRestaurantIds === []) {
            return false;
        }

        $resourceRestaurant = match (true) {
            $subject instanceof Category,
            $subject instanceof Product,
            $subject instanceof Media => $subject->getRestaurant(),
            $subject instanceof QrCode,
            $subject instanceof RestaurantPage,
            $subject instanceof RestaurantSocialLink => $subject->getRestaurant(),
            default => null,
        };

        if ($resourceRestaurant === null) {
            return false;
        }

        return in_array($resourceRestaurant->getId(), $accessibleRestaurantIds, true);
    }
}
