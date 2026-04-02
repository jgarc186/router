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
        view("foo\0../../etc/passwd", []);
    }

    public function testViewRejectsDoubleDot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        view('../../etc/passwd', []);
    }

    public function testViewRejectsParentDirSegment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        view('../secret', []);
    }

    public function testViewRejectsNullByteAlone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        view("home\0", []);
    }

    // --- view() valid path ---

    public function testViewRendersValidView(): void
    {
        $tmpDir = sys_get_temp_dir();
        $viewFile = $tmpDir . DIRECTORY_SEPARATOR . 'test_valid_view.php';
        file_put_contents($viewFile, '<?php ?>');

        try {
            view('test_valid_view', [], $tmpDir);
        } catch (\InvalidArgumentException $e) {
            $this->fail('view() should not reject a valid view path: ' . $e->getMessage());
        } finally {
            @unlink($viewFile);
        }

        $this->addToAssertionCount(1);
    }
}
