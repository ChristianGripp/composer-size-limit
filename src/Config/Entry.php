<?php

declare(strict_types=1);

namespace Core23\SizeLimit\Config;

use Core23\SizeLimit\Size\Compression;

final readonly class Entry
{
    /**
     * @param list<string> $paths files, directories or globs relative to the project root
     * @param int                    $limit maximum size in bytes
     */
    public function __construct(
        public string $name,
        public array $paths,
        public int $limit,
        public Compression $compression,
        public bool $noDev,
    ) {
    }
}
