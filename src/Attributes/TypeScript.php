<?php

declare(strict_types=1);

namespace Hemilrajput\TypeGen\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class TypeScript
{
    public function __construct(
        public ?string $name = null,
        public bool $export = true,
        /** @var array<string> */
        public array $includeRelations = [],
        /** @var array<string> */
        public array $ignore = [],
    ) {}
}
