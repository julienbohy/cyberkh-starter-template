<?php

declare(strict_types=1);

namespace App\RealTime;

use App\Security\Role;

/**
 * The live channels of the application. A page follows them with `{% set live = [...] %}`, and only
 * the users with the role of a topic get it in their subscriber cookie.
 */
enum LiveTopic: string
{
    case Users = 'admin/users';

    public function uri(): string
    {
        return '/' . $this->value;
    }

    public function role(): Role
    {
        return match ($this) {
            self::Users => Role::SuperAdmin,
        };
    }
}
