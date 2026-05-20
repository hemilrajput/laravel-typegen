<?php

namespace hemilrajput\TypeGen\Writers;

class TypeScriptWriter
{
    public function __construct(protected array $config) {}

    /** @param  array<string>  $blocks  rendered interface/type blocks */
    public function write(array $blocks): string
    {
        $path = $this->config['output']['path'];
        $banner = $this->config['output']['banner'] ?? '';
        $contents = $banner."\n".implode("\n\n", $blocks)."\n";

        @mkdir(dirname($path), 0755, recursive: true);
        file_put_contents($path, $contents);

        return $path;
    }
}
