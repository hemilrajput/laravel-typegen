<?php

namespace Hemilrajput\TypeGen\Commands;

use Hemilrajput\TypeGen\Generators\RoutesGenerator;
use Hemilrajput\TypeGen\Support\TypeMapperHelper;
use Illuminate\Console\Command;

class GenerateRoutesCommand extends Command
{
    protected $signature = 'typescript:routes
                            {--dry-run : Print output instead of writing}
                            {--check : Check if generated route types match the existing file}';

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

        if ($this->option('check')) {
            if (file_exists($path) && file_get_contents($path) === $output) {
                $this->info('Route types are up to date.');

                return self::SUCCESS;
            }

            $this->error("Generated route types do not match [{$path}]. Please run 'php artisan typescript:routes' and commit the changes.");

            return self::FAILURE;
        }

        $dir = dirname((string) $path);
        if (! TypeMapperHelper::isSafePath($dir)) {
            throw new \RuntimeException("Output path [{$dir}] is outside the project root. This is a security risk.");
        }

        @mkdir($dir, 0755, recursive: true);
        file_put_contents($path, $output);

        $this->info("Route types successfully written to: {$path}");

        return self::SUCCESS;
    }
}
