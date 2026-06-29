<?php

namespace App\Support;

use Illuminate\Http\Request;

final class PerPageOptions
{
    public const ALLOWED = [20, 50, 100];

    public static function resolve(Request $request, $parameter, $default = 50)
    {
        $value = (int) $request->query($parameter, $default);

        return in_array($value, self::ALLOWED, true) ? $value : $default;
    }
}
