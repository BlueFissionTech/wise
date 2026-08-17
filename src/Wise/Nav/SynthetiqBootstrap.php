<?php

namespace BlueFission\Wise\Nav;

use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\Memory\MemoryAdapterInterface;
use BlueFission\SynthetIQ\Training\RouteTrainer;
use BlueFission\Automata\Language\{Interpreter, Grammar, StemmerLemmatizer, Walker};
use BlueFission\Automata\Analysis\KeywordTopicAnalyzer;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;
use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Wise\Sys\DirectoryManager;
use BlueFission\Wise\Sys\FileSystemManager;

class SynthetiqBootstrap
{
    public static function fromVendorSampleConfigs(
        ?string $basePath = null,
        ?string $modelPath = null,
        ?MemoryAdapterInterface $memoryAdapter = null,
        ?callable $progress = null
    ): SynthetiqProxy
    {
        $root = dirname(__DIR__, 3);
        $configPath = $basePath ?? $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bluefission' . DIRECTORY_SEPARATOR . 'synthetiq' . DIRECTORY_SEPARATOR . 'sample_configs';

        if (!DirectoryManager::pathExists($configPath)) {
            throw new \RuntimeException('Synthetiq sample configs not found at: ' . $configPath);
        }

        self::emitProgress($progress, 'configs', 'Loading Synthetiq configs...');

        $skills = $configPath . DIRECTORY_SEPARATOR . 'skills.php';
        if (FileSystemManager::pathExists($skills)) {
            require $skills;
        }

        $dialogue = require $configPath . DIRECTORY_SEPARATOR . 'dialogue.php';
        $intentBoosts = require $configPath . DIRECTORY_SEPARATOR . 'intent_boosts.php';
        $grammar = require $configPath . DIRECTORY_SEPARATOR . 'grammar.php';
        $tokens = require $configPath . DIRECTORY_SEPARATOR . 'tokens.php';
        $documenter = require $configPath . DIRECTORY_SEPARATOR . 'documenter.php';
        $dialogue = Arr::merge($dialogue, WiseSynthetiqSamples::dialogue());
        $intentBoosts = Arr::merge($intentBoosts, WiseSynthetiqSamples::intentBoosts());

        return self::fromConfig([
            'dialogue' => $dialogue,
            'intent_boosts' => $intentBoosts,
            'grammar' => $grammar,
            'tokens' => $tokens,
            'documenter' => $documenter,
            'memory_adapter' => $memoryAdapter,
            'model_path' => $modelPath ?? $root . DIRECTORY_SEPARATOR . 'artifacts' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'synthetiq',
            'progress' => $progress,
        ]);
    }

    public static function fromConfig(array $config): SynthetiqProxy
    {
        $dialogue = $config['dialogue'] ?? [];
        $intentBoosts = $config['intent_boosts'] ?? [];
        $grammar = $config['grammar'] ?? ['rules' => [], 'commands' => []];
        $tokens = $config['tokens'] ?? [];
        $documenter = $config['documenter'] ?? null;
        $progress = $config['progress'] ?? null;

        if (!$documenter) {
            throw new \InvalidArgumentException('documenter is required for Synthetiq bootstrap.');
        }

        $modelDir = $config['model_path'] ?? (dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'artifacts' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'synthetiq');
        $modelDir = rtrim($modelDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        DirectoryManager::ensurePath($modelDir);

        $modelFile = self::resolveModelPath($modelDir, KeywordTopicAnalyzer::class);
        if (FileSystemManager::pathExists($modelFile)) {
            self::emitProgress($progress, 'model', 'Loading cached topic model...', ['cache' => true]);
        } else {
            self::emitProgress($progress, 'model', 'Preparing topic model cache...', ['cache' => false]);
        }

        self::emitProgress($progress, 'interpreter', 'Initializing language interpreter...');
        $interpreter = new Interpreter(
            new Grammar(
                new StemmerLemmatizer(),
                $grammar['rules'] ?? [],
                $grammar['commands'] ?? [],
                $tokens
            ),
            $documenter,
            new Walker()
        );

        self::emitProgress($progress, 'analyzer', 'Preparing intent analyzer...');
        $analyzer = new KeywordTopicAnalyzer(new NaiveBayesTextClassification, $modelDir);
        $intentModelPath = $modelDir . 'intent_naive_bayes.phpml';
        $routeStatePath = $modelDir . 'route_training_state.json';
        $routeExtra = [
            'grammar' => $grammar,
            'tokens' => $tokens,
            'routes' => $config['routes'] ?? [],
            'intent_keywords' => $config['intent_keywords'] ?? [],
        ];

        try {
            $ai = new SynthetIQ(
                $interpreter,
                $analyzer,
                routerOptions: [
                    'naive_bayes' => [
                        'model_path' => $intentModelPath,
                        'cache_dir' => $modelDir,
                        'cache_key' => RouteTrainer::cacheKey($dialogue, $intentBoosts, $routeExtra),
                    ],
                ]
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException('Synthetiq runtime is unavailable: ' . $e->getMessage(), 0, $e);
        }

        $memoryAdapter = $config['memory_adapter'] ?? null;
        if ($memoryAdapter instanceof MemoryAdapterInterface) {
            $ai->setMemoryAdapter($memoryAdapter);
        }

        [$routeState, $routeCacheHit] = self::routeState(
            $routeStatePath,
            $dialogue,
            $intentBoosts,
            $routeExtra
        );
        self::emitProgress(
            $progress,
            'routes',
            $routeCacheHit ? 'Loading cached intent routes...' : 'Training intent routes...',
            ['cache' => $routeCacheHit, 'cache_key' => $routeState['cache_key']]
        );
        RouteTrainer::apply($ai, $routeState, self::routeProgress($progress, $routeCacheHit));

        self::emitProgress($progress, 'finalize', 'Finalizing navigator...');
        $proxy = new SynthetiqProxy($ai);

        $extraKeywords = $config['intent_keywords'] ?? [];
        foreach ($extraKeywords as $type => $data) {
            $keywords = $data['keywords'] ?? [];
            $priority = $data['priority'] ?? null;
            if (!is_array($keywords)) {
                $keywords = [$keywords];
            }
            $proxy->addIntentKeywords($type, $keywords, $priority);
        }

        $extraRoutes = $config['routes'] ?? [];
        foreach ($extraRoutes as $route) {
            if (!is_array($route) || count($route) < 2) {
                continue;
            }
            $statement = (string)$route[0];
            $type = (string)$route[1];
            $to = $route[2] ?? [];
            $proxy->addRoute($statement, $type, $to);
        }

        return $proxy;
    }

    private static function routeState(
        string $path,
        array $dialogue,
        array $intentBoosts,
        array $extra
    ): array
    {
        if (FileSystemManager::pathExists($path)) {
            try {
                $state = RouteTrainer::loadState($path);
                if (RouteTrainer::stateMatches($state, $dialogue, $intentBoosts, $extra)) {
                    return [$state, true];
                }
            } catch (\Throwable $e) {
                // A stale or partial cache is rebuilt from the configured source data.
            }
        }

        $state = RouteTrainer::compile($dialogue, $intentBoosts, $extra);
        RouteTrainer::saveState($state, $path);

        return [$state, false];
    }

    private static function routeProgress(?callable $progress, bool $cacheHit): ?callable
    {
        if (!$progress) {
            return null;
        }

        return static function (array $event) use ($progress, $cacheHit): void {
            self::emitProgress($progress, 'routes', 'Preparing intent routes...', [
                'cache' => $cacheHit,
                'sub_stage' => $event['stage'] ?? null,
                'sub_current' => $event['current'] ?? 0,
                'sub_total' => $event['total'] ?? 0,
                'intent' => $event['intent'] ?? null,
            ]);
        };
    }

    private static function resolveModelPath(string $modelDir, string $analyzerClass): string
    {
        $class = new \ReflectionClass($analyzerClass);
        $modelName = Str::snake($class->getShortName());
        return $modelDir . $modelName . '.phpml';
    }

    private static function emitProgress(?callable $progress, string $stage, string $message, array $meta = []): void
    {
        if (!$progress) {
            return;
        }

        $payload = array_merge(['stage' => $stage, 'message' => $message], $meta);
        $progress($stage, $message, $payload);
    }

}
