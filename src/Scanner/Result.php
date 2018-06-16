<?php declare(strict_types=1);

namespace Shira\PhpExtUsage\Scanner;

class Result
{
    /** @var array extension => true */
    private $extensionMap = [];
    /** @var array extension => function => lines */
    private $functionMap = [];
    /** @var array extension => class => lines */
    private $classMap = [];
    /** @var array extension => constant => lines */
    private $constantMap = [];

    function addFunction(string $extension, string $functionName, int $line): void
    {
        $this->extensionMap[$extension] = true;
        $this->functionMap[$extension][$functionName][] = $line;
    }

    function addClass(string $extension, string $className, int $line): void
    {
        $this->extensionMap[$extension] = true;
        $this->classMap[$extension][$className][] = $line;
    }

    function addConstant(string $extension, string $constantName, int $line): void
    {
        $this->extensionMap[$extension] = true;
        $this->constantMap[$extension][$constantName][] = $line;
    }

    /**
     * @return string[]
     */
    function getExtensions(): array
    {
        return array_keys($this->extensionMap);
    }

    /**
     * @return array function => lines
     */
    function getFunctions(string $extension): array
    {
        return $this->functionMap[$extension] ?? [];
    }

    /**
     * @return array class => lines
     */
    function getClasses(string $extension): array
    {
        return $this->classMap[$extension] ?? [];
    }

    /**
     * @return array constant => lines
     */
    function getConstants(string $extension): array
    {
        return $this->constantMap[$extension] ?? [];
    }
}
