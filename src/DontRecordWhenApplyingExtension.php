<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\ChildAggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Rules\RestrictedUsage\RestrictedMethodUsageExtension;
use PHPStan\Rules\RestrictedUsage\RestrictedUsage;

use function array_key_exists;
use function in_array;
use function sprintf;
use function strtolower;

final class DontRecordWhenApplyingExtension implements RestrictedMethodUsageExtension
{
    private NodeFinder $nodeFinder;

    /** @var array<string, bool> */
    private array $recordsEventCache = [];

    public function __construct(private readonly Parser $parser)
    {
        $this->nodeFinder = new NodeFinder();
    }

    public function isRestrictedMethodUsage(
        ExtendedMethodReflection $methodReflection,
        Scope $scope,
    ): RestrictedUsage|null {
        $declaringClass = $methodReflection->getDeclaringClass();

        if (!$this->isAggregate($declaringClass)) {
            return null;
        }

        $function = $scope->getFunction();

        if ($function === null) {
            return null;
        }

        if (!$this->isApplyMethod($function)) {
            return null;
        }

        $visited = [];

        if (!$this->recordsEvent($declaringClass, $methodReflection->getName(), $visited)) {
            return null;
        }

        return RestrictedUsage::create(
            errorMessage: sprintf(
                'Method %s::%s() records an event and is called from apply method %s().',
                $declaringClass->getName(),
                $methodReflection->getName(),
                $function->getName(),
            ),
            identifier: 'patchlevel.noRecordThatWhenApplying',
        );
    }

    private function isAggregate(ClassReflection $classReflection): bool
    {
        return $classReflection->implementsInterface(AggregateRoot::class)
            || $classReflection->implementsInterface(ChildAggregate::class);
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

    /** @param array<string, true> $visited */
    private function recordsEvent(ClassReflection $classReflection, string $methodName, array &$visited): bool
    {
        if ($methodName === 'recordThat') {
            return true;
        }

        $cacheKey = sprintf('%s::%s', $classReflection->getName(), $methodName);

        if (array_key_exists($cacheKey, $this->recordsEventCache)) {
            return $this->recordsEventCache[$cacheKey];
        }

        if (array_key_exists($cacheKey, $visited)) {
            return false;
        }

        $visited[$cacheKey] = true;

        $methodNode = $this->findMethodNode($classReflection, $methodName);
        $result = false;

        if ($methodNode?->stmts !== null) {
            foreach ($this->calledMethodNames($methodNode) as $calledMethodName) {
                if ($this->recordsEvent($classReflection, $calledMethodName, $visited)) {
                    $result = true;
                    break;
                }
            }
        }

        $this->recordsEventCache[$cacheKey] = $result;

        return $result;
    }

    private function findMethodNode(ClassReflection $classReflection, string $methodName): ClassMethod|null
    {
        $nativeClass = $classReflection->getNativeReflection();

        if (!$nativeClass->hasMethod($methodName)) {
            return null;
        }

        $nativeMethod = $nativeClass->getMethod($methodName);
        $fileName = $nativeMethod->getFileName();

        if ($fileName === false) {
            return null;
        }

        $candidates = $this->nodeFinder->find(
            $this->parser->parseFile($fileName),
            static fn (Node $node): bool => $node instanceof ClassMethod
                && $node->name->toString() === $nativeMethod->getName(),
        );

        if ($candidates === []) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if ($candidate->getStartLine() === $nativeMethod->getStartLine()) {
                return $candidate instanceof ClassMethod ? $candidate : null;
            }
        }

        $candidate = $candidates[0];

        return $candidate instanceof ClassMethod ? $candidate : null;
    }

    /**
     * Collects the names of all methods the given method calls on its own
     * instance ($this->, self::, static::, parent::). Calls on other objects
     * are ignored, their receiver type cannot be resolved without a scope.
     *
     * @return list<string>
     */
    private function calledMethodNames(ClassMethod $methodNode): array
    {
        $calls = $this->nodeFinder->find(
            $methodNode->stmts ?? [],
            static function (Node $node): bool {
                if ($node instanceof MethodCall) {
                    return $node->var instanceof Variable
                        && $node->var->name === 'this'
                        && $node->name instanceof Identifier;
                }

                if ($node instanceof StaticCall) {
                    return $node->class instanceof Name
                        && in_array(strtolower($node->class->toString()), ['self', 'static', 'parent'], true)
                        && $node->name instanceof Identifier;
                }

                return false;
            },
        );

        $names = [];

        foreach ($calls as $call) {
            if (!$call instanceof MethodCall && !$call instanceof StaticCall) {
                continue;
            }

            if (!$call->name instanceof Identifier) {
                continue;
            }

            $names[] = $call->name->toString();
        }

        return $names;
    }
}
