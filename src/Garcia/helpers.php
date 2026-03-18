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
    $array = is_array($element) ? $element : json_decode(json_encode($element), true);
    extract($array);
    include $path . DIRECTORY_SEPARATOR . "{$string}.php";
}
