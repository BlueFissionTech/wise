<?php

namespace BlueFission\Wise\Nav;

use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Val;

class SynthetiqProxy implements INavigator
{
    protected object $_synthetiq;

    public function __construct(object $synthetiq)
    {
        if (!method_exists($synthetiq, 'processInput')) {
            throw new \InvalidArgumentException('Synthetiq engine must implement processInput().');
        }

        $this->_synthetiq = $synthetiq;
    }

    public function process(string $input): string
    {
        return (string)$this->_synthetiq->processInput($input);
    }

    public function handoff(string $input, array $context = []): SynthetiqContextHandoff
    {
        $source = 'synthetiq.processInput';
        try {
            if (method_exists($this->_synthetiq, 'processInputEnvelope')) {
                $source = 'synthetiq.processInputEnvelope';
                $result = $this->_synthetiq->processInputEnvelope($input);
            } else {
                $result = $this->_synthetiq->processInput($input);
            }
        } catch (\Throwable $e) {
            return SynthetiqContextHandoff::failure($e->getMessage(), Arr::merge($context, [
                'diagnostics' => [
                    'source' => $source,
                    'exception' => get_class($e),
                ],
            ]));
        }

        $payload = Arr::is($result) ? $result : ['response' => (string)$result];
        $handoff = Arr::merge($payload, $context);
        $handoff['handoff_status'] = $handoff['handoff_status'] ?? SynthetiqContextHandoff::STATUS_ACCEPTED;
        $handoff['provenance'] = Arr::merge(
            Arr::hasKey($handoff, 'provenance') && Arr::is($handoff['provenance']) ? $handoff['provenance'] : [],
            ['source' => $source]
        );
        $handoff['diagnostics'] = Arr::merge(
            Arr::hasKey($handoff, 'diagnostics') && Arr::is($handoff['diagnostics']) ? $handoff['diagnostics'] : [],
            ['response_preview' => self::responsePreview($payload)]
        );

        return new SynthetiqContextHandoff($handoff);
    }

    public function addRoute(string $statement, string $type, array|string $to = []): void
    {
        if (!method_exists($this->_synthetiq, 'addRoute')) {
            throw new \RuntimeException('Synthetiq does not support addRoute.');
        }

        $this->_synthetiq->addRoute($statement, $type, $to);
    }

    public function addIntentKeywords(string $type, array $keywords, ?int $priorityBase = null): void
    {
        if (!method_exists($this->_synthetiq, 'addIntentKeywords')) {
            throw new \RuntimeException('Synthetiq does not support addIntentKeywords.');
        }

        $this->_synthetiq->addIntentKeywords($type, $keywords, $priorityBase);
    }

    private static function responsePreview(array $payload): ?string
    {
        $response = $payload['response'] ?? $payload['output'] ?? $payload['message'] ?? null;
        if (!Val::is($response)) {
            return null;
        }

        return Str::make((string)$response)->trim()->truncate(120)->val();
    }
}
