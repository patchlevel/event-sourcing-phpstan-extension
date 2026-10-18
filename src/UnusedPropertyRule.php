<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcingPHPStanExtension\Helper\EventSourcing;
use Patchlevel\EventSourcingPHPStanExtension\Helper\PropertyWrites;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function array_key_exists;
use function sprintf;

/** @implements Rule<InClassNode> */
final class UnusedPropertyRule implements Rule
{
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

        foreach ($classNode->getMethods() as $method) {
            if (!EventSourcing::hasAttribute($method, Apply::class)) {
                continue;
            }

            foreach (PropertyWrites::findTargets($method->stmts ?? [], withUnset: false) as $target) {
                foreach (PropertyWrites::properties($target) as $property) {
                    $propertyName = PropertyWrites::ownPropertyName($property);

                    if ($propertyName === null) {
                        continue;
                    }

                    $writtenInApply[$propertyName] = true;
                }
            }
        }

        $errors = [];

        foreach ($classNode->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            foreach ($property->props as $propertyItem) {
                if ($propertyItem->default !== null) {
                    continue;
                }

                $propertyName = $propertyItem->name->toString();

                if (array_key_exists($propertyName, $writtenInApply)) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Property "%s" of aggregate "%s" is never written in an #[Apply] method and is therefore unused.',
                    $propertyName,
                    $classReflection->getName(),
                ))
                    ->identifier('patchlevel.unusedProperty')
                    ->line($propertyItem->getStartLine())
                    ->tip('Change the state in an #[Apply] method or remove the property.')
                    ->build();
            }
        }

        return $errors;
    }
}
