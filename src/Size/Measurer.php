<?php

declare(strict_types=1);

namespace Core23\SizeLimit\Size;

use Core23\SizeLimit\Config\Entry;
use RuntimeException;

final class Measurer
{
    public function __construct(private readonly string $baseDir)
    {
    }

    /**
     * @param list<string> $excludedDirs absolute directories whose files are not counted
     */
    public function measure(Entry $entry, array $excludedDirs = []): int
    {
        $total = 0;
        $seen = [];

        foreach ($entry->paths as $pattern) {
            foreach (Glob::files($this->baseDir, $pattern) as $file) {
                if (isset($seen[$file]) || self::isExcluded($file, $excludedDirs)) {
                    continue;
                }
                $seen[$file] = true;
                $total += $entry->compression->sizeOf($file);
            }
        }

        if ($seen === []) {
            throw new RuntimeException(sprintf('Entry "%s": no files matched %s.', $entry->name, implode(', ', $entry->paths)));
        }

        return $total;
    }

    /**
     * @param list<string> $excludedDirs
     */
    private static function isExcluded(string $file, array $excludedDirs): bool
    {
        foreach ($excludedDirs as $dir) {
            if (str_starts_with($file, rtrim($dir, '/').'/')) {
                return true;
            }
        }

        return false;
    }
}
