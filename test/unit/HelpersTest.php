<?php

namespace Test\Unit;

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    // --- Header injection ---

    public function testRedirectRejectsCarriageReturnLineFeed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        redirect("/dashboard\r\nSet-Cookie: malicious=value");
    }

    public function testRedirectRejectsLineFeed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        redirect("/dashboard\nSet-Cookie: malicious=value");
    }

    public function testRedirectRejectsCarriageReturn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        redirect("/dashboard\rSet-Cookie: malicious=value");
    }

    // --- Open redirect ---

    public function testRedirectRejectsAbsoluteHttpUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        redirect("http://evil.com/phish");
    }

    public function testRedirectRejectsAbsoluteHttpsUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        redirect("https://evil.com/phish");
    }

    public function testRedirectRejectsProtocolRelativeUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        redirect("//evil.com/phish");
    }

    public function testRedirectRejectsPathWithoutLeadingSlash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        redirect("dashboard");
    }

    // --- Valid paths (exceptions must NOT be thrown) ---

    public function testRedirectAcceptsSimpleRelativePath(): void
    {
        // Wrap in try/catch to ignore the header()/exit that cannot run in CLI.
        // The important thing is no InvalidArgumentException is thrown beforehand.
        try {
            redirect("/dashboard");
        } catch (\InvalidArgumentException $e) {
            $this->fail("redirect() should not reject a valid relative path: " . $e->getMessage());
        } catch (\Throwable $e) {
            // header() or exit may throw in test context — that is acceptable.
        }

        $this->addToAssertionCount(1);
    }

    public function testRedirectAcceptsRelativePathWithQuery(): void
    {
        try {
            redirect("/search?q=hello&page=2");
        } catch (\InvalidArgumentException $e) {
            $this->fail("redirect() should not reject a valid relative path: " . $e->getMessage());
        } catch (\Throwable $e) {
            // Acceptable — header/exit side-effects in CLI context.
        }

        $this->addToAssertionCount(1);
    }

    // --- view() path traversal ---

    public function testViewRejectsNullByte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('null bytes are not allowed');
        view("foo\0../../etc/passwd", []);
    }

    public function testViewRejectsNullByteAlone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('null bytes are not allowed');
        view("home\0", []);
    }

    public function testViewRejectsDoubleDot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('path traversal sequences are not allowed');
        view('../../etc/passwd', []);
    }

    public function testViewRejectsParentDirSegment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('path traversal sequences are not allowed');
        view('../secret', []);
    }

    public function testViewRejectsSymlinkEscape(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Symlink creation requires elevated privileges on Windows.');
        }

        $base      = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_symlink_' . uniqid();
        $viewsDir  = $base . DIRECTORY_SEPARATOR . 'views';
        $siblingDir = $base . DIRECTORY_SEPARATOR . 'sibling';
        mkdir($viewsDir, 0777, true);
        mkdir($siblingDir, 0777, true);

        $outsideFile = $siblingDir . DIRECTORY_SEPARATOR . 'secret.php';
        file_put_contents($outsideFile, '<?php echo "secret"; ?>');

        $symlinkPath = $viewsDir . DIRECTORY_SEPARATOR . 'escaped_view.php';
        symlink($outsideFile, $symlinkPath);

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('outside the allowed views directory');
            view('escaped_view', [], $viewsDir);
        } finally {
            @unlink($symlinkPath);
            @unlink($outsideFile);
            @rmdir($siblingDir);
            @rmdir($viewsDir);
            @rmdir($base);
        }
    }

    public function testViewAllowsDoubleDotWithinFilename(): void
    {
        // 'view..backup' contains .. but not as a path segment — must not be rejected.
        $tmpDir   = sys_get_temp_dir();
        $viewFile = $tmpDir . DIRECTORY_SEPARATOR . 'view..backup.php';
        file_put_contents($viewFile, '<?php echo "ok"; ?>');

        ob_start();
        try {
            view('view..backup', [], $tmpDir);
        } finally {
            $output = ob_get_clean();
            @unlink($viewFile);
        }

        $this->assertSame('ok', $output);
    }

    public function testViewElementCannotOverwriteInternalPath(): void
    {
        $tmpDir   = sys_get_temp_dir();
        $viewFile = $tmpDir . DIRECTORY_SEPARATOR . 'test_safe_view_' . uniqid() . '.php';
        file_put_contents($viewFile, '<?php echo "safe"; ?>');

        $viewName = basename($viewFile, '.php');

        ob_start();
        try {
            // Attempt to overwrite $__viewPath via extract(); EXTR_SKIP must prevent this.
            view($viewName, ['__viewPath' => '/etc/passwd'], $tmpDir);
        } finally {
            $output = ob_get_clean();
            @unlink($viewFile);
        }

        $this->assertSame('safe', $output);
    }

    public function testViewRejectsNonexistentBaseDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('base directory does not exist');
        view('home', [], '/nonexistent/path/that/cannot/exist');
    }

    // --- view() valid paths ---

    public function testViewRendersValidView(): void
    {
        $tmpDir   = sys_get_temp_dir();
        $viewFile = $tmpDir . DIRECTORY_SEPARATOR . 'test_valid_view_' . uniqid() . '.php';
        file_put_contents($viewFile, '<?php echo "hello-view"; ?>');

        $viewName = basename($viewFile, '.php');

        ob_start();
        try {
            view($viewName, [], $tmpDir);
        } finally {
            $output = ob_get_clean();
            @unlink($viewFile);
        }

        $this->assertSame('hello-view', $output);
    }

    public function testViewRendersSubdirectoryView(): void
    {
        $tmpDir   = sys_get_temp_dir();
        $viewsDir = $tmpDir . DIRECTORY_SEPARATOR . 'test_views_sub_' . uniqid();
        $subDir   = $viewsDir . DIRECTORY_SEPARATOR . 'admin';
        mkdir($subDir, 0777, true);

        $viewFile = $subDir . DIRECTORY_SEPARATOR . 'dashboard.php';
        file_put_contents($viewFile, '<?php echo "admin-dashboard"; ?>');

        ob_start();
        try {
            view('admin/dashboard', [], $viewsDir);
        } finally {
            $output = ob_get_clean();
            @unlink($viewFile);
            @rmdir($subDir);
            @rmdir($viewsDir);
        }

        $this->assertSame('admin-dashboard', $output);
    }
}
