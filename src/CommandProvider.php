<?php

declare(strict_types=1);

namespace Core23\SizeLimit;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Core23\SizeLimit\Command\SizeLimitCommand;

final class CommandProvider implements CommandProviderCapability
{
    public function getCommands(): array
    {
        return [new SizeLimitCommand()];
    }
}
