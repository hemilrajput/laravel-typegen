<?php

namespace Hemilrajput\TypeGen\Commands;

use Hemilrajput\TypeGen\Generators\EnumGenerator;
use Hemilrajput\TypeGen\Generators\FormRequestGenerator;
use Hemilrajput\TypeGen\Generators\ModelGenerator;
use Hemilrajput\TypeGen\Generators\ResourceGenerator;
use Hemilrajput\TypeGen\Mappers\CastTypeMapper;
use Hemilrajput\TypeGen\Mappers\RuleToTypeMapper;
use Hemilrajput\TypeGen\Mappers\RuleTree;
use Hemilrajput\TypeGen\Relations\RelationDetector;
use Hemilrajput\TypeGen\Relations\RelationResolver;
use Hemilrajput\TypeGen\Scanners\ClassScanner;
use Hemilrajput\TypeGen\Writers\TypeScriptSplitWriter;
use Hemilrajput\TypeGen\Writers\TypeScriptWriter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use Symfony\Component\Process\Process;

#[Description('Generate TypeScript types from Laravel models, enums, and form requests.')]
#[Signature('typescript:generate
                            {--dry-run : Print output instead of writing}
                            {--watch : Keep running and watch for file changes}')]
class GenerateCommand extends Command
{
    public function handle(ClassScanner $classScanner, CastTypeMapper $castTypeMapper): int
    {
        $config = config('typegen');

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
                    || array_diff($files, $lastFiles) !== []
                    || array_diff($lastFiles, $files) !== [];

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
                        $this->runGeneration($classScanner, $config, $castTypeMapper, $writer);
                    } catch (\Throwable $e) {
                        $this->error('Generation failed: '.$e->getMessage());
                    }
                    $lastRun = time();
                    $lastFiles = $files;
                }

                Sleep::sleep(1);
            }

            return self::SUCCESS;
        }

        return $this->runGeneration($classScanner, $config, $castTypeMapper, $writer);
    }

    protected function runGeneration(ClassScanner $classScanner, array $config, CastTypeMapper $castTypeMapper, $writer): int
    {
        $blocks = [];
        $isVerbose = $this->option('watch') || $this->option('dry-run');

        $outputPath = $config['output']['path'] ?? resource_path('js/types/generated.ts');
        if (isset($config['output']['split']) && $config['output']['split']) {
            $outputPath = dirname($outputPath).'/'.pathinfo($outputPath, PATHINFO_FILENAME);
        }

        if (! $this->option('dry-run')) {
            $preHooks = $config['hooks']['pre_generate'] ?? [];
            if (! empty($preHooks)) {
                $this->runHooks($preHooks, $outputPath);
            }
        }

        $enums = [];
        $requests = [];
        $resources = [];
        $models = [];

        // 1. Scan Enums
        $enumPath = $config['paths']['enums'] ?? null;
        if ($enumPath && is_dir($enumPath)) {
            $enums = $classScanner->scan([$enumPath], $config['scan_mode'] ?? 'attribute', filter: 'enum');
        }

        // 2. Scan Form Requests
        $requestPath = $config['paths']['form_requests'] ?? null;
        if ($requestPath && is_dir($requestPath)) {
            $requests = $classScanner->scan([$requestPath], $config['scan_mode'] ?? 'attribute');
        }

        // 2.5 Scan API Resources
        $resourcePath = $config['paths']['resources'] ?? null;
        if ($resourcePath && is_dir($resourcePath)) {
            $resources = $classScanner->scan([$resourcePath], $config['scan_mode'] ?? 'attribute');
        }

        // 3. Scan Models
        $modelPath = $config['paths']['models'] ?? app_path('Models');
        if (is_dir($modelPath)) {
            $models = $classScanner->scan([$modelPath], $config['scan_mode'] ?? 'attribute');
        }

        $totalCount = count($enums) + count($requests) + count($resources) + count($models);
        $bar = null;

        if (! $isVerbose && $totalCount > 0) {
            $bar = $this->output->createProgressBar($totalCount);
            $bar->start();
        }

        // Process Enums
        if ($enums !== []) {
            $enumGenerator = new EnumGenerator($config);
            foreach ($enums as $enum) {
                if ($isVerbose) {
                    $this->line("  ✓ enum {$enum}");
                }
                $blocks[] = [
                    'category' => 'Enums',
                    'content' => $enumGenerator->generate($enum),
                ];
                if ($bar) {
                    $bar->advance();
                }
            }
        }

        // Process Form Requests
        if ($requests !== []) {
            $formRequestGenerator = new FormRequestGenerator(
                new RuleToTypeMapper,
                new RuleTree,
                $config,
            );
            foreach ($requests as $request) {
                if ($isVerbose) {
                    $this->line("  ✓ request {$request}");
                }
                $blocks[] = [
                    'category' => 'Requests',
                    'content' => $formRequestGenerator->generate($request),
                ];
                if ($bar) {
                    $bar->advance();
                }
            }
        }

        // Process API Resources
        if ($resources !== []) {
            $resourceGenerator = new ResourceGenerator($castTypeMapper, $config);
            foreach ($resources as $resource) {
                if ($isVerbose) {
                    $this->line("  ✓ resource {$resource}");
                }
                $blocks[] = [
                    'category' => 'Resources',
                    'content' => $resourceGenerator->generate($resource),
                ];
                if ($bar) {
                    $bar->advance();
                }
            }
        }

        // Process Models
        $modelBlocks = [];
        if (is_dir($modelPath)) {
            $relationDetector = new RelationDetector;
            $relationResolver = new RelationResolver($relationDetector);
            $modelGenerator = new ModelGenerator($castTypeMapper, $relationResolver, $config);

            // BFS queue with cycle detection
            $queue = array_values($models);
            $seen = array_flip($queue);

            while ($queue !== []) {
                $modelClass = array_shift($queue);
                if ($isVerbose) {
                    $this->line("  ✓ model {$modelClass}");
                }

                $result = $modelGenerator->generate($modelClass);
                $modelBlocks[] = [
                    'category' => 'Models',
                    'content' => $result['block'],
                ];

                // Add any newly-discovered related models to the queue
                foreach ($result['discovered'] as $discoveredClass) {
                    if (! isset($seen[$discoveredClass]) && class_exists($discoveredClass)) {
                        $seen[$discoveredClass] = true;
                        $queue[] = $discoveredClass;
                        if ($isVerbose) {
                            $this->line("    ↳ discovered {$discoveredClass}");
                        } elseif ($bar) {
                            $bar->setMaxSteps($bar->getMaxSteps() + 1);
                        }
                    }
                }

                if ($bar) {
                    $bar->advance();
                }
            }
        }

        if ($bar) {
            $bar->finish();
            $this->line(''); // empty line after progress bar finish
        }

        // Assemble: enums -> form requests -> models
        $allBlocks = [...$blocks, ...$modelBlocks];

        if ($modelBlocks !== [] && ($config['relations']['wrap_with_relation'] ?? true)) {
            array_unshift($allBlocks, [
                'category' => 'Support',
                'content' => 'export type Relation<T> = T;',
            ]);
        }

        if ($allBlocks === []) {
            $this->warn('No classes found. Did you add the #[TypeScript] attribute?');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line("\n".implode("\n\n", array_column($allBlocks, 'content')));

            return self::SUCCESS;
        }

        $path = $writer->write($allBlocks);
        $this->info("\nWritten to: {$path}");

        if (! $this->option('dry-run')) {
            $postHooks = $config['hooks']['post_generate'] ?? [];
            if (! empty($postHooks)) {
                $this->runHooks($postHooks, $path);
            }
        }

        return self::SUCCESS;
    }

    protected function runHooks(array $commands, string $filePath): void
    {
        foreach ($commands as $command) {
            $cmd = str_replace('{file}', $filePath, $command);

            try {
                if (class_exists(Process::class)) {
                    $process = Process::fromShellCommandline($cmd);
                    $process->run();
                    if (! $process->isSuccessful()) {
                        $this->warn("Hook failed: {$cmd}\nError: ".$process->getErrorOutput());
                    }
                } else {
                    shell_exec($cmd);
                }
            } catch (\Throwable $e) {
                $this->warn("Hook failed to execute: {$cmd}\nException: ".$e->getMessage());
            }
        }
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
