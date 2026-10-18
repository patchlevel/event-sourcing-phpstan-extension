<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcingPHPStanExtension\Helper\EventSourcing;
use Patchlevel\EventSourcingPHPStanExtension\Helper\PropertyWrites;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function array_key_exists;
use function spl_object_id;
use function sprintf;

/** @implements Rule<InClassNode> */
final class WriteOnlyPropertyRule implements Rule
{
    private NodeFinder $nodeFinder;

    public function __construct()
    {
        $this->nodeFinder = new NodeFinder();
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        if (!EventSourcing::isAggregate($classReflection)) {
            return [];
        }

        if ($classReflection->isAbstract()) {
            return [];
        }

        $classNode = $node->getOriginalNode();

        $writtenInApply = [];
        $read = [];

        foreach ($classNode->getMethods() as $method) {
            $writeTargets = PropertyWrites::findTargets($method->stmts ?? []);

            if (EventSourcing::hasAttribute($method, Apply::class)) {
                foreach ($writeTargets as $writeTarget) {
                    foreach (PropertyWrites::properties($writeTarget) as $property) {
                        $propertyName = PropertyWrites::ownPropertyName($property);

                        if ($propertyName === null) {
                            continue;
                        }

                        $writtenInApply[$propertyName] = true;
                    }
                }
            }

            foreach ($this->readPropertyNames($method, $writeTargets) as $propertyName) {
                $read[$propertyName] = true;
            }
        }

        $errors = [];

        foreach ($classNode->getProperties() as $property) {
            if ($property->isStatic() || !$property->isPrivate()) {
                continue;
            }

            if (EventSourcing::hasAttribute($property, Id::class)) {
                continue;
            }

            foreach ($property->props as $propertyItem) {
                $propertyName = $propertyItem->name->toString();

                if (!array_key_exists($propertyName, $writtenInApply)) {
                    continue;
                }

                if (array_key_exists($propertyName, $read)) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Property "%s" of aggregate "%s" is written in an #[Apply] method but never read, so it is not used to check any invariants.',
                    $propertyName,
                    $classReflection->getName(),
                ))
                    ->identifier('patchlevel.writeOnlyProperty')
                    ->line($propertyItem->getStartLine())
                    ->tip('Use the property to check invariants or remove it. State that only exists for reading belongs in a projection.')
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * Collects the names of all properties the method reads: every $this
     * property fetch that is not itself the target of a write.
     *
     * @param list<Expr> $writeTargets
     *
     * @return list<string>
     */
    private function readPropertyNames(ClassMethod $method, array $writeTargets): array
    {
        $writeBaseIds = [];

        foreach ($writeTargets as $writeTarget) {
            foreach (PropertyWrites::properties($writeTarget) as $property) {
                $writeBaseIds[spl_object_id($property)] = true;
            }
        }

        $names = [];

        $propertyFetches = $this->nodeFinder->findInstanceOf($method->stmts ?? [], PropertyFetch::class);

        foreach ($propertyFetches as $propertyFetch) {
            if (array_key_exists(spl_object_id($propertyFetch), $writeBaseIds)) {
                continue;
            }

            $propertyName = PropertyWrites::ownPropertyName($propertyFetch);

            if ($propertyName === null) {
                continue;
            }

            $names[] = $propertyName;
        }

        return $names;
    }
}
