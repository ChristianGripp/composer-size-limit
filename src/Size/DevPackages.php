<?php

declare(strict_types=1);

namespace Core23\SizeLimit\Size;

use RuntimeException;

final class DevPackages
{
    /**
     * Install directories of packages that are only required by require-dev.
     *
     * @return list<string>
     */
    public static function installDirs(string $vendorDir): array
    {
        $file = $vendorDir.'/composer/installed.json';
        if (!is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Cannot parse "%s".', $file));
        }

        $dev = array_flip(array_filter((array) ($data['dev-package-names'] ?? []), is_string(...)));
        $dirs = [];
        foreach ((array) ($data['packages'] ?? []) as $package) {
            if (!is_array($package) || !isset($package['name'], $package['install-path'])) {
                continue;
            }
            if (!is_string($package['name']) || !is_string($package['install-path']) || !isset($dev[$package['name']])) {
                continue;
            }
            $dir = realpath($vendorDir.'/composer/'.$package['install-path']);
            if ($dir !== false) {
                $dirs[] = $dir;
            }
        }

        return $dirs;
    }
}
