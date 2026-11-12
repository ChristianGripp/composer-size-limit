<?php

declare(strict_types=1);

namespace Core23\SizeLimit\Size;

use InvalidArgumentException;
use LogicException;

/**
 * Converts between byte counts and human readable sizes.
 *
 * Decimal units (kB, MB, GB) use 1000, binary units (KiB, MiB, GiB) use 1024.
 */
final class SizeParser
{
    private const UNITS = [
        'b' => 1,
        'kb' => 1000,
        'mb' => 1000 ** 2,
        'gb' => 1000 ** 3,
        'kib' => 1024,
        'mib' => 1024 ** 2,
        'gib' => 1024 ** 3,
    ];

    public static function parse(string|int $value): int
    {
        if (is_int($value)) {
            if ($value < 0) {
                throw new InvalidArgumentException(sprintf('Invalid size "%d".', $value));
            }

            return $value;
        }

        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*([a-z]*)\s*$/i', $value, $m) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid size "%s". Expected e.g. "500 kB" or "2 MiB".', $value));
        }

        $unit = strtolower($m[2] === '' ? 'b' : $m[2]);
        if (!isset(self::UNITS[$unit])) {
            throw new InvalidArgumentException(sprintf('Unknown size unit "%s" in "%s". Use B, kB, MB, GB, KiB, MiB or GiB.', $m[2], $value));
        }

        $bytes = round((float) $m[1] * self::UNITS[$unit]);
        if ($bytes >= (float) PHP_INT_MAX) {
            throw new InvalidArgumentException(sprintf('Size "%s" is too large.', $value));
        }

        return (int) $bytes;
    }

    public static function format(int $bytes): string
    {
        if ($bytes < 1000) {
            return $bytes.' B';
        }

        foreach (['kB' => 1000, 'MB' => 1000 ** 2, 'GB' => 1000 ** 3] as $unit => $factor) {
            $value = round($bytes / $factor, 2);
            if ($value < 1000 || $unit === 'GB') {
                return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').' '.$unit;
            }
        }

        throw new LogicException('Unreachable.');
    }
}
