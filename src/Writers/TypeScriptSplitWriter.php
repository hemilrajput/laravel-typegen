<?php

namespace Hemilrajput\TypeGen\Writers;

use Illuminate\Support\Str;

class TypeScriptSplitWriter
{
    public function __construct(protected array $config) {}

    public function write(array $blocks): string
    {
        $path = $this->config['output']['path'];
        $banner = $this->config['output']['banner'] ?? '';

        // Determine output directory: strip .ts extension from the output path and make it a folder
        $dir = dirname((string) $path).'/'.pathinfo((string) $path, PATHINFO_FILENAME);

        if (! $this->isSafePath($dir)) {
            throw new \RuntimeException("Output path [{$dir}] is outside the project root. This is a security risk.");
        }

        if (is_dir($dir)) {
            // Clean up old files/directories recursively
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getRealPath());
                } else {
                    @unlink($file->getRealPath());
                }
            }
        } else {
            @mkdir($dir, 0755, recursive: true);
        }

        // Map blocks to their defined type names and categories
        $typeMap = [];

        foreach ($blocks as $block) {
            if (preg_match('/export\s+(?:interface|type|enum)\s+(\w+)/', (string) $block['content'], $match)) {
                $typeName = $match[1];
                $typeMap[$typeName] = [
                    'category' => $block['category'],
                    'content' => $block['content'],
                ];
            }
        }

        $categories = [];

        // Write each type file with resolved imports
        foreach ($typeMap as $typeName => $info) {
            $myCat = $info['category'];
            $myContent = $info['content'];

            $imports = [];
            foreach ($typeMap as $otherType => $otherInfo) {
                if ($otherType === $typeName) {
                    continue;
                }

                // Match exact word boundaries
                if (preg_match('/\b'.preg_quote($otherType, '/').'\b/', (string) $myContent)) {
                    $otherCat = $otherInfo['category'];
                    if ($myCat === $otherCat) {
                        $imports[] = "import { {$otherType} } from './{$otherType}';";
                    } else {
                        $imports[] = "import { {$otherType} } from '../{$otherCat}/{$otherType}';";
                    }
                }
            }

            $fileContent = $banner;
            if ($imports !== []) {
                $fileContent .= implode("\n", $imports)."\n\n";
            }
            $fileContent .= $myContent."\n";

            $catDir = $dir.'/'.$myCat;
            @mkdir($catDir, 0755, recursive: true);
            file_put_contents("{$catDir}/{$typeName}.ts", $fileContent);

            $categories[$myCat][] = $typeName;
        }

        // Write barrel index.ts files for each category
        foreach ($categories as $cat => $types) {
            $indexLines = [$banner];
            foreach ($types as $type) {
                $indexLines[] = "export * from './{$type}';";
            }
            $indexContent = implode("\n", $indexLines)."\n";
            file_put_contents("{$dir}/{$cat}/index.ts", $indexContent);
        }

        // Write root barrel index.ts
        $rootLines = [$banner];
        foreach (array_keys($categories) as $cat) {
            $rootLines[] = "export * from './{$cat}';";
        }
        $rootContent = implode("\n", $rootLines)."\n";
        file_put_contents("{$dir}/index.ts", $rootContent);

        return $dir;
    }

    protected function isSafePath(string $path): bool
    {
        if (function_exists('app') && app()->runningUnitTests()) {
            return true;
        }

        $realPath = realpath($path);

        if ($realPath === false) {
            $parent = dirname($path);
            if ($parent === $path || $parent === '.') {
                $realPath = $path;
            } else {
                return $this->isSafePath($parent);
            }
        }

        $basePath = realpath(base_path());

        $realPath = str_replace('\\', '/', $realPath);
        $basePath = str_replace('\\', '/', $basePath);

        return Str::startsWith($realPath, $basePath);
    }
}
