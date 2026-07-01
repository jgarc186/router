<?php

namespace Test\Unit;

use Garcia\Helpers;
use PHPUnit\Framework\TestCase;

class GarciaHelpersTest extends TestCase
{
    // --- validateRedirectPath / redirect ---

    public function testRedirectRejectsHeaderInjection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Helpers::redirect("/dashboard\r\nSet-Cookie: malicious=value");
    }

    public function testRedirectRejectsAbsoluteUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Helpers::redirect('https://evil.com/phish');
    }

    public function testRedirectRejectsProtocolRelativeUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Helpers::redirect('//evil.com/phish');
    }

    public function testValidateRedirectPathAcceptsValidPath(): void
    {
        try {
            Helpers::validateRedirectPath('/dashboard');
        } catch (\InvalidArgumentException $e) {
            $this->fail('Helpers::validateRedirectPath() should not reject a valid relative path: ' . $e->getMessage());
        }

        $this->addToAssertionCount(1);
    }

    // --- view ---

    public function testViewRejectsPathTraversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('path traversal sequences are not allowed');
        Helpers::view('../secret', []);
    }

    public function testViewRejectsNullByte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('null bytes are not allowed');
        Helpers::view("home\0", []);
    }

    public function testViewRendersValidView(): void
    {
        $tmpDir   = sys_get_temp_dir();
        $viewFile = $tmpDir . DIRECTORY_SEPARATOR . 'test_garcia_helpers_view_' . uniqid() . '.php';
        file_put_contents($viewFile, '<?php echo "hello-view"; ?>');

        $viewName = basename($viewFile, '.php');

        ob_start();
        try {
            Helpers::view($viewName, [], $tmpDir);
        } finally {
            $output = ob_get_clean();
            @unlink($viewFile);
        }

        $this->assertSame('hello-view', $output);
    }

    // --- backward-compatibility layer ---

    public function testGlobalFunctionsDelegateToHelpersClass(): void
    {
        $this->assertTrue(function_exists('redirect'));
        $this->assertTrue(function_exists('view'));
        $this->assertTrue(function_exists('validateRedirectPath'));

        try {
            validateRedirectPath('/dashboard');
        } catch (\InvalidArgumentException $e) {
            $this->fail('Global validateRedirectPath() should not reject a valid relative path: ' . $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        redirect('https://evil.com/phish');
    }
}
