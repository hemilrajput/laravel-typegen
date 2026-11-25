<?php

namespace Hemilrajput\TypeGen\Scanners;

use Hemilrajput\TypeGen\Attributes\TypeScript;
use Hemilrajput\TypeGen\Attributes\TypeScriptIgnore;
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

                $reflection = new ReflectionClass($fqcn);
                if ($filter !== 'enum' && ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait())) {
                    continue;
                }

                if ($reflection->getAttributes(TypeScriptIgnore::class) !== []) {
                    continue;
                }

                if ($mode === 'all' || $this->hasAttribute($reflection)) {
                    $found[] = $fqcn;
                }
            }
        }

        return $found;
    }

    /** @param ReflectionClass<object> $reflection */
    private function hasAttribute(ReflectionClass $reflection): bool
    {
        return $reflection->getAttributes(TypeScript::class) !== [];
    }

    private function classFromFile(string $path): ?string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $tokens = token_get_all($contents);
        $count = count($tokens);
        $namespace = '';

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $ns = '';
                for ($j = $i + 1; $j < $count; $j++) {
                    $next = $tokens[$j];
                    if (is_array($next) && in_array($next[0], [T_STRING, T_NAME_QUALIFIED], true)) {
                        $ns .= $next[1];
                    } elseif ($next === ';' || $next === '{') {
                        break;
                    }
                }
                $namespace = trim($ns);
            }

            if (in_array($token[0], [T_CLASS, T_ENUM], true)) {
                // Skip anonymous classes (`new class ...`)
                $prevIndex = $i - 1;
                while ($prevIndex >= 0 && is_array($tokens[$prevIndex]) && in_array($tokens[$prevIndex][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $prevIndex--;
                }
                if ($prevIndex >= 0 && is_array($tokens[$prevIndex]) && $tokens[$prevIndex][0] === T_NEW) {
                    continue;
                }

                for ($j = $i + 1; $j < $count; $j++) {
                    $next = $tokens[$j];
                    if (is_array($next) && $next[0] === T_STRING) {
                        return $namespace !== '' ? $namespace.'\\'.$next[1] : $next[1];
                    }
                    if (! is_array($next) || ! in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        break;
                    }
                }
            }
        }

        return null;
    }
}
