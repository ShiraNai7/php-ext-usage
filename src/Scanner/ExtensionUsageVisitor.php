<?php declare(strict_types=1);

namespace Shira\PhpExtUsage\Scanner;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ExtensionUsageVisitor extends NodeVisitorAbstract
{
    /**
     * List of core extensions that cannot be removed
     *
     * @see http://php.net/manual/en/extensions.membership.php#extensions.membership.core
     *
     * Note that some of the extensions listed on the above page can disabled (e.g. filter, session, phar...),
     * contrary to the linked page stating that they "cannot be left out of a PHP binary with compilation options".
     */
    private const CORE_EXTENSION_MAP = [
        'core' => true,
        'date' => true,
        'pcre' => true,
        'reflection' => true,
        'spl' => true,
        'standard' => true,
    ];

    /**
     * List of callable parameter name patterns
     */
    private const CALLABLE_PARAM_PATTERNS = [
        '*callback*',
        '*function*',
        '*funcname*',
    ];

    /** @var Result|null */
    private $resultObject;
    /** @var array|null constant => extension name */
    private $constantToExtensionMap;

    function setResultObject(?Result $result): void
    {
        $this->resultObject = $result;
    }

    function enterNode(Node $node)
    {
        if ($node instanceof Node\Expr\FuncCall) {
            // function call
            foreach ($this->getPossibleConstantOrFunctionNames($node->name) as $name) {
                if (function_exists($name)) {
                    $this->handleFunctionCall($name, $node->args, $node->getStartLine());
                    break;
                }
            }
        } elseif ($node instanceof Node\Expr\ConstFetch) {
            // constant fetch
            foreach ($this->getPossibleConstantOrFunctionNames($node->name) as $name) {
                if (defined($name)) {
                    $this->handleConstantFetch($name, $node->getStartLine());
                    break;
                }
            }
        } elseif (
            $node instanceof Node\Expr\New_
            || $node instanceof Node\Expr\ClassConstFetch
            || $node instanceof Node\Expr\StaticPropertyFetch
            || $node instanceof Node\Expr\StaticCall
        ) {
            // class operation
            if ($node->class instanceof Node\Name\FullyQualified && class_exists($name = $node->class->toString(), false)) {
                $this->handleClassUsage($name, $node->getStartLine());
            }
        }

        return null;
    }

    /**
     * @param Node\Arg[] $arguments
     */
    private function handleFunctionCall(string $functionName, array $arguments, int $line): void
    {
        $refl = new \ReflectionFunction($functionName);
        $extension = $refl->getExtensionName();

        // detect extension function call
        if ($extension && !$this->isCoreExtension($extension)) {
            $this->resultObject->addFunction($extension, $functionName, $line);
        }

        // detect callback arguments
        $this->handleFunctionNamesPassedAsCallbacks($refl->getParameters(), $arguments);
    }

    /**
     * @param \ReflectionParameter[] $parameters
     * @param Node\Arg[] $arguments
     */
    private function handleFunctionNamesPassedAsCallbacks(array $parameters, array $arguments): void
    {
        foreach ($parameters as $index => $parameter) {
            if (!isset($arguments[$index]) || $parameter->isVariadic()) {
                break;
            }

            $argument = $arguments[$index]->value;

            if (
                // parameter is callable
                (
                    $parameter->isCallable()
                    || !$parameter->hasType() && $this->isCallableParameterName($parameter->getName())
                )

                // and the argument is a string literal
                && $argument instanceof Node\Scalar\String_

                // and it is an existing function name
                && function_exists($argument->value)

                // belonging to a non-core extension
                && ($extension = (new \ReflectionFunction($argument->value))->getExtensionName())
                && !$this->isCoreExtension($extension)
            ) {
                $this->getResultObject()->addFunction($extension, $argument->value, $argument->getStartLine());
            }
        }
    }

    private function isCallableParameterName(string $parameterName): bool
    {
        foreach (self::CALLABLE_PARAM_PATTERNS as $pattern) {
            if (fnmatch($pattern, $parameterName)) {
                return true;
            }
        }

        return false;
    }

    private function handleConstantFetch(string $constantName, int $line): void
    {
        $extension = $this->getConstantExtension($constantName);

        if ($extension !== null && !$this->isCoreExtension($extension)) {
            $this->getResultObject()->addConstant($extension, $constantName, $line);
        }
    }

    private function handleClassUsage(string $className, int $line): void
    {
        if (
            ($extension = (new \ReflectionClass($className))->getExtensionName())
            && !$this->isCoreExtension($extension)
        ) {
            $this->getResultObject()->addClass($extension, $className, $line);
        }
    }

    private function isCoreExtension(string $extension): bool
    {
        return isset(self::CORE_EXTENSION_MAP[strtolower($extension)]);
    }

    private function getPossibleConstantOrFunctionNames($nodeName): array
    {
        $names = [];

        if ($nodeName instanceof Node\Name\FullyQualified) {
            // fully-qualified name only
            $names[] = $nodeName->toString();
        } elseif ($nodeName instanceof Node\Name) {
            // namespaced or global name
            if ($nodeName->hasAttribute('namespacedName')) {
                $names[] = $nodeName->getAttribute('namespacedName')->toString();
            }

            $names[] = $nodeName->toString();
        }

        return $names;
    }

    private function getConstantExtension(string $constantName): ?string
    {
        if ($this->constantToExtensionMap === null) {
            $this->constantToExtensionMap = $this->buildConstantToExtensionMap();
        }

        return $this->constantToExtensionMap[$constantName] ?? null;
    }

    private function buildConstantToExtensionMap(): array
    {
        $map = [];

        foreach (get_defined_constants(true) as $extension => $constants) {
            if ($extension !== 'user') {
                $map += array_fill_keys(array_keys($constants), $extension);
            }
        }

        return $map;
    }

    private function getResultObject(): Result
    {
        if ($this->resultObject === null) {
            throw new \LogicException('No result object has been set');
        }

        return $this->resultObject;
    }
}
