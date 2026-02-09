<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SuperAdminVoter extends Voter
{
    public const MANAGE_RESTAURANTS = 'SUPER_ADMIN_MANAGE_RESTAURANTS';
    public const MANAGE_CATALOG = 'SUPER_ADMIN_MANAGE_CATALOG';
    public const MANAGE_DEFAULTS = 'SUPER_ADMIN_MANAGE_DEFAULTS';

    private const ATTRIBUTES = [
        self::MANAGE_RESTAURANTS,
        self::MANAGE_CATALOG,
        self::MANAGE_DEFAULTS,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return $user->isSuperAdmin();
    }
}
