<?php

namespace Hemilrajput\TypeGen\Generators;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use ReflectionEnum;

class EnumGenerator
{
    public function __construct(protected array $config) {}

    public function generate(string $enumClass): string
    {
        $reflection = new ReflectionEnum($enumClass);
        $name = $this->resolveName($reflection);
        $values = $this->collectValues($reflection);

        if (empty($values)) {
            return "// SKIPPED: {$name} has no cases";
        }

        $union = implode(' | ', $values);

        return "export type {$name} = {$union};";
    }

    protected function resolveName(ReflectionEnum $reflection): string
    {
        $attr = $reflection->getAttributes(TypeScript::class)[0] ?? null;
        $override = $attr?->newInstance()->name;

        return $override ?? $reflection->getShortName();
    }

    /** @return array<string> */
    protected function collectValues(ReflectionEnum $reflection): array
    {
        $values = [];

        foreach ($reflection->getCases() as $case) {
            if ($case instanceof \ReflectionEnumBackedCase) {
                $backingType = $reflection->getBackingType()?->getName();
                $value = $case->getBackingValue();
                $values[] = $backingType === 'string'
                    ? "'".str_replace("'", "\\'", (string) $value)."'"
                    : (string) $value;
            } else {
                // pure enum — emit case names as string literals
                $values[] = "'".$case->getName()."'";
            }
        }

        return $values;
    }
}
