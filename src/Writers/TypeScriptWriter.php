<?php

namespace Hemilrajput\TypeGen\Writers;

class TypeScriptWriter
{
    public function __construct(protected array $config) {}

    /** @param  array<string>  $blocks  rendered interface/type blocks */
    public function write(array $blocks): string
    {
        $path = $this->config['output']['path'];
        $banner = $this->config['output']['banner'] ?? '';

        $contents = array_column($blocks, 'content');
        $fileContent = $banner."\n".implode("\n\n", $contents)."\n";

        @mkdir(dirname((string) $path), 0755, recursive: true);
        file_put_contents($path, $fileContent);

        return $path;
    }
}
