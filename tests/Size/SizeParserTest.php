<?php

declare(strict_types=1);

namespace Core23\SizeLimit\Tests\Size;

use Core23\SizeLimit\Size\SizeParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SizeParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string|int, int}>
     */
    public static function validProvider(): iterable
    {
        yield 'plain int' => [123, 123];
        yield 'bytes' => ['100', 100];
        yield 'B' => ['100 B', 100];
        yield 'kB' => ['500 kB', 500_000];
        yield 'no space' => ['2MB', 2_000_000];
        yield 'case-insensitive' => ['1 KB', 1000];
        yield 'KiB' => ['1 KiB', 1024];
        yield 'MiB' => ['2 MiB', 2_097_152];
        yield 'decimal' => ['1.5 kB', 1500];
    }

    #[DataProvider('validProvider')]
    public function testParse(string|int $input, int $expected): void
    {
        self::assertSame($expected, SizeParser::parse($input));
    }

    #[DataProvider('invalidProvider')]
    public function testParseRejectsInvalid(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        SizeParser::parse($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['big'];
        yield 'unknown unit' => ['5 XB'];
        yield 'negative' => ['-5 kB'];
        yield 'overflow' => ['99999999999999999999 GB'];
        yield 'exponent' => ['1e3'];
    }

    public function testFormat(): void
    {
        self::assertSame('999 B', SizeParser::format(999));
        self::assertSame('1 kB', SizeParser::format(1000));
        self::assertSame('1.5 kB', SizeParser::format(1500));
        self::assertSame('2 MB', SizeParser::format(2_000_000));
        self::assertSame('0 B', SizeParser::format(0));
        self::assertSame('1 MB', SizeParser::format(999_999), 'rounding must not yield "1000 kB"');
        self::assertSame('1.5 GB', SizeParser::format(1_500_000_000));
    }
}
