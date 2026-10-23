<?php

declare(strict_types=1);

namespace App\Security;

/**
 * The roles granted by hand, from the highest. Their hierarchy is in config/packages/security.yaml;
 * every user has ROLE_USER.
 */
enum Role: string
{
    /**
     * Manages the users.
     */
    case SuperAdmin = 'ROLE_SUPER_ADMIN';

    /**
     * Manages the business data of the application.
     */
    case Admin = 'ROLE_ADMIN';

    /**
     * The translation key of its name.
     */
    public function label(): string
    {
        return 'role.' . $this->name;
    }
}
