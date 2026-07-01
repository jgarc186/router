<?php

require_once '../vendor/autoload.php';

use Garcia\Router;
use Garcia\Helpers;
use Garcia\Exceptions\RouterException;

// Example of a simple route
Router::addRoute('GET', '/health', fn () => 'Hello, world!');

// Example of a route with a parameter
Router::addRoute('GET', '/health/:id', fn ($params) => "User ID: {$params['id']}");

// Example of a route that returns a JSON response
Router::get('/api/health/:id', fn ($params) => [
    'id' => $params['id'],
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

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
Router::get('/redirect', fn () => Helpers::redirect('https://www.example.com'));

// Example of using the resource method
class Test
{
    public function index()
    {
        return ['Hello' => 'index'];
    }
    public function store()
    {
        return ['Hello' => 'store'];
    }
    public function show()
    {
        return ['Hello' => 'show'];
    }
    public function update($params)
    {
        return ['Hello' => "update".$params['id']];
    }
    public function destroy()
    {
        return ['Hello' => 'destroy'];
    }
}

Router::resource('/test', Test::class)->middleware(fn () => 'hello');

try {
    Router::run();
} catch (RouterException $e) {
    echo $e->getMessage();
}
