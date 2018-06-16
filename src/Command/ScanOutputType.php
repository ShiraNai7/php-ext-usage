<?php declare(strict_types=1);

namespace Shira\PhpExtUsage\Command;

use Kuria\Enum\Enum;

class ScanOutputType extends Enum
{
    const TEXT = 'text';
    const JSON = 'json';
    const COMPOSER = 'composer';
}
