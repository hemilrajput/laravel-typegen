<?php

namespace Hemilrajput\TypeGen\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class TypeScriptIgnore {}
