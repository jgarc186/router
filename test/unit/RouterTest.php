<?php

namespace Test\Unit;

use Garcia\Router;
use PHPUnit\Framework\TestCase;

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
        return ['Hello' => "update" . $params['id']];
    }
    public function destroy()
    {
        return ['Hello' => 'destroy'];
    }
}

class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        Router::clearRoutes();
    }

    /** @test - Test if the route is added to the routes array */
    public function addRoute()
    {
        Router::addRoute('GET', '/test', fn () => 'test');

        $this->assertIsArray(Router::getRoutes());
        $this->assertCount(1, Router::getRoutes());
    }

    /** @test - Test if the route is added to the routes array */
    public function testGet()
    {
        Router::get('/test', fn () => 'test');

        $this->assertIsArray(Router::getRoutes());
        $this->assertCount(1, Router::getRoutes());
    }

    /** @test - Test if the route is added to the routes array */
    public function testPost()
    {
        Router::post('/test', fn () => 'test');

        $this->assertIsArray(Router::getRoutes());
        $this->assertCount(1, Router::getRoutes());
    }

    /** @test - Test if the route is added to the routes array */
    public function testPut()
    {
        Router::put('/test', fn () => 'test');

        $this->assertIsArray(Router::getRoutes());
        $this->assertCount(1, Router::getRoutes());
    }

    /** @test - test the json response from the router */
    public function testJsonResponse()
    {
        Router::get('/test', fn () => ['id' => 1, 'name' => 'John Doe', 'email' => '', 'phone' => '']);

        $this->assertIsArray(Router::getRoutes());
        $this->assertCount(1, Router::getRoutes());
    }

    /** @test - Test if the route is added to the routes array */
    public function addResource()
    {
        Router::resource('/tests', Test::class);
        $this->assertIsArray(Router::getRoutes());
        $this->assertCount(6, Router::getRoutes());
    }

    /** @test - error.php must HTML-encode the $message variable */
    public function testErrorViewEscapesXss()
    {
        $message = '<script>alert("xss")</script>';
        ob_start();
        view('error', ['message' => $message], __DIR__ . '/../../src/Garcia/views');
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    /** @test - template.php must HTML-encode the $name variable */
    public function testTemplateViewEscapesXss()
    {
        $name = '<script>alert("xss")</script>';
        ob_start();
        view('template', ['name' => $name], __DIR__ . '/../../src/Garcia/views');
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    /** @test - views must encode double quotes to prevent attribute injection (validates ENT_QUOTES) */
    public function testViewsEscapeQuotes()
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

    /** @test - views must handle empty string input without errors */
    public function testViewsHandleEmptyStrings()
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

    /** @test - views must handle null input safely by rendering an empty string */
    public function testViewsHandleNullSafely()
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

    /** @test - Test if clearRoutes empties the route array */
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
