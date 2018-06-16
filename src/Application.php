<?php declare(strict_types=1);

namespace Shira\PhpExtUsage;

use Shira\PhpExtUsage\Command\ScanCommand;
use Shira\PhpExtUsage\Scanner\Scanner;

class Application extends \Symfony\Component\Console\Application
{
    protected function getDefaultCommands()
    {
        return array_merge(
            parent::getDefaultCommands(),
            [
                new ScanCommand(new Scanner()),
            ]
        );
    }
}
