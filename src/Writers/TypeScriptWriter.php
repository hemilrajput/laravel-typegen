<?php

namespace Hemilrajput\TypeGen\Writers;

use Illuminate\Support\Str;

class TypeScriptWriter
{
    public function __construct(protected array $config) {}

    public function write(array $blocks): string
    {
        $path = $this->config['output']['path'];
        $fileContent = $this->buildContent($blocks);

        $dir = dirname((string) $path);

        if (! $this->isSafePath($dir)) {
            throw new \RuntimeException("Output path [{$dir}] is outside the project root. This is a security risk.");
        }

        @mkdir($dir, 0755, recursive: true);
        file_put_contents($path, $fileContent);

        return $path;
    }

    public function check(array $blocks): bool
    {
        $path = $this->config['output']['path'];
        if (! file_exists($path)) {
            return false;
        }

        return file_get_contents($path) === $this->buildContent($blocks);
    }

    protected function buildContent(array $blocks): string
    {
        $banner = $this->config['output']['banner'] ?? '';
        $contents = array_column($blocks, 'content');

        return $banner."\n".implode("\n\n", $contents)."\n";
    }

    protected function isSafePath(string $path): bool
    {
        if (function_exists('app') && app()->runningUnitTests()) {
            return true;
        }

        $realPath = realpath($path);

        if ($realPath === false) {
            // Path doesn't exist yet, check its parent recursively
            $parent = dirname($path);
            if ($parent === $path || $parent === '.') {
                $realPath = $path; // Fallback
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
