<?php

declare(strict_types=1);

namespace Core23\SizeLimit\Command;

use Composer\Command\BaseCommand;
use Composer\Factory;
use Core23\SizeLimit\Config\ConfigLoader;
use Core23\SizeLimit\Size\DevPackages;
use Core23\SizeLimit\Size\Measurer;
use Core23\SizeLimit\Size\SizeParser;
use RuntimeException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SizeLimitCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('size-limit')
            ->setDescription('Checks the sizes configured in "extra.size-limit" against their limits.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the result as JSON')
            ->addOption('no-dev', null, InputOption::VALUE_NONE, 'Exclude require-dev packages from all entries');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();

        $raw = $composer->getPackage()->getExtra()['size-limit'] ?? null;
        if ($raw === null) {
            throw new RuntimeException('No "extra.size-limit" configuration found in composer.json.');
        }
        $entries = ConfigLoader::load($raw);

        $composerFile = realpath(Factory::getComposerFile());
        if ($composerFile === false) {
            throw new RuntimeException('Cannot locate composer.json.');
        }
        $baseDir = dirname($composerFile);
        $measurer = new Measurer($baseDir);

        $forceNoDev = (bool) $input->getOption('no-dev');
        $devDirs = null;

        $results = [];
        $passed = true;
        foreach ($entries as $entry) {
            $excluded = [];
            if ($forceNoDev || $entry->noDev) {
                $devDirs ??= DevPackages::installDirs((string) $composer->getConfig()->get('vendor-dir'));
                $excluded = $devDirs;
            }

            $size = $measurer->measure($entry, $excluded);
            $ok = $size <= $entry->limit;
            $passed = $passed && $ok;
            $results[] = [
                'name' => $entry->name,
                'size' => $size,
                'limit' => $entry->limit,
                'compression' => $entry->compression->value,
                'passed' => $ok,
            ];
        }

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode(['passed' => $passed, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $table = new Table($output);
            $table->setHeaders(['Name', 'Size', 'Limit', 'Compression', 'Status']);
            foreach ($results as $r) {
                $table->addRow([
                    $r['name'],
                    SizeParser::format($r['size']),
                    SizeParser::format($r['limit']),
                    $r['compression'],
                    $r['passed'] ? '<info>OK</info>' : '<error>EXCEEDED</error>',
                ]);
            }
            $table->render();
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
