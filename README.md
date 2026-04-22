# Router
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
Router::get('/redirect', fn () => redirect('/home'));

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

Router::run();
```

## API
the router has the following methods:
get, post, put, patch, delete, options, head, any, addRoute, run, redirect, view, json, and render.

### get, post, put, patch, delete, options, head, any
These methods are used to define routes. They take two parameters: the route and the handler. The route can contain parameters, which are defined by a colon followed by the parameter name. The handler can be a function or a string. If it is a string, it is assumed to be the name of a function to be called.

### addRoute
This method is used to define routes. It takes three parameters: the method, the route, and the handler. The route can contain parameters, which are defined by a colon followed by the parameter name. The handler can be a function or a string. If it is a string, it is assumed to be the name of a function to be called.

### run
This method is used to run the router. It takes no parameters.

### redirect
This method is used to redirect to another URL. It takes one parameter: the URL to redirect to.

### view
This method is used to render a view. It takes one parameter: the name of the view to render.

The built-in 404 handler always resolves its error view relative to the library's own directory (`__DIR__ . '/views'`), so it works correctly regardless of the application's current working directory.

> **Security warning:** Never pass raw user input directly as the view name or path. The function enforces the following constraints and throws `\InvalidArgumentException` on violation:
> - View names containing `../` path traversal sequences are rejected.
> - Null bytes in the view name are rejected.
> - The resolved file path must remain within the base views directory (symlink escapes are blocked via `realpath()`).
> - The base directory must exist.
>
> Always validate and whitelist view names before passing them to `view()`.

### json
This method is used to return a JSON response. It takes one parameter: the data to be returned as JSON.

### render
This method is used to render a view. It takes one parameter: the name of the view to render.

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
