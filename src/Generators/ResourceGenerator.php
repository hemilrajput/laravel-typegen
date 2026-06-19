<?php

namespace Hemilrajput\TypeGen\Generators;

use Hemilrajput\TypeGen\Mappers\CastTypeMapper;
use ReflectionClass;

class ResourceGenerator
{
    public function __construct(
        protected CastTypeMapper $mapper,
        protected array $config,
    ) {}

    public function generate(string $resourceClass): string
    {
        $reflectionClass = new ReflectionClass($resourceClass);
        $name = $reflectionClass->getShortName();
        $fields = $this->collectFields($resourceClass, $reflectionClass);

        $allLines = [];
        foreach ($fields as $field => $type) {
            $allLines[] = "  {$field}: {$type};";
        }

        $body = implode("\n", $allLines);
        $style = $this->config['output']['style'] ?? 'interface';
        $keyword = $style === 'type' ? "export type {$name} =" : "export interface {$name}";
        $opener = $style === 'type' ? ' {' : ' {';

        return "{$keyword}{$opener}\n{$body}\n}";
    }

    /** @return array<string,string> */
    protected function collectFields(string $resourceClass, ReflectionClass $reflectionClass): array
    {
        $fields = [];
        $docComment = $reflectionClass->getDocComment();

        if ($docComment) {
            preg_match_all('/@property(?:-read)?\s+([^\s]+)\s+\$(\w+)/', $docComment, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $type = $match[1];
                $name = $match[2];
                $fields[$name] = $this->parsePhpDocType($type);
            }
        }

        // Fallback to matching model if no properties are defined via PHPDoc
        if ($fields === []) {
            $baseName = $reflectionClass->getShortName();
            if (str_ends_with($baseName, 'Resource')) {
                $modelName = substr($baseName, 0, -8);
                $modelClass = null;
                $possibleClasses = [
                    "App\\Models\\{$modelName}",
                    "App\\{$modelName}",
                    "hemilrajput\\TypeGen\\Tests\\Fixtures\\Models\\{$modelName}", // for test environment
                ];
                foreach ($possibleClasses as $possibleClass) {
                    if (class_exists($possibleClass)) {
                        $modelClass = $possibleClass;
                        break;
                    }
                }

                if ($modelClass) {
                    $instance = new $modelClass;
                    // Primary key
                    $fields[$instance->getKeyName()] = $instance->getKeyType() === 'int' ? 'number' : 'string';
                    // Casts
                    foreach ($instance->getCasts() as $attr => $cast) {
                        $fields[$attr] = $this->mapper->toTypeScript($cast);
                    }
                    // Fillable
                    foreach ($instance->getFillable() as $attr) {
                        if (! isset($fields[$attr])) {
                            $fields[$attr] = 'string';
                        }
                    }
                }
            }
        }

        return $fields;
    }

    protected function parsePhpDocType(string $type): string
    {
        $type = trim($type);
        $isNullable = false;

        if (str_starts_with($type, '?')) {
            $isNullable = true;
            $type = substr($type, 1);
        }

        $types = explode('|', $type);
        $mappedTypes = [];

        foreach ($types as $t) {
            $t = strtolower(trim($t));
            if ($t === 'null') {
                $isNullable = true;

                continue;
            }

            $mapped = match ($t) {
                'int', 'integer', 'float', 'double' => 'number',
                'string' => 'string',
                'bool', 'boolean' => 'boolean',
                'array' => 'any[]',
                'mixed' => 'any',
                default => 'any',
            };

            // If it matches a resource or model name, we keep its short name
            if ($mapped === 'any' && preg_match('/^[A-Z]\w+$/', trim($t))) {
                $mapped = trim($t);
            }
            $mappedTypes[] = $mapped;
        }

        $union = implode(' | ', array_unique($mappedTypes));
        if ($isNullable) {
            return "{$union} | null";
        }

        return $union;
    }
}
