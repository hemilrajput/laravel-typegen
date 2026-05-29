<?php

namespace Hemilrajput\TypeGen\Commands;

use Hemilrajput\TypeGen\Generators\RoutesGenerator;
use Illuminate\Console\Command;

class GenerateRoutesCommand extends Command
{
    protected $signature = 'typescript:routes
                            {--dry-run : Print output instead of writing}';

    protected $description = 'Generate TypeScript types for Laravel routes.';

    public function handle(): int
    {
        $config = config('typegen');
        $generator = new RoutesGenerator($config);
        $output = $generator->generate();

        if ($this->option('dry-run')) {
            $this->line($output);

            return self::SUCCESS;
        }

        $path = $config['output']['routes_path'] ?? resource_path('js/types/routes.ts');

        @mkdir(dirname($path), 0755, recursive: true);
        file_put_contents($path, $output);

        $this->info("Route types successfully written to: {$path}");

        return self::SUCCESS;
    }
}
