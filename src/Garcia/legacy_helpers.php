<?php

use Garcia\Helpers;

// Deprecated backward-compatibility layer.
//
// These global functions collide with same-named helpers in other frameworks
// (e.g. Laravel, Symfony). Use Garcia\Helpers::redirect() and
// Garcia\Helpers::view() instead. This file only exists so that existing
// code written against the old global functions keeps working, and it is
// guarded with function_exists() so it steps aside if another framework
// already defines these names.

if (!function_exists('validateRedirectPath')) {
    /**
     * @deprecated Use Garcia\Helpers::validateRedirectPath() instead.
     */
    function validateRedirectPath(string $path): void
    {
        Helpers::validateRedirectPath($path);
    }
}

if (!function_exists('redirect')) {
    /**
     * @deprecated Use Garcia\Helpers::redirect() instead.
     */
    function redirect(string $path): void
    {
        Helpers::redirect($path);
    }
}

if (!function_exists('view')) {
    /**
     * @deprecated Use Garcia\Helpers::view() instead.
     */
    function view(string $string, $element, ?string $path = null)
    {
        return Helpers::view($string, $element, $path);
    }
}
