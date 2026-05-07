<?php

namespace Test\Unit;

use Garcia\Router;
use PHPUnit\Framework\TestCase;

class MockPhpInputStream
{
    public static string $input = '';
    public $context;
    private int $position = 0;

    public function stream_open($path, $mode, $options, &$opened_path): bool
    {
        $this->position = 0;
        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr(self::$input, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen(self::$input);
    }

    public function stream_stat(): array
    {
        return [];
    }
}

class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        Router::clearRoutes();
        $_POST = [];
        $_REQUEST = [];
    }

    protected function tearDown(): void
    {
        $this->restorePhpStreamWrapper();
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
        $_POST = [];
        $_REQUEST = [];
    }

    private function mockPhpInput(string $input): void
    {
        MockPhpInputStream::$input = $input;
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', MockPhpInputStream::class);
    }

    private function restorePhpStreamWrapper(): void
    {
        $wrappers = stream_get_wrappers();
        if (in_array('php', $wrappers, true)) {
            stream_wrapper_unregister('php');
        }
        stream_wrapper_restore('php');
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

    public function testRunStripsQueryStringForStaticRoute(): void
    {
        Router::get('/health', function () {
            return 'ok';
        });
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/health?foo=bar';
        ob_start();
        Router::run();
        $output = ob_get_clean();

        $this->assertSame('ok', $output);
    }

    public function testRunStripsQueryStringForDynamicRoute(): void
    {
        Router::get('/users/:id', function (array $params) {
            return json_encode($params);
        });
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/users/42?include=posts';
        ob_start();
        Router::run();
        $output = ob_get_clean();

        $this->assertSame('{"id":"42"}', $output);
    }

    public function testRunMatchesStaticRouteWithTrailingSlashAndQueryString(): void
    {
        Router::get('/health', function () {
            return 'ok';
        });
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/health/?foo=bar';
        ob_start();
        Router::run();
        $output = ob_get_clean();

        $this->assertSame('ok', $output);
    }

    public function testRunMatchesDynamicRouteWithTrailingSlashAndQueryString(): void
    {
        Router::get('/users/:id', function (array $params) {
            return json_encode($params);
        });
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users/42/?include=posts';
        ob_start();
        Router::run();
        $output = ob_get_clean();

        $this->assertSame('{"id":"42"}', $output);
    }

    public function testRunTreatsQueryOnlyRequestUriAsRootPath(): void
    {
        Router::get('/', function () {
            return 'root';
        });
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '?foo=bar';
        ob_start();
        Router::run();
        $output = ob_get_clean();

        $this->assertSame('root', $output);
    }

    public function testRunIgnoresComplexQueryStringDuringRouteMatch(): void
    {
        Router::get('/search', function () {
            return 'ok';
        });
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/search?q=router&tag=php&tag=tests&empty=';

        ob_start();
        Router::run();
        $output = ob_get_clean();

        $this->assertSame('ok', $output);
    }

    public function testRunPreservesEncodedPathWhileIgnoringQueryString(): void
    {
        Router::get('/files/a%2Fb', function () {
            return 'encoded';
        });
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/files/a%2Fb?download=1';

        ob_start();
        Router::run();
        $output = ob_get_clean();

        $this->assertSame('encoded', $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testRunDoesNotMatchDynamicRouteWhenUriContainsEmptySegment(): void
    {
        Router::get('/users/:id', function (array $params) {
            return json_encode($params);
        });
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users//42?include=posts';
        ob_start();
        Router::run();
        $output = ob_get_clean();

        $this->assertStringContainsString('IMPORTANT:', $output);
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

    /**
     * @runInSeparateProcess
     */
    public function testHandleNotFoundRendersFromLibraryDirWhenCwdIsProjectRoot(): void
    {
        $originalCwd = getcwd();
        chdir(dirname(__DIR__, 2));

        ob_start();
        Router::handleRequest('GET', '/nonexistent');
        $output = ob_get_clean();

        chdir($originalCwd);

        $this->assertStringContainsString('IMPORTANT:', $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleNotFoundRendersFromLibraryDirWhenCwdIsUnrelated(): void
    {
        $originalCwd = getcwd();
        chdir(sys_get_temp_dir());

        ob_start();
        Router::handleRequest('GET', '/nonexistent');
        $output = ob_get_clean();

        chdir($originalCwd);

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

    public function testOnlyFirstMatchingRouteExecutesWhenAnyAndGetOverlap(): void
    {
        Router::any('/test', fn () => 'from-any');
        Router::get('/test', fn () => 'from-get');

        ob_start();
        Router::handleRequest('GET', '/test');
        $output = ob_get_clean();

        $this->assertSame('from-any', $output);
    }

    public function testOnlyFirstMatchingRouteExecutesWithTwoGetRoutes(): void
    {
        Router::get('/dup', fn () => 'first');
        Router::get('/dup', fn () => 'second');

        ob_start();
        Router::handleRequest('GET', '/dup');
        $output = ob_get_clean();

        $this->assertSame('first', $output);
    }

    public function testFirstDeclaredDynamicRouteBeatsLaterStaticRouteForSamePath(): void
    {
        Router::get('/users/:id', fn () => 'dynamic');
        Router::get('/users/list', fn () => 'static');

        ob_start();
        Router::handleRequest('GET', '/users/list');
        $output = ob_get_clean();

        $this->assertSame('dynamic', $output);
    }

    public function testFormEncodedBodyParsedFromPost(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = ['name' => 'Alice', 'age' => '30'];

        Router::post('/submit', fn ($params) => "{$params['name']}:{$params['age']}");

        ob_start();
        Router::handleRequest('POST', '/submit');
        $output = ob_get_clean();

        $this->assertSame('Alice:30', $output);

        unset($_SERVER['CONTENT_TYPE']);
        $_POST = [];
    }

    public function testMultipartFormDataBodyParsedFromPost(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=----boundary';
        $_POST = ['file_name' => 'upload.txt', 'size' => '1024'];

        Router::post('/upload', fn ($params) => "{$params['file_name']}:{$params['size']}");

        ob_start();
        Router::handleRequest('POST', '/upload');
        $output = ob_get_clean();

        $this->assertSame('upload.txt:1024', $output);

        unset($_SERVER['CONTENT_TYPE']);
        $_POST = [];
    }

    public function testUnknownContentTypeFallsBackToPost(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'text/plain';
        $_POST = ['fallback' => 'yes'];

        Router::post('/fallback', fn ($params) => $params['fallback'] ?? 'missing');

        ob_start();
        Router::handleRequest('POST', '/fallback');
        $output = ob_get_clean();

        $this->assertSame('yes', $output);

        unset($_SERVER['CONTENT_TYPE']);
        $_POST = [];
    }

    public function testJsonBodyParsedFromPost(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $this->mockPhpInput('{"name":"Alice","age":30}');

        Router::post('/json-post', fn ($params) => "{$params['name']}:{$params['age']}");

        ob_start();
        Router::handleRequest('POST', '/json-post');
        $output = ob_get_clean();

        $this->assertSame('Alice:30', $output);
    }

    public function testJsonBodyParsedFromPut(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $this->mockPhpInput('{"title":"Draft","published":false}');

        Router::put('/json-put', fn ($params) => "{$params['title']}:" . ($params['published'] ? '1' : '0'));

        ob_start();
        Router::handleRequest('PUT', '/json-put');
        $output = ob_get_clean();

        $this->assertSame('Draft:0', $output);
    }

    public function testJsonBodyParsedFromPatch(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $this->mockPhpInput('{"status":"active"}');

        Router::patch('/json-patch', fn ($params) => $params['status']);

        ob_start();
        Router::handleRequest('PATCH', '/json-patch');
        $output = ob_get_clean();

        $this->assertSame('active', $output);
    }

    public function testFormEncodedBodyParsedFromPut(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = ['name' => 'Taylor', 'city' => 'Boston'];

        Router::put('/form-put', fn ($params) => "{$params['name']}:{$params['city']}");

        ob_start();
        Router::handleRequest('PUT', '/form-put');
        $output = ob_get_clean();

        $this->assertSame('Taylor:Boston', $output);
    }

    public function testFormEncodedBodyParsedFromPatch(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = ['role' => 'admin'];

        Router::patch('/form-patch', fn ($params) => $params['role']);

        ob_start();
        Router::handleRequest('PATCH', '/form-patch');
        $output = ob_get_clean();

        $this->assertSame('admin', $output);
    }

    public function testBodyParamsMergeWithRouteParams(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $this->mockPhpInput('{"status":"done"}');

        Router::patch('/tasks/:id', fn ($params) => "{$params['id']}:{$params['status']}");

        ob_start();
        Router::handleRequest('PATCH', '/tasks/42');
        $output = ob_get_clean();

        $this->assertSame('42:done', $output);
    }

    // matchPath() coverage

    public function testMatchPathExtractsMultipleParams(): void
    {
        Router::get('/users/:id/posts/:postId', fn ($params) => json_encode($params));

        ob_start();
        Router::handleRequest('GET', '/users/42/posts/7');
        $output = ob_get_clean();

        $this->assertSame('{"id":"42","postId":"7"}', $output);
    }

    public function testMatchPathMatchesUriWithTrailingSlash(): void
    {
        Router::get('/users/:id', fn ($params) => $params['id']);

        ob_start();
        Router::handleRequest('GET', '/users/42/');
        $output = ob_get_clean();

        $this->assertSame('42', $output);
    }

    public function testMatchPathMatchesRouteWithTrailingSlash(): void
    {
        Router::get('/users/:id/', fn ($params) => $params['id']);

        ob_start();
        Router::handleRequest('GET', '/users/42');
        $output = ob_get_clean();

        $this->assertSame('42', $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testMatchPathEmptySegmentDoesNotMatch(): void
    {
        Router::get('/users/:id', fn ($params) => $params['id']);

        ob_start();
        Router::handleRequest('GET', '/users//');
        $output = ob_get_clean();

        $this->assertStringContainsString('IMPORTANT:', $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testMatchPathTooManySegmentsDoesNotMatch(): void
    {
        Router::get('/users/:id', fn ($params) => $params['id']);

        ob_start();
        Router::handleRequest('GET', '/users/42/extra');
        $output = ob_get_clean();

        $this->assertStringContainsString('IMPORTANT:', $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testMatchPathTooFewSegmentsDoesNotMatch(): void
    {
        Router::get('/users/:id/posts/:postId', fn ($params) => json_encode($params));

        ob_start();
        Router::handleRequest('GET', '/users/42');
        $output = ob_get_clean();

        $this->assertStringContainsString('IMPORTANT:', $output);
    }

    /**
     * @runInSeparateProcess
     */
    public function testMatchPathStaticSegmentMismatchDoesNotMatch(): void
    {
        Router::get('/users/profile', fn () => 'profile');

        ob_start();
        Router::handleRequest('GET', '/users/settings');
        $output = ob_get_clean();

        $this->assertStringContainsString('IMPORTANT:', $output);
    }

    public function testMatchPathParamAtFirstPosition(): void
    {
        Router::get('/:id/profile', fn ($params) => $params['id']);

        ob_start();
        Router::handleRequest('GET', '/42/profile');
        $output = ob_get_clean();

        $this->assertSame('42', $output);
    }

    public function testMiddlewareRunsBeforeHandlerForMatchingRoute(): void
    {
        $events = [];

        Router::get('/secure', function () use (&$events) {
            $events[] = 'handler';
            return 'ok';
        })->middleware(function () use (&$events) {
            $events[] = 'middleware';
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/secure';

        ob_start();
        Router::run();
        $output = ob_get_clean();

        $this->assertSame('ok', $output);
        $this->assertSame(['middleware', 'handler'], $events);
    }

    public function testMiddlewareDoesNotRunForNonMatchingMethodOrPath(): void
    {
        $middlewareCalls = 0;

        Router::get('/secure', fn () => 'ok')
            ->middleware(function () use (&$middlewareCalls) {
                $middlewareCalls++;
            });

        Router::handleMiddleware('POST', '/secure');
        Router::handleMiddleware('GET', '/other');

        $this->assertSame(0, $middlewareCalls);
    }

    public function testMiddlewareOrderIsPreservedPerRoute(): void
    {
        $events = [];

        Router::get('/ordered', function () use (&$events) {
            $events[] = 'handler';
            return 'done';
        })
            ->middleware(function () use (&$events) {
                $events[] = 'first';
            })
            ->middleware(function () use (&$events) {
                $events[] = 'second';
            });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/ordered';

        ob_start();
        Router::run();
        ob_end_clean();

        $this->assertSame(['first', 'second', 'handler'], $events);
    }

    public function testMiddlewareScopesToMatchedRouteOnly(): void
    {
        $events = [];

        Router::get('/a', function () use (&$events) {
            $events[] = 'handler-a';
            return 'a';
        })->middleware(function () use (&$events) {
            $events[] = 'mw-a';
        });

        Router::get('/b', function () use (&$events) {
            $events[] = 'handler-b';
            return 'b';
        })->middleware(function () use (&$events) {
            $events[] = 'mw-b';
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/b';

        ob_start();
        Router::run();
        $output = ob_get_clean();

        $this->assertSame('b', $output);
        $this->assertSame(['mw-b', 'handler-b'], $events);
    }

    public function testHandleRequestBubblesHandlerExceptions(): void
    {
        Router::get('/explode', function () {
            throw new \RuntimeException('handler failed');
        });

        $level = ob_get_level();
        ob_start();
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('handler failed');
            Router::handleRequest('GET', '/explode');
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    public function testRunBubblesMiddlewareExceptionsBeforeHandlerExecution(): void
    {
        Router::get('/secure', function () {
            return 'never';
        })->middleware(function () {
            throw new \RuntimeException('middleware failed');
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/secure';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('middleware failed');
        Router::run();
    }

    public function testRedirectRejectsInvalidAbsoluteUrlPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        redirect('https://example.com');
    }
}
