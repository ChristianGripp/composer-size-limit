<?php

declare(strict_types=1);

namespace Core23\SizeLimit\Tests\Command;

use Composer\Console\Application;
use Composer\Factory;
use Composer\IO\NullIO;
use Core23\SizeLimit\Command\SizeLimitCommand;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SizeLimitCommandTest extends TestCase
{
    private string $dir;
    private string $previousCwd;
    private string|false $previousHome;

    protected function setUp(): void
    {
        $tmp = realpath(sys_get_temp_dir());
        self::assertNotFalse($tmp);
        $this->dir = $tmp.'/size-limit-cmd-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/dist', 0777, true);
        file_put_contents($this->dir.'/dist/app.js', str_repeat('a', 5000));

        $cwd = getcwd();
        self::assertNotFalse($cwd);
        $this->previousCwd = $cwd;
        $this->previousHome = getenv('COMPOSER_HOME');
        putenv('COMPOSER_HOME='.$this->dir.'/home');
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->previousCwd);
        putenv($this->previousHome === false ? 'COMPOSER_HOME' : 'COMPOSER_HOME='.$this->previousHome);

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
     * @param array<string, mixed> $extra
     */
    private function tester(array $extra): CommandTester
    {
        file_put_contents($this->dir.'/composer.json', json_encode(['name' => 'test/app', 'extra' => $extra]));

        $command = new SizeLimitCommand();
        $command->setApplication(new Application());
        $command->setComposer((new Factory())->createComposer(new NullIO(), $this->dir.'/composer.json', true, $this->dir));

        return new CommandTester($command);
    }

    public function testPassesWithinLimit(): void
    {
        $tester = $this->tester(['size-limit' => [['name' => 'JS', 'path' => 'dist/*.js', 'limit' => '6 kB']]]);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('OK', $tester->getDisplay());
        self::assertStringContainsString('5 kB', $tester->getDisplay());
    }

    public function testFailsWhenLimitExceededAndPrintsJson(): void
    {
        $tester = $this->tester(['size-limit' => [
            ['name' => 'ok', 'path' => 'dist', 'limit' => '6 kB'],
            ['name' => 'big', 'path' => 'dist', 'limit' => '1 kB'],
        ]]);

        self::assertSame(Command::FAILURE, $tester->execute(['--json' => true]));

        $data = json_decode($tester->getDisplay(), true);
        self::assertIsArray($data);
        self::assertFalse($data['passed']);
        self::assertSame(
            [['ok', 5000, 6000, true], ['big', 5000, 1000, false]],
            array_map(static fn (array $r): array => [$r['name'], $r['size'], $r['limit'], $r['passed']], $data['results']),
        );
    }

    public function testMissingConfigurationThrows(): void
    {
        $tester = $this->tester([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('extra.size-limit');
        $tester->execute([]);
    }

    public function testNoDevExcludesDevPackages(): void
    {
        mkdir($this->dir.'/vendor/composer', 0777, true);
        mkdir($this->dir.'/vendor/acme/prod', 0777, true);
        mkdir($this->dir.'/vendor/acme/dev', 0777, true);
        file_put_contents($this->dir.'/vendor/acme/prod/p.php', str_repeat('p', 100));
        file_put_contents($this->dir.'/vendor/acme/dev/d.php', str_repeat('d', 900));
        file_put_contents($this->dir.'/vendor/composer/installed.json', json_encode([
            'packages' => [
                ['name' => 'acme/prod', 'version' => '1.0.0', 'install-path' => '../acme/prod'],
                ['name' => 'acme/dev', 'version' => '1.0.0', 'install-path' => '../acme/dev'],
            ],
            'dev-package-names' => ['acme/dev'],
        ]));

        $tester = $this->tester(['size-limit' => [
            ['name' => 'all', 'path' => 'vendor/acme', 'limit' => '10 kB'],
            ['name' => 'prod', 'path' => 'vendor/acme', 'limit' => '10 kB', 'no-dev' => true],
        ]]);
        $tester->execute(['--json' => true]);

        $data = json_decode($tester->getDisplay(), true);
        self::assertIsArray($data);
        self::assertSame([1000, 100], array_column($data['results'], 'size'));

        $tester->execute(['--json' => true, '--no-dev' => true]);
        $data = json_decode($tester->getDisplay(), true);
        self::assertIsArray($data);
        self::assertSame([100, 100], array_column($data['results'], 'size'));
    }
}
