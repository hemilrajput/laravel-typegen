<?php

namespace Hemil09\TypeGen\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class TypeScript
{
    public function __construct(
        public ?string $name = null,
        public bool $export = true,
        /** @var array<string> */
        public array $includeRelations = [],
    ) {}
}
