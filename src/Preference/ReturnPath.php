<?php

declare(strict_types=1);

namespace App\Preference;

use Symfony\Component\HttpFoundation\Request;

/**
 * Where to go back after changing a preference: the page the form was on, as a path of this
 * site only, so the switch cannot be turned into an open redirect.
 */
final class ReturnPath
{
    public static function from(Request $request): string
    {
        $path = $request->request->getString('_target');

        return preg_match('#^/(?![/\\\\])[^\s]*$#', $path) === 1 ? $path : '/';
    }
}
