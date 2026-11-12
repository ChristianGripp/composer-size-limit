<?php

declare(strict_types=1);

namespace Core23\SizeLimit\Size;

use RuntimeException;

enum Compression: string
{
    case None = 'none';
    case Gzip = 'gzip';
    case Brotli = 'brotli';

    /**
     * Size in bytes of a single file after applying this compression.
     */
    public function sizeOf(string $file): int
    {
        if ($this === self::None) {
            $size = filesize($file);
            if ($size === false) {
                throw new RuntimeException(sprintf('Cannot read size of "%s".', $file));
            }

            return $size;
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Cannot read "%s".', $file));
        }

        if ($this === self::Gzip) {
            $compressed = gzencode($contents, 9);
        } else {
            if (!function_exists('brotli_compress')) {
                throw new RuntimeException('Compression "brotli" requires the PHP extension ext-brotli (https://github.com/kjdev/php-ext-brotli).');
            }
            $compressed = brotli_compress($contents);
        }

        if ($compressed === false) {
            throw new RuntimeException(sprintf('Cannot compress "%s" with %s.', $file, $this->value));
        }

        return strlen($compressed);
    }
}
