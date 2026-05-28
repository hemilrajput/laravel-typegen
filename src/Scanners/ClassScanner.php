<?php

namespace hemilrajput\TypeGen\Scanners;

use hemilrajput\TypeGen\Attributes\TypeScript;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class ClassScanner
{
    /**
     * @param  array<string>  $paths
     * @return array<class-string>
     */
    public function scan(array $paths, string $mode = 'attribute', string $filter = 'class'): array
    {
        $found = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach ((new Finder)->files()->in($path)->name('*.php') as $file) {
                $fqcn = $this->classFromFile($file->getRealPath());
                if (! $fqcn) {
                    continue;
                }

                $exists = match ($filter) {
                    'enum' => enum_exists($fqcn),
                    default => class_exists($fqcn),
                };
                if (! $exists) {
                    continue;
                }

                if ($mode === 'all' || $this->hasAttribute($fqcn)) {
                    $found[] = $fqcn;
                }
            }
        }

        return $found;
    }

    private function hasAttribute(string $fqcn): bool
    {
        return (bool) (new ReflectionClass($fqcn))
            ->getAttributes(TypeScript::class);
    }

    private function classFromFile(string $path): ?string
    {
        $contents = file_get_contents($path);
        if (! preg_match('/namespace\s+([^;]+);/', $contents, $ns)) {
            return null;
        }

        $className = pathinfo($path, PATHINFO_FILENAME);

        return trim($ns[1]).'\\'.$className;
    }
}
