<?php

namespace Hemilrajput\TypeGen\Writers;

use Hemilrajput\TypeGen\Support\TypeMapperHelper;

class TypeScriptWriter
{
    public function __construct(protected array $config) {}

    public function write(array $blocks): string
    {
        $path = $this->config['output']['path'];
        $fileContent = $this->buildContent($blocks);

        $dir = dirname((string) $path);

        if (! TypeMapperHelper::isSafePath($dir)) {
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
}
