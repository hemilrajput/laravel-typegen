<?php

namespace Hemilrajput\TypeGen\Commands;

use Hemilrajput\TypeGen\Generators\RoutesGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Generate TypeScript types for Laravel routes.')]
#[Signature('typescript:routes
                            {--dry-run : Print output instead of writing}')]
class GenerateRoutesCommand extends Command
{
    public function handle(): int
    {
        $config = config('typegen');
        $routesGenerator = new RoutesGenerator($config);
        $output = $routesGenerator->generate();

        if ($this->option('dry-run')) {
            $this->line($output);

            return self::SUCCESS;
        }

        $path = $config['output']['routes_path'] ?? resource_path('js/types/routes.ts');

        @mkdir(dirname((string) $path), 0755, recursive: true);
        file_put_contents($path, $output);

        $this->info("Route types successfully written to: {$path}");

        return self::SUCCESS;
    }
}
