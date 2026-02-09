<?php

namespace App\Security\Voter;

use App\Entity\Product;
use App\Entity\User;
use App\Enum\ProductStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Controls which status transitions are allowed for a product.
 */
class ProductStatusVoter extends Voter
{
    public const SUBMIT = 'PRODUCT_SUBMIT';
    public const APPROVE = 'PRODUCT_APPROVE';
    public const REJECT = 'PRODUCT_REJECT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::SUBMIT, self::APPROVE, self::REJECT], true)
            && $subject instanceof Product;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Product $product */
        $product = $subject;

        return match ($attribute) {
            self::SUBMIT => $this->canSubmit($product, $user),
            self::APPROVE => $this->canApprove($product, $user),
            self::REJECT => $this->canReject($product, $user),
            default => false,
        };
    }

    private function canSubmit(Product $product, User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        return $product->getStatus()->canTransitionTo(ProductStatus::PENDING_APPROVAL);
    }

    private function canApprove(Product $product, User $user): bool
    {
        if (!$user->isSuperAdmin()) {
            return false;
        }

        return $product->getStatus() === ProductStatus::PENDING_APPROVAL;
    }

    private function canReject(Product $product, User $user): bool
    {
        if (!$user->isSuperAdmin()) {
            return false;
        }

        return $product->getStatus() === ProductStatus::PENDING_APPROVAL;
    }
}
