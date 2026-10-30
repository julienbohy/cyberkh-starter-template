<?php

declare(strict_types=1);

namespace App\Preference;

/**
 * The color theme of the interface. System follows the setting of the device.
 */
enum Theme: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';
}
