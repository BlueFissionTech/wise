<?php

namespace BlueFission\Wise\Nav;

use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\Intents\IntelligenceRouter;
use BlueFission\SynthetIQ\Intents\Classifier as SynthetiqIntentClassifier;
use BlueFission\SynthetIQ\Memory\MemoryAdapterInterface;
use BlueFission\Automata\Language\{Interpreter, Grammar, StemmerLemmatizer, Walker};
use BlueFission\Automata\Analysis\KeywordTopicAnalyzer;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;
use BlueFission\Automata\Context;
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
        $ai = new SynthetIQ($interpreter, $analyzer);
        self::stabilizePredictors($ai);

        $memoryAdapter = $config['memory_adapter'] ?? null;
        if ($memoryAdapter instanceof MemoryAdapterInterface) {
            $ai->setMemoryAdapter($memoryAdapter);
        }

        $intentModelPath = $modelDir . 'intent_naive_bayes.phpml';
        $cacheKey = self::buildIntentCacheKey($dialogue, $intentBoosts, $config);
        self::configureIntentRouter($ai, [
            'naive_bayes' => [
                'model_path' => $intentModelPath,
                'cache_dir' => $modelDir,
                'cache_key' => $cacheKey,
            ],
        ]);

        $intentCacheHit = FileSystemManager::pathExists($intentModelPath);
        self::emitProgress($progress, 'routes', 'Training intent routes...', ['cache' => $intentCacheHit]);
        self::trainRoutes($ai, $dialogue, $intentBoosts, $progress);
        self::warmIntentRouter($ai);

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

    private static function trainRoutes(SynthetIQ $ai, array $dialogue, array $intentBoosts = [], ?callable $progress = null): void
    {
        $stopwords = ['how', 'what', 'is', 'the', 'a', 'an', 'to', 'for', 'on', 'in'];
        $total = 0;
        foreach ($dialogue as $info) {
            $phrases = $info[1] ?? [];
            $total += 1;
            if (is_array($phrases)) {
                $total += count($phrases);
            }
        }
        $current = 0;
        $emitProgress = function () use ($progress, &$current, $total) {
            if (!$progress || $total <= 0) {
                return;
            }

            self::emitProgress($progress, 'routes', 'Training intent routes...', [
                'sub_current' => $current,
                'sub_total' => $total,
            ]);
        };

        foreach ($dialogue as $category => $info) {
            $current++;
            $boost = $intentBoosts[$category] ?? [];
            $keywords = $info[2] ?? [];
            if (!empty($boost['keywords'])) {
                $keywords = array_merge($keywords, $boost['keywords']);
            }
            $exclude = $boost['exclude'] ?? [];
            $keywords = self::normalizeKeywords($keywords, array_merge($stopwords, $exclude));
            $priorityBase = $boost['priority'] ?? null;
            $ai->addIntentKeywords($category, $keywords, $priorityBase);
            if ($current % 10 === 0 || $current === $total) {
                $emitProgress();
            }

            foreach ($info[1] as $statement) {
                $ai->addRoute($statement, $category, $info[0]);
                $current++;
                if ($current % 10 === 0 || $current === $total) {
                    $emitProgress();
                }
            }
        }

        $emitProgress();
    }

    private static function normalizeKeywords(array $keywords, array $exclude = []): array
    {
        $excludeSet = [];
        foreach ($exclude as $value) {
            $excludeSet[strtolower(trim((string)$value))] = true;
        }

        $normalized = [];
        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim((string)$keyword));
            if ($keyword === '' || isset($excludeSet[$keyword])) {
                continue;
            }
            $normalized[$keyword] = true;
        }

        return array_keys($normalized);
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

    private static function buildIntentCacheKey(array $dialogue, array $intentBoosts, array $config): string
    {
        $payload = [
            'dialogue' => $dialogue,
            'intent_boosts' => $intentBoosts,
            'grammar' => $config['grammar'] ?? [],
            'tokens' => $config['tokens'] ?? [],
            'routes' => $config['routes'] ?? [],
            'intent_keywords' => $config['intent_keywords'] ?? [],
        ];

        $normalized = self::normalizeCachePayload($payload);
        return sha1(json_encode($normalized));
    }

    private static function normalizeCachePayload($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        if ($isAssoc) {
            ksort($value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = self::normalizeCachePayload($item);
        }

        return $normalized;
    }

    private static function configureIntentRouter(SynthetIQ $ai, array $options): void
    {
        $matcher = self::readProtectedProperty($ai, '_matcher');
        if (!$matcher) {
            return;
        }

        $analyzer = self::readProtectedProperty($matcher, '_intentAnalyzer');
        if (!$analyzer) {
            return;
        }

        $router = null;
        if (class_exists(IntelligenceRouter::class)) {
            $router = new IntelligenceRouter($analyzer, $matcher, $options);
        } elseif (class_exists(SynthetiqIntentClassifier::class)) {
            $router = new SynthetiqIntentClassifier($analyzer);
            self::writeProtectedProperty($router, '_matcher', $matcher);
        }

        if (!$router) {
            return;
        }

        self::writeProtectedProperty($ai, '_intentClassifier', $router);
    }

    private static function warmIntentRouter(SynthetIQ $ai): void
    {
        $router = self::readProtectedProperty($ai, '_intentClassifier');
        if (!$router) {
            return;
        }

        try {
            $router->score('bootstrap', new Context());
        } catch (\Throwable $e) {
            // ignore warmup errors; they will surface on real input
        }
    }

    private static function stabilizePredictors(SynthetIQ $ai): void
    {
        $predictor = self::readProtectedProperty($ai, '_predictor');
        if (!is_object($predictor) || self::predictorWorks($predictor)) {
            return;
        }

        $safePredictor = self::nullPredictor();
        self::writeProtectedProperty($ai, '_predictor', $safePredictor);
        self::writeProtectedProperty($ai, '_learningModel', null);

        $selector = self::readProtectedProperty($ai, '_responseSelector');
        if (is_object($selector)) {
            self::writeProtectedProperty($selector, '_predictor', $safePredictor);
        }
    }

    private static function predictorWorks(object $predictor): bool
    {
        if (!method_exists($predictor, 'addSentence')) {
            return true;
        }

        try {
            $class = get_class($predictor);
            $probe = new $class();
            $probe->addSentence('wise bootstrap predictor probe');

            if (method_exists($probe, 'predictNextWords')) {
                $probe->predictNextWords('wise');
            } elseif (method_exists($probe, 'predictNextWord')) {
                $probe->predictNextWord('wise');
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function nullPredictor(): object
    {
        return new class {
            public function addSentence(string $sentence): void
            {
            }

            public function predictBeginning(): string
            {
                return '';
            }

            public function predictNextWords(string $input): array
            {
                return [];
            }

            public function predictNextWord(string $input): ?string
            {
                return null;
            }
        };
    }

    private static function readProtectedProperty(object $object, string $property)
    {
        try {
            $reflection = new \ReflectionObject($object);
            if (!$reflection->hasProperty($property)) {
                return null;
            }
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            return $prop->getValue($object);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function writeProtectedProperty(object $object, string $property, $value): void
    {
        try {
            $reflection = new \ReflectionObject($object);
            if (!$reflection->hasProperty($property)) {
                return;
            }
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($object, $value);
        } catch (\Throwable $e) {
            return;
        }
    }
}
