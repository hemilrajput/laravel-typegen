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
use hemilrajput\TypeGen\Writers\TypeScriptWriter;
use Illuminate\Console\Command;

class GenerateCommand extends Command
{
    protected $signature = 'typescript:generate
                            {--dry-run : Print output instead of writing}';

    protected $description = 'Generate TypeScript types from Laravel models, enums, and form requests.';

    public function handle(ClassScanner $scanner): int
    {
        $config = config('typegen');
        $mapper = new CastTypeMapper($config['cast_map'] ?? []);
        $writer = new TypeScriptWriter($config);
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
}
