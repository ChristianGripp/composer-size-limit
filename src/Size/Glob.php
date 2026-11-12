<?php

declare(strict_types=1);

namespace Core23\SizeLimit\Size;

use FilesystemIterator;
use Generator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Resolves a file, directory or glob to regular files (symlinks are skipped).
 *
 * Supported glob syntax: "*", "?", "**" (any depth) and "{a,b}".
 * A plain directory matches all files below it. A glob matches files only.
 */
final class Glob
{
    /**
     * @return Generator<int, string> absolute file paths
     */
    public static function files(string $baseDir, string $pattern): Generator
    {
        $absolute = str_starts_with($pattern, '/') ? $pattern : rtrim($baseDir, '/').'/'.$pattern;

        $segments = explode('/', $absolute);
        $static = [];
        while ($segments !== [] && strpbrk($segments[0], '*?{') === false) {
            $static[] = array_shift($segments);
        }
        $root = implode('/', $static);
        if ($root === '') {
            $root = '/';
        }

        $regex = $segments === [] ? null : self::toRegex(implode('/', $segments));

        if (is_file($root) && !is_link($root)) {
            if ($regex === null) {
                yield $root;
            }

            return;
        }
        if (!is_dir($root)) {
            return;
        }

        $prefix = rtrim($root, '/').'/';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $path = $file->getPathname();
            if ($regex === null || preg_match($regex, substr($path, strlen($prefix))) === 1) {
                yield $path;
            }
        }
    }

    private static function toRegex(string $glob): string
    {
        $regex = '';
        $depth = 0;
        $length = strlen($glob);

        for ($i = 0; $i < $length; ++$i) {
            $char = $glob[$i];
            if ($char === '*') {
                if (($glob[$i + 1] ?? '') === '*') {
                    if (($glob[$i + 2] ?? '') === '/') {
                        $regex .= '(?:.*/)?';
                        $i += 2;
                    } else {
                        $regex .= '.*';
                        ++$i;
                    }
                } else {
                    $regex .= '[^/]*';
                }
            } elseif ($char === '?') {
                $regex .= '[^/]';
            } elseif ($char === '{') {
                ++$depth;
                $regex .= '(?:';
            } elseif ($char === '}' && $depth > 0) {
                --$depth;
                $regex .= ')';
            } elseif ($char === ',' && $depth > 0) {
                $regex .= '|';
            } else {
                $regex .= preg_quote($char, '~');
            }
        }

        if ($depth !== 0) {
            throw new InvalidArgumentException(sprintf('Unbalanced "{" in glob "%s".', $glob));
        }

        return '~^'.$regex.'$~';
    }
}
