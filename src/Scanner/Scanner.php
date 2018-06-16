<?php declare(strict_types=1);

namespace Shira\PhpExtUsage\Scanner;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Shira\PhpExtUsage\Scanner\Exception\ScannerException;

class Scanner
{
    /** @var Parser|null */
    private $parser;
    /** @var ExtensionUsageVisitor|null */
    private $visitor;

    /**
     * @throws ScannerException on failure
     */
    function scan(string $phpCode): Result
    {
        $result = new Result();

        $visitor = $this->getExtensionUsageVisitor();
        $visitor->setResultObject($result);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($visitor);

        try {
            $traverser->traverse($this->getParser()->parse($phpCode));
        } catch (\Throwable $e) {
            throw new ScannerException($e->getMessage(), 0, $e);
        } finally {
            $visitor->setResultObject(null);
        }

        return $result;
    }

    private function getParser(): Parser
    {
        return $this->parser ?? ($this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7));
    }

    private function getExtensionUsageVisitor(): ExtensionUsageVisitor
    {
        return $this->visitor ?? ($this->visitor = new ExtensionUsageVisitor());
    }
}
