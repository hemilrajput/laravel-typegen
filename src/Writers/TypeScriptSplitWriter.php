<?php

namespace Hemilrajput\TypeGen\Writers;

use Illuminate\Support\Str;

class TypeScriptSplitWriter
{
    public function __construct(protected array $config) {}

    public function write(array $blocks): string
    {
        $path = $this->config['output']['path'];
        $dir = dirname((string) $path).'/'.pathinfo((string) $path, PATHINFO_FILENAME);

        if (! $this->isSafePath($dir)) {
            throw new \RuntimeException("Output path [{$dir}] is outside the project root. This is a security risk.");
        }

        if (is_dir($dir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getRealPath());
                } elseif ($file->getExtension() === 'ts') {
                    @unlink($file->getRealPath());
                }
            }
        } else {
            @mkdir($dir, 0755, recursive: true);
        }

        $files = $this->buildFiles($blocks);
        foreach ($files as $filePath => $content) {
            $fullPath = "{$dir}/{$filePath}";
            $catDir = dirname($fullPath);
            @mkdir($catDir, 0755, recursive: true);
            file_put_contents($fullPath, $content);
        }

        return $dir;
    }

    public function check(array $blocks): bool
    {
        $path = $this->config['output']['path'];
        $dir = dirname((string) $path).'/'.pathinfo((string) $path, PATHINFO_FILENAME);

        if (! is_dir($dir)) {
            return false;
        }

        $files = $this->buildFiles($blocks);

        // Check if all generated files match disk
        foreach ($files as $filePath => $content) {
            $fullPath = "{$dir}/{$filePath}";
            if (! file_exists($fullPath) || file_get_contents($fullPath) !== $content) {
                return false;
            }
        }

        // Check if there are extra files on disk
        $diskFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'ts') {
                $relPath = str_replace('\\', '/', substr($file->getRealPath(), strlen(realpath($dir)) + 1));
                if (! isset($files[$relPath])) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return array<string,string> */
    protected function buildFiles(array $blocks): array
    {
        $banner = $this->config['output']['banner'] ?? '';
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
        $files = [];

        foreach ($typeMap as $typeName => $info) {
            $myCat = $info['category'];
            $myContent = $info['content'];

            $imports = [];
            foreach ($typeMap as $otherType => $otherInfo) {
                if ($otherType === $typeName) {
                    continue;
                }

                if (preg_match('/(?:[:<|&]\s*(?:readonly\s+)?|extends\s+|implements\s+|type\s+\w+\s*=\s*)\b'.preg_quote($otherType, '/').'\b/', (string) $myContent)) {
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

            $files["{$myCat}/{$typeName}.ts"] = $fileContent;
            $categories[$myCat][] = $typeName;
        }

        foreach ($categories as $cat => $types) {
            $indexLines = [$banner];
            foreach ($types as $type) {
                $indexLines[] = "export * from './{$type}';";
            }
            $files["{$cat}/index.ts"] = implode("\n", $indexLines)."\n";
        }

        $rootLines = [$banner];
        foreach (array_keys($categories) as $cat) {
            $rootLines[] = "export * from './{$cat}';";
        }
        $files['index.ts'] = implode("\n", $rootLines)."\n";

        return $files;
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
