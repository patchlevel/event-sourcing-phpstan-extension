<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Apply;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Rules\RestrictedUsage\RestrictedMethodUsageExtension;
use PHPStan\Rules\RestrictedUsage\RestrictedUsage;

use function file_get_contents;
use function sprintf;

final class DontRecordWhenApplyingExtension implements RestrictedMethodUsageExtension
{
    private NodeFinder $nodeFinder;
    private ParserFactory $parserFactory;

    /** @var array<string, bool> */
    private array $recordThatCallCache = [];

    public function __construct()
    {
        $this->nodeFinder = new NodeFinder();
        $this->parserFactory = new ParserFactory();
    }

    public function isRestrictedMethodUsage(
        ExtendedMethodReflection $methodReflection,
        Scope $scope,
    ): RestrictedUsage|null {
        if (!$methodReflection->getDeclaringClass()->implementsInterface(AggregateRoot::class)) {
            return null;
        }

        $function = $scope->getFunction();

        if ($function === null) {
            return null;
        }

        if (!$this->isApplyMethod($function)) {
            return null;
        }

        if (!$this->callsRecordThat($methodReflection)) {
            return null;
        }

        return RestrictedUsage::create(
            errorMessage: sprintf(
                'Method %s::recordThat() is called from %s which is an apply method.',
                AggregateRoot::class,
                $function->getName() ?? 'unknown',
            ),
            identifier: 'patchlevel.noRecordThatWhenApplying',
        );
    }

    private function isApplyMethod(FunctionReflection|MethodReflection $function): bool
    {
        foreach ($function->getAttributes() as $attribute) {
            if ($attribute->getName() === Apply::class) {
                return true;
            }
        }

        return false;
    }

    private function callsRecordThat(ExtendedMethodReflection $methodReflection): bool
    {
        if ($methodReflection->getName() === 'recordThat') {
            return true;
        }

        $declaringClass = $methodReflection->getDeclaringClass();
        $fileName = $declaringClass->getFileName();

        if ($fileName === null || $fileName === false) {
            return false;
        }

        $cacheKey = sprintf('%s::%s', $declaringClass->getName(), $methodReflection->getName());

        if (isset($this->recordThatCallCache[$cacheKey])) {
            return $this->recordThatCallCache[$cacheKey];
        }

        $parser = $this->parserFactory->createForNewestSupportedVersion();
        $ast = $parser->parse((string)file_get_contents($fileName));

        if ($ast === null) {
            $this->recordThatCallCache[$cacheKey] = false;

            return false;
        }

        $methodNode = $this->nodeFinder->findFirst(
            $ast,
            static fn (mixed $node): bool => $node instanceof ClassMethod
                && $node->name->toString() === $methodReflection->getName(),
        );

        if (!$methodNode instanceof ClassMethod || $methodNode->stmts === null) {
            $this->recordThatCallCache[$cacheKey] = false;

            return false;
        }

        $result = $this->nodeFinder->findFirst(
            $methodNode->stmts,
            static fn (mixed $node): bool => $node instanceof MethodCall
                && $node->name instanceof Identifier
                && $node->name->toString() === 'recordThat',
        ) !== null;

        $this->recordThatCallCache[$cacheKey] = $result;

        return $result;
    }
}
