<?php

namespace hemilrajput\TypeGen\Commands;

use hemilrajput\TypeGen\Generators\EnumGenerator;
use hemilrajput\TypeGen\Generators\FormRequestGenerator;
use hemilrajput\TypeGen\Generators\ModelGenerator;
use hemilrajput\TypeGen\Mappers\CastTypeMapper;
use hemilrajput\TypeGen\Mappers\RuleToTypeMapper;
use hemilrajput\TypeGen\Mappers\RuleTree;
use hemilrajput\TypeGen\Relations\RelationDetector;
use hemilrajput\TypeGen\Relations\RelationResolver;
use hemilrajput\TypeGen\Scanners\ClassScanner;
use hemilrajput\TypeGen\Writers\TypeScriptSplitWriter;
use hemilrajput\TypeGen\Writers\TypeScriptWriter;
use Illuminate\Console\Command;

class GenerateCommand extends Command
{
    protected $signature = 'typescript:generate
                            {--dry-run : Print output instead of writing}
                            {--watch : Keep running and watch for file changes}';

    protected $description = 'Generate TypeScript types from Laravel models, enums, and form requests.';

    public function handle(ClassScanner $scanner): int
    {
        $config = config('typegen');
        $mapper = new CastTypeMapper($config['cast_map'] ?? []);

        $writer = isset($config['output']['split']) && $config['output']['split']
            ? new TypeScriptSplitWriter($config)
            : new TypeScriptWriter($config);

        if ($this->option('watch')) {
            $this->info('Watching for changes in models, enums, and form requests...');
            $lastRun = 0;
            $lastFiles = [];

            while (app()->runningInConsole()) {
                $files = $this->getWatchFiles($config);
                $changed = count($files) !== count($lastFiles)
                    || ! empty(array_diff($files, $lastFiles))
                    || ! empty(array_diff($lastFiles, $files));

                if (! $changed) {
                    foreach ($files as $file) {
                        if (file_exists($file) && filemtime($file) > $lastRun) {
                            $changed = true;
                            break;
                        }
                    }
                }

                if ($changed) {
                    $this->info('['.date('H:i:s').'] File changes detected. Regenerating...');
                    try {
                        $this->runGeneration($scanner, $config, $mapper, $writer);
                    } catch (\Throwable $e) {
                        $this->error('Generation failed: '.$e->getMessage());
                    }
                    $lastRun = time();
                    $lastFiles = $files;
                }

                sleep(1);
            }

            return self::SUCCESS;
        }

        return $this->runGeneration($scanner, $config, $mapper, $writer);
    }

    protected function runGeneration(ClassScanner $scanner, array $config, CastTypeMapper $mapper, $writer): int
    {
        $blocks = [];

        // 1. Enums
        $enumPath = $config['paths']['enums'] ?? null;
        if ($enumPath && is_dir($enumPath)) {
            $enums = $scanner->scan([$enumPath], $config['scan_mode'] ?? 'attribute', filter: 'enum');
            $enumGenerator = new EnumGenerator($config);

            foreach ($enums as $enum) {
                $this->line("  ✓ enum {$enum}");
                $blocks[] = $enumGenerator->generate($enum);
            }
        }

        // 2. Form Requests
        $requestPath = $config['paths']['form_requests'] ?? null;
        if ($requestPath && is_dir($requestPath)) {
            $requests = $scanner->scan([$requestPath], $config['scan_mode'] ?? 'attribute');
            $requestGenerator = new FormRequestGenerator(
                new RuleToTypeMapper,
                new RuleTree,
                $config,
            );

            foreach ($requests as $request) {
                $this->line("  ✓ request {$request}");
                $blocks[] = $requestGenerator->generate($request);
            }
        }

        // 3. Models
        $modelPath = $config['paths']['models'] ?? app_path('Models');
        $modelBlocks = [];
        if (is_dir($modelPath)) {
            $detector = new RelationDetector;
            $resolver = new RelationResolver($detector);
            $modelGenerator = new ModelGenerator($mapper, $resolver, $config);

            $models = $scanner->scan([$modelPath], $config['scan_mode'] ?? 'attribute');

            // BFS queue with cycle detection
            $queue = array_values($models);
            $seen = array_flip($queue);

            while (! empty($queue)) {
                $modelClass = array_shift($queue);
                $this->line("  ✓ model {$modelClass}");

                $result = $modelGenerator->generate($modelClass);
                $modelBlocks[] = $result['block'];

                // Add any newly-discovered related models to the queue
                foreach ($result['discovered'] as $discoveredClass) {
                    if (! isset($seen[$discoveredClass]) && class_exists($discoveredClass)) {
                        $seen[$discoveredClass] = true;
                        $queue[] = $discoveredClass;
                        $this->line("    ↳ discovered {$discoveredClass}");
                    }
                }
            }
        }

        // Assemble: enums -> form requests -> models
        $allBlocks = [...$blocks, ...$modelBlocks];

        if (empty($allBlocks)) {
            $this->warn('No classes found. Did you add the #[TypeScript] attribute?');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line("\n".implode("\n\n", $allBlocks));

            return self::SUCCESS;
        }

        $path = $writer->write($allBlocks);
        $this->info("\nWritten to: {$path}");

        return self::SUCCESS;
    }

    protected function getWatchFiles(array $config): array
    {
        $files = [];
        $configPath = config_path('typegen.php');
        if (file_exists($configPath)) {
            $files[] = $configPath;
        }

        foreach ($config['paths'] ?? [] as $path) {
            if ($path && is_dir($path)) {
                $directoryIterator = new \RecursiveDirectoryIterator($path);
                $iterator = new \RecursiveIteratorIterator($directoryIterator);
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $files[] = $file->getRealPath();
                    }
                }
            }
        }

        return array_unique($files);
    }
}
