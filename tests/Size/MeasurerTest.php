<?php

declare(strict_types=1);

namespace Core23\SizeLimit\Tests\Size;

use Core23\SizeLimit\Config\Entry;
use Core23\SizeLimit\Size\Compression;
use Core23\SizeLimit\Size\DevPackages;
use Core23\SizeLimit\Size\Measurer;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class MeasurerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $dir = realpath(sys_get_temp_dir());
        self::assertNotFalse($dir);
        $this->dir = $dir.'/size-limit-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/dist/sub', 0777, true);
        file_put_contents($this->dir.'/dist/a.js', str_repeat('a', 100));
        file_put_contents($this->dir.'/dist/b.css', str_repeat('b', 50));
        file_put_contents($this->dir.'/dist/sub/c.js', str_repeat('c', 10));
    }

    protected function tearDown(): void
    {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            /** @var SplFileInfo $f */
            $f->isDir() && !$f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
    }

    /**
     * @param list<string> $paths
     */
    private function entry(array $paths, Compression $c = Compression::None): Entry
    {
        return new Entry('t', $paths, 1, $c, false);
    }

    public function testSingleFile(): void
    {
        self::assertSame(100, (new Measurer($this->dir))->measure($this->entry(['dist/a.js'])));
    }

    public function testDirectoryIsRecursive(): void
    {
        self::assertSame(160, (new Measurer($this->dir))->measure($this->entry(['dist'])));
    }

    public function testGlobs(): void
    {
        $m = new Measurer($this->dir);
        self::assertSame(100, $m->measure($this->entry(['dist/*.js'])));
        self::assertSame(110, $m->measure($this->entry(['dist/**/*.js'])));
        self::assertSame(150, $m->measure($this->entry(['dist/*.{js,css}'])));
    }

    public function testOverlappingPathsAreCountedOnce(): void
    {
        self::assertSame(160, (new Measurer($this->dir))->measure($this->entry(['dist', 'dist/a.js'])));
    }

    public function testGzip(): void
    {
        $expected = strlen((string) gzencode(str_repeat('a', 100), 9));
        self::assertSame($expected, (new Measurer($this->dir))->measure($this->entry(['dist/a.js'], Compression::Gzip)));
    }

    public function testExcludedDirs(): void
    {
        self::assertSame(150, (new Measurer($this->dir))->measure($this->entry(['dist']), [$this->dir.'/dist/sub']));
    }

    public function testNoMatchThrows(): void
    {
        $this->expectException(RuntimeException::class);
        (new Measurer($this->dir))->measure($this->entry(['dist/*.png']));
    }

    public function testDevPackagesInstallDirs(): void
    {
        mkdir($this->dir.'/vendor/composer', 0777, true);
        mkdir($this->dir.'/vendor/acme/prod', 0777, true);
        mkdir($this->dir.'/vendor/acme/dev', 0777, true);
        file_put_contents($this->dir.'/vendor/composer/installed.json', json_encode([
            'packages' => [
                ['name' => 'acme/prod', 'install-path' => '../acme/prod'],
                ['name' => 'acme/dev', 'install-path' => '../acme/dev'],
            ],
            'dev-package-names' => ['acme/dev'],
        ]));

        self::assertSame([$this->dir.'/vendor/acme/dev'], DevPackages::installDirs($this->dir.'/vendor'));
    }
}
