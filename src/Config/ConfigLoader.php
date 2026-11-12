<?php

declare(strict_types=1);

namespace Core23\SizeLimit\Config;

use Core23\SizeLimit\Size\Compression;
use Core23\SizeLimit\Size\SizeParser;
use InvalidArgumentException;

final class ConfigLoader
{
    private const KEYS = ['name', 'path', 'limit', 'compression', 'no-dev'];

    /**
     * @return non-empty-list<Entry>
     */
    public static function load(mixed $raw): array
    {
        if (!is_array($raw) || $raw === [] || !array_is_list($raw)) {
            throw new InvalidArgumentException('"extra.size-limit" must be a non-empty list of entries.');
        }

        $entries = [];
        foreach ($raw as $index => $item) {
            $entries[] = self::entry($item, 'extra.size-limit['.$index.']');
        }

        return $entries;
    }

    private static function entry(mixed $item, string $where): Entry
    {
        if (!is_array($item)) {
            throw new InvalidArgumentException($where.' must be an object.');
        }

        $unknown = array_diff(array_keys($item), self::KEYS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('%s has unknown key(s): %s. Allowed: %s.', $where, implode(', ', $unknown), implode(', ', self::KEYS)));
        }

        $name = $item['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new InvalidArgumentException($where.'.name must be a non-empty string.');
        }

        $path = $item['path'] ?? null;
        $paths = is_string($path) ? [$path] : $path;
        if (!is_array($paths) || $paths === [] || !array_is_list($paths)) {
            throw new InvalidArgumentException($where.'.path must be a string or a non-empty list of strings.');
        }
        foreach ($paths as $p) {
            if (!is_string($p) || $p === '') {
                throw new InvalidArgumentException($where.'.path must contain only non-empty strings.');
            }
        }

        $limit = $item['limit'] ?? null;
        if (!is_string($limit) && !is_int($limit)) {
            throw new InvalidArgumentException($where.'.limit is required, e.g. "500 kB".');
        }
        try {
            $limitBytes = SizeParser::parse($limit);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException($where.'.limit: '.$e->getMessage(), 0, $e);
        }

        $compression = Compression::tryFrom((string) ($item['compression'] ?? 'none'));
        if ($compression === null) {
            throw new InvalidArgumentException($where.'.compression must be one of: none, gzip, brotli.');
        }

        $noDev = $item['no-dev'] ?? false;
        if (!is_bool($noDev)) {
            throw new InvalidArgumentException($where.'.no-dev must be a boolean.');
        }

        return new Entry($name, $paths, $limitBytes, $compression, $noDev);
    }
}
