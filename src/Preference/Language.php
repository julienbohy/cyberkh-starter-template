<?php

declare(strict_types=1);

namespace App\Preference;

/**
 * The languages of the interface and of the emails. English is the default; the values are the
 * locales enabled in config/packages/translation.yaml.
 */
enum Language: string
{
    case English = 'en';
    case French = 'fr';

    /**
     * The name of the language in itself, so that anyone finds theirs whatever the current language.
     */
    public function nativeName(): string
    {
        return match ($this) {
            self::English => 'English',
            self::French => 'Français',
        };
    }
}
