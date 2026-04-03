<?php

/**
 * Redirects to a new path.
 *
 * @param string $path - New path
 * @return void
 */
function redirect(string $path)
{
    if (preg_match('/[\r\n]/', $path)) {
        throw new \InvalidArgumentException('Invalid redirect path: newline characters are not allowed.');
    }

    if (!preg_match('/^\/(?!\/)/', $path)) {
        throw new \InvalidArgumentException('Invalid redirect path: only relative paths starting with / are allowed.');
    }

    header("Location: $path");
    exit;
}

/**
 * Renders a view.
 *
 * @param string $string - View name
 * @param object|array $element - Data to be rendered
 * @param string $path - Path to the views directory
 * @return void
 */
function view(string $string, $element, string $path = 'views')
{
    if (strpos($string, "\0") !== false) {
        throw new \InvalidArgumentException('Invalid view name: null bytes are not allowed.');
    }

    // Defense-in-depth: reject explicit path-traversal segments before hitting the filesystem.
    // The realpath() containment check below is the authoritative guard.
    if (preg_match('#(^|[/\\\\])\.\.($|[/\\\\])#', $string)) {
        throw new \InvalidArgumentException('Invalid view name: path traversal sequences are not allowed.');
    }

    $resolvedBase = realpath($path);
    if ($resolvedBase === false) {
        throw new \InvalidArgumentException('Invalid view: base directory does not exist.');
    }

    $resolvedPath = realpath($resolvedBase . DIRECTORY_SEPARATOR . $string . '.php');
    if ($resolvedPath === false) {
        throw new \InvalidArgumentException("View not found: {$string}");
    }
    if (strpos($resolvedPath, $resolvedBase . DIRECTORY_SEPARATOR) !== 0) {
        throw new \InvalidArgumentException('Invalid view: resolved path is outside the allowed views directory.');
    }

    $array = is_array($element) ? $element : json_decode(json_encode($element), true);
    extract($array);
    include $resolvedPath;
}
