<?php

declare(strict_types=1);

namespace App\Preference;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * The cookies that keep the choices of a visitor, and of a user before they sign in.
 */
final class PreferenceCookie
{
    public const string LANGUAGE = 'language';

    public const string THEME = 'theme';

    public static function create(string $name, string $value): Cookie
    {
        return Cookie::create($name, $value)
            ->withExpires(new \DateTimeImmutable('+1 year'))
            ->withSecure(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
