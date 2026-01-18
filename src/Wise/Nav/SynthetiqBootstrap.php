<?php

namespace BlueFission\Wise\Nav;

use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\Memory\MemoryAdapterInterface;
use BlueFission\Automata\Language\{Interpreter, Grammar, StemmerLemmatizer, Walker};
use BlueFission\Automata\Analysis\KeywordTopicAnalyzer;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;

class SynthetiqBootstrap
{
    public static function fromVendorSampleConfigs(
        ?string $basePath = null,
        ?string $modelPath = null,
        ?MemoryAdapterInterface $memoryAdapter = null
    ): SynthetiqProxy
    {
        $root = dirname(__DIR__, 3);
        $configPath = $basePath ?? $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bluefission' . DIRECTORY_SEPARATOR . 'synthetiq' . DIRECTORY_SEPARATOR . 'sample_configs';

        if (!is_dir($configPath)) {
            throw new \RuntimeException('Synthetiq sample configs not found at: ' . $configPath);
        }

        $skills = $configPath . DIRECTORY_SEPARATOR . 'skills.php';
        if (is_file($skills)) {
            require $skills;
        }

        $dialogue = require $configPath . DIRECTORY_SEPARATOR . 'dialogue.php';
        $intentBoosts = require $configPath . DIRECTORY_SEPARATOR . 'intent_boosts.php';
        $grammar = require $configPath . DIRECTORY_SEPARATOR . 'grammar.php';
        $tokens = require $configPath . DIRECTORY_SEPARATOR . 'tokens.php';
        $documenter = require $configPath . DIRECTORY_SEPARATOR . 'documenter.php';

        return self::fromConfig([
            'dialogue' => $dialogue,
            'intent_boosts' => $intentBoosts,
            'grammar' => $grammar,
            'tokens' => $tokens,
            'documenter' => $documenter,
            'memory_adapter' => $memoryAdapter,
            'model_path' => $modelPath ?? $root . DIRECTORY_SEPARATOR . 'artifacts' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'synthetiq',
        ]);
    }

    public static function fromConfig(array $config): SynthetiqProxy
    {
        $dialogue = $config['dialogue'] ?? [];
        $intentBoosts = $config['intent_boosts'] ?? [];
        $grammar = $config['grammar'] ?? ['rules' => [], 'commands' => []];
        $tokens = $config['tokens'] ?? [];
        $documenter = $config['documenter'] ?? null;

        if (!$documenter) {
            throw new \InvalidArgumentException('documenter is required for Synthetiq bootstrap.');
        }

        $modelDir = $config['model_path'] ?? (dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'artifacts' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'synthetiq');
        if (!is_dir($modelDir)) {
            mkdir($modelDir, 0777, true);
        }

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

        $analyzer = new KeywordTopicAnalyzer(new NaiveBayesTextClassification, $modelDir);
        $ai = new SynthetIQ($interpreter, $analyzer);

        $memoryAdapter = $config['memory_adapter'] ?? null;
        if ($memoryAdapter instanceof MemoryAdapterInterface) {
            $ai->setMemoryAdapter($memoryAdapter);
        }

        self::trainRoutes($ai, $dialogue, $intentBoosts);

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

    private static function trainRoutes(SynthetIQ $ai, array $dialogue, array $intentBoosts = []): void
    {
        $stopwords = ['how', 'what', 'is', 'the', 'a', 'an', 'to', 'for', 'on', 'in'];

        foreach ($dialogue as $category => $info) {
            $boost = $intentBoosts[$category] ?? [];
            $keywords = $info[2] ?? [];
            if (!empty($boost['keywords'])) {
                $keywords = array_merge($keywords, $boost['keywords']);
            }
            $exclude = $boost['exclude'] ?? [];
            $keywords = self::normalizeKeywords($keywords, array_merge($stopwords, $exclude));
            $priorityBase = $boost['priority'] ?? null;
            $ai->addIntentKeywords($category, $keywords, $priorityBase);

            foreach ($info[1] as $statement) {
                $ai->addRoute($statement, $category, $info[0]);
            }
        }
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
}
