<?php

namespace Hemilrajput\TypeGen\Commands;

use Hemilrajput\TypeGen\Generators\RoutesGenerator;
use Illuminate\Console\Command;

class GenerateRoutesCommand extends Command
{
    protected $signature = 'typescript:routes
                            {--dry-run : Print output instead of writing}';

    /** @var array<string> */
    protected $aliases = ['typegen:routes'];

    protected $description = 'Generate TypeScript types for Laravel routes.';

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
