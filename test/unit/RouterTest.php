<?php

namespace Test\Unit;

use Garcia\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        Router::clearRoutes();
    }

    public function testAddRoute(): void
    {
        Router::addRoute('GET', '/test', fn () => 'test');

        $this->assertCount(1, Router::getRoutes());
    }

    public function testGet(): void
    {
        Router::get('/test', fn () => 'test');

        $routes = Router::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertSame('GET', $routes[0]['method']);
        $this->assertSame('/test', $routes[0]['path']);
    }

    public function testPost(): void
    {
        Router::post('/test', fn () => 'test');

        $routes = Router::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertSame('POST', $routes[0]['method']);
    }

    public function testPut(): void
    {
        Router::put('/test', fn () => 'test');

        $routes = Router::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertSame('PUT', $routes[0]['method']);
    }

    public function testDelete(): void
    {
        Router::delete('/test', fn () => 'test');

        $routes = Router::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertSame('DELETE', $routes[0]['method']);
    }

    public function testPatch(): void
    {
        Router::patch('/test', fn () => 'test');

        $routes = Router::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertSame('PATCH', $routes[0]['method']);
    }

    public function testOptions(): void
    {
        Router::options('/test', fn () => 'test');

        $routes = Router::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertSame('OPTIONS', $routes[0]['method']);
    }

    public function testAnyRegistersAllMethods(): void
    {
        Router::any('/test', fn () => 'test');

        $routes = Router::getRoutes();
        $this->assertCount(6, $routes);

        $methods = array_column($routes, 'method');
        $this->assertContains('GET', $methods);
        $this->assertContains('POST', $methods);
        $this->assertContains('PUT', $methods);
        $this->assertContains('DELETE', $methods);
        $this->assertContains('PATCH', $methods);
        $this->assertContains('OPTIONS', $methods);
    }

    public function testGetRouteWithArrayHandlerRegisters(): void
    {
        Router::get('/test', fn () => ['id' => 1, 'name' => 'John Doe']);

        $this->assertCount(1, Router::getRoutes());
    }

    public function testAddResource(): void
    {
        Router::resource('/tests', FakeController::class);

        $this->assertCount(6, Router::getRoutes());
    }

    public function testRouteParameterExtraction(): void
    {
        Router::get('/users/:id', fn ($params) => ['id' => $params['id']]);

        ob_start();
        Router::handleRequest('GET', '/users/42');
        $output = ob_get_clean();

        $this->assertSame('{"id":"42"}', $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleRequestNotFoundReturns404(): void
    {
        ob_start();
        Router::handleRequest('GET', '/nonexistent');
        $output = ob_get_clean();

        $this->assertStringContainsString('IMPORTANT:', $output);
    }

    public function testErrorViewEscapesXss(): void
    {
        $message = '<script>alert("xss")</script>';
        ob_start();
        view('error', ['message' => $message], __DIR__ . '/../../src/Garcia/views');
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testTemplateViewEscapesXss(): void
    {
        $name = '<script>alert("xss")</script>';
        ob_start();
        view('template', ['name' => $name], __DIR__ . '/../../src/Garcia/views');
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testViewsEscapeQuotes(): void
    {
        $payload = '" onmouseover="alert(1)"';

        ob_start();
        view('error', ['message' => $payload], __DIR__ . '/../../src/Garcia/views');
        $errorOutput = ob_get_clean();

        ob_start();
        view('template', ['name' => $payload], __DIR__ . '/../../src/Garcia/views');
        $templateOutput = ob_get_clean();

        $this->assertStringContainsString('&quot;', $errorOutput);
        $this->assertStringContainsString('&quot;', $templateOutput);
    }

    public function testViewsHandleEmptyStrings(): void
    {
        ob_start();
        view('error', ['message' => ''], __DIR__ . '/../../src/Garcia/views');
        $errorOutput = ob_get_clean();

        ob_start();
        view('template', ['name' => ''], __DIR__ . '/../../src/Garcia/views');
        $templateOutput = ob_get_clean();

        $this->assertStringContainsString('IMPORTANT: ', $errorOutput);
        $this->assertStringContainsString('Hello, !', $templateOutput);
    }

    public function testViewsHandleNullSafely(): void
    {
        ob_start();
        view('error', ['message' => null], __DIR__ . '/../../src/Garcia/views');
        $errorOutput = ob_get_clean();

        ob_start();
        view('template', ['name' => null], __DIR__ . '/../../src/Garcia/views');
        $templateOutput = ob_get_clean();

        $this->assertStringContainsString('IMPORTANT: ', $errorOutput);
        $this->assertStringContainsString('Hello, !', $templateOutput);
    }

    public function testClearRoutesEmptiesRouteArray(): void
    {
        Router::get('/a', fn () => 'a');
        Router::post('/b', fn () => 'b');
        $this->assertCount(2, Router::getRoutes());

        Router::clearRoutes();
        $this->assertCount(0, Router::getRoutes());
        $this->assertSame([], Router::getRoutes());
    }

    public function testCallHandlerWithArrayReturnProducesJson(): void
    {
        Router::get('/api/test', fn () => ['key' => 'value']);

        ob_start();
        Router::handleRequest('GET', '/api/test');
        $output = ob_get_clean();

        $this->assertSame('{"key":"value"}', $output);
    }

    public function testCallHandlerWithObjectReturnProducesJson(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';
        Router::get('/api/obj', fn () => $obj);

        ob_start();
        Router::handleRequest('GET', '/api/obj');
        $output = ob_get_clean();

        $this->assertSame('{"name":"test"}', $output);
    }

    public function testCallHandlerWithStringReturnEchoesDirectly(): void
    {
        Router::get('/api/str', fn () => 'hello world');

        ob_start();
        Router::handleRequest('GET', '/api/str');
        $output = ob_get_clean();

        $this->assertSame('hello world', $output);
    }

    public function testCallHandlerSideEffectOutputIsDiscarded(): void
    {
        Router::get('/api/warn', function () {
            echo 'side-effect-output';
            return ['status' => 'ok'];
        });

        ob_start();
        Router::handleRequest('GET', '/api/warn');
        $output = ob_get_clean();

        $this->assertSame('{"status":"ok"}', $output);
    }
}
