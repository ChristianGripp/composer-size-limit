<?php

declare(strict_types=1);

namespace Core23\SizeLimit\Tests\Config;

use Core23\SizeLimit\Config\ConfigLoader;
use Core23\SizeLimit\Size\Compression;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function testLoadsEntries(): void
    {
        $entries = ConfigLoader::load([
            ['name' => 'A', 'path' => 'dist/*.js', 'limit' => '1 kB'],
            ['name' => 'B', 'path' => ['vendor'], 'limit' => 2000, 'compression' => 'gzip', 'no-dev' => true],
        ]);

        self::assertCount(2, $entries);
        self::assertSame(['dist/*.js'], $entries[0]->paths);
        self::assertSame(1000, $entries[0]->limit);
        self::assertSame(Compression::None, $entries[0]->compression);
        self::assertFalse($entries[0]->noDev);
        self::assertSame(Compression::Gzip, $entries[1]->compression);
        self::assertTrue($entries[1]->noDev);
    }

    #[DataProvider('invalidProvider')]
    public function testRejectsInvalid(mixed $raw): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConfigLoader::load($raw);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'not a list' => [['name' => 'A']];
        yield 'empty' => [[]];
        yield 'missing name' => [[['path' => 'a', 'limit' => '1 kB']]];
        yield 'missing path' => [[['name' => 'A', 'limit' => '1 kB']]];
        yield 'empty path list' => [[['name' => 'A', 'path' => [], 'limit' => '1 kB']]];
        yield 'missing limit' => [[['name' => 'A', 'path' => 'a']]];
        yield 'bad limit' => [[['name' => 'A', 'path' => 'a', 'limit' => 'lots']]];
        yield 'bad compression' => [[['name' => 'A', 'path' => 'a', 'limit' => '1 kB', 'compression' => 'zip']]];
        yield 'bad no-dev' => [[['name' => 'A', 'path' => 'a', 'limit' => '1 kB', 'no-dev' => 'yes']]];
        yield 'unknown key' => [[['name' => 'A', 'path' => 'a', 'limit' => '1 kB', 'limt' => '2']]];
    }
}
