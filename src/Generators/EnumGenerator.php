<?php

namespace Hemilrajput\TypeGen\Generators;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use ReflectionEnum;

class EnumGenerator
{
    public function __construct(protected array $config) {}

    public function generate(string $enumClass): string
    {
        $reflectionEnum = new ReflectionEnum($enumClass);
        $name = $this->resolveName($reflectionEnum);
        $values = $this->collectValues($reflectionEnum);

        if ($values === []) {
            return "// SKIPPED: {$name} has no cases";
        }

        $union = implode(' | ', $values);

        return "export type {$name} = {$union};";
    }

    protected function resolveName(ReflectionEnum $reflectionEnum): string
    {
        $attr = $reflectionEnum->getAttributes(TypeScript::class)[0] ?? null;
        $override = $attr?->newInstance()->name;

        return $override ?? $reflectionEnum->getShortName();
    }

    /** @return array<string> */
    protected function collectValues(ReflectionEnum $reflectionEnum): array
    {
        $values = [];

        foreach ($reflectionEnum->getCases() as $reflectionEnumUnitCase) {
            if ($reflectionEnumUnitCase instanceof \ReflectionEnumBackedCase) {
                $backingType = $reflectionEnum->getBackingType()?->getName();
                $value = $reflectionEnumUnitCase->getBackingValue();
                $values[] = $backingType === 'string'
                    ? "'".str_replace("'", "\\'", (string) $value)."'"
                    : (string) $value;
            } else {
                // pure enum — emit case names as string literals
                $values[] = "'".$reflectionEnumUnitCase->getName()."'";
            }
        }

        return $values;
    }
}
