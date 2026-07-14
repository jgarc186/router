gi# Router
A lightweight routing tool for PHP, drawing inspiration from Express and Laravel.

## Description

This router provides a straightforward way to define and manage routes in PHP. Inspired by frameworks like Express and Laravel, it lets you assign specific handlers to routes and then processes incoming requests by matching them to the appropriate handler.

## Installation

```bash
composer require josegarcia/router
```

## Usage

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Garcia\Router;
use Garcia\Helpers;
use Garcia\Exceptions\RouterException;

// Example of a simple route
Router::addRoute('GET', '/health', fn () => 'Hello, world!');

// Example of a route with a parameter
Router::addRoute('GET', '/health/:id', fn ($params) => "User ID: {$params['id']}");

// Example of a route that returns a JSON response
Router::get( '/api/health/:id', fn ($params) => ['id' => $params['id'], 'name' => 'John Doe', 'email' => 'john@example.com']);

// Example of rendering a view
Router::get('/view', fn () => [
    // we need to pass the view name and the data to be rendered as a template
    'view' => 'template',
    // we can also pass the path to the views directory
    'path' => 'views',
    // we can also pass the data to be rendered
    'data' => [
        'name' => 'John Doe'
    ]
]);

Router::post('/health', fn ($params) => "Hello, {$params['name']} {$params['last']}!");

// Example of rendering
Router::get('/redirect', fn () => Helpers::redirect('/home'));

// Example of using the resource method
class Test {
    public function index(){
        return ['Hello' => 'index'];
    }
    public function store(){
        return ['Hello' => 'store'];
    }
    public function show(){
        return ['Hello' => 'show'];
    }
    public function update($params){
        return ['Hello' => "update".$params['id']];
    }
    public function destroy(){
        return ['Hello' => 'destroy'];
    }
}

Router::resource('/test', Test::class);

// Unmatched routes are handled automatically: the router sends a 404 header
// and renders its built-in error view, so no extra code is needed for that case.
try {
    Router::run();
} catch (RouterException $e) {
    // Thrown when a registered handler is not callable.
    http_response_code(500);
    echo 'Router error: ' . $e->getMessage();
}
```

## API

the router has the following methods:
get, post, put, patch, delete, options, any, addRoute, run, middleware, and resource.

Redirects and views are provided by the `Garcia\Helpers` class: `Helpers::redirect()` and `Helpers::view()`.

### get, post, put, patch, delete, options, any

These methods are used to define routes. They take two parameters: the route and the handler. The route can contain parameters, which are defined by a colon followed by the parameter name. The handler can be a function or a string. If it is a string, it is assumed to be the name of a function to be called.

### addRoute

This method is used to define routes. It takes three parameters: the method, the route, and the handler. The route can contain parameters, which are defined by a colon followed by the parameter name. The handler can be a function or a string. If it is a string, it is assumed to be the name of a function to be called.

Like the other route-registration methods, it returns the router instance so it can be chained directly into `middleware()`:

```php
Router::addRoute('GET', '/admin', fn () => 'Admin area')->middleware(function () {
    if (!isset($_SESSION['user'])) {
        Helpers::redirect('/login');
    }
});
```

### run

This method is used to run the router. It takes no parameters.

### Helpers::redirect

This method is used to redirect to another URL. It takes one parameter: the URL to redirect to.

> **Security warning:** Never pass raw user input directly as the redirect path. The function enforces the following constraints and throws `\InvalidArgumentException` on violation:
>
> - Carriage return or line feed characters (`\r`, `\n`) in the path are rejected, preventing header/cookie injection via a crafted `Location` header.
> - Only same-origin relative paths starting with a single `/` are accepted. Absolute URLs (`https://evil.com/...`), protocol-relative URLs (`//evil.com/...`), and other schemes are rejected, preventing open redirects.
>
> Always validate and whitelist redirect targets before passing them to `Helpers::redirect()`.

### Helpers::view

Renders a PHP template. Its signature is:

```php
Helpers::view(string $view, array|object $data, ?string $path = null): void
```

- `$view` — the template name, resolved to `<path>/<view>.php`.
- `$data` — data exposed to the template (see the security notes below).
- `$path` — the base views directory. Defaults to the library's own `views` directory (`__DIR__ . '/views'`), so the built-in 404 error view resolves correctly regardless of the application's current working directory.

#### Security

`view()` loads and executes a PHP file and exposes `$data` as template variables, so **both the view name and the data are security-sensitive**. Three distinct risks apply:

**1. Path traversal — the `$view` / `$path` arguments.** A view name derived from user input could try to escape the views directory (e.g. `../../etc/passwd`) and execute or disclose arbitrary files. `view()` defends against this and throws `\InvalidArgumentException` when:

- the view name contains a `../` path-traversal sequence,
- the view name contains a null byte,
- the resolved file falls outside the base views directory (symlink escapes are blocked via `realpath()`), or
- the base directory does not exist.

These checks are defense-in-depth, **not** a licence to forward raw input. Resolve user input against a fixed whitelist of view names in your own code before calling `view()`.

**2. `extract()` variable injection — the *keys* of `$data`.** Internally `view()` calls `extract($data, EXTR_SKIP)`, so every key in `$data` becomes a local variable inside the template. `EXTR_SKIP` prevents a crafted key (e.g. `__viewPath`) from overwriting the router's own already-set variables, but you should still **never let user input control the keys of `$data`** — pass a fixed set of keys that you define in code.

**3. Output escaping / XSS — the *values* in `$data`.** `view()` does not escape anything; template values are printed exactly as your template prints them. Escape untrusted values in the template with `htmlspecialchars()` (or an equivalent templating escape) to prevent cross-site scripting.

#### Safe usage

```php
// Route: /articles/:slug — render an article page from user-controlled input.
Router::get('/articles/:slug', function ($params) {
    // 1. Resolve the requested view against a whitelist YOU control —
    //    never pass $params['slug'] straight through as the view name.
    $allowed = ['article', 'article-preview'];
    $view = in_array($params['slug'], $allowed, true) ? $params['slug'] : 'article';

    // 2. Build $data with keys YOU define; only the values come from input.
    $data = [
        'title' => $params['slug'],  // untrusted value
        'body'  => 'Lorem ipsum...', // trusted, hard-coded value
    ];

    Helpers::view($view, $data, __DIR__ . '/views');
});
```

```php
<!-- views/article.php -->
<!-- 3. Escape every untrusted value on output to prevent XSS. -->
<h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
<div><?= $body ?></div>
```

### middleware

This method is used to attach middleware to the most recently defined route. It is called on the instance returned by a route-registration method, enabling chaining:

```php
Router::get('/admin', fn () => 'Admin area')->middleware(function () {
    if (!isset($_SESSION['user'])) {
        Helpers::redirect('/login');
    }
});
```

### Deprecated global functions

Earlier versions exposed `redirect()` and `view()` as global functions. These collide with same-named helpers in other frameworks (e.g. Laravel, Symfony), so they have been moved to the `Garcia\Helpers` class. The global functions still work — they now delegate to `Garcia\Helpers` — but are deprecated and guarded with `function_exists()`, so they step aside if another framework already defines a global `redirect()`/`view()`. New code should call `Helpers::redirect()` / `Helpers::view()` directly.

### resource

This method sets multiple routes such a Get, Post, Patch, Put, Delete and sets the corresponding callbacks.
Based on restful controllers.

## Examples

You can find more examples in the [examples](sample) directory.

## Tests

You can run the tests with the following command:

```bash
composer test
```

## Docker Development

The Docker setup uses **PHP 8.4 CLI**. No local PHP installation is required.
The environment variable `PHP_CS_FIXER_IGNORE_ENV=1` is set because php-cs-fixer may not yet officially support PHP 8.4.

Build the image:

```bash
docker compose build
```

Install dependencies:

```bash
docker compose run --rm app composer install
```

Run tests:

```bash
docker compose run --rm app composer test
```

Lint:

```bash
docker compose run --rm app composer lint
```

Fix code style:

```bash
docker compose run --rm app composer fix
```

Open an interactive shell:

```bash
docker compose run --rm app bash
```

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

[MIT](LICENSE)
