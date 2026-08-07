<?php

namespace BlueFission\Wise\Nav;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

class SynthetiqContextHandoff extends Obj
{
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CLARIFICATION = 'clarification';
    public const STATUS_FAILURE = 'failure';

    private const HANDOFF_FIELDS = [
        'conversation_profile',
        'context_refs',
        'current_intent',
        'unresolved_questions',
        'confidence',
        'declared_capabilities',
        'provenance',
        'handoff_status',
        'diagnostics',
        'output_id',
    ];

    private const WISE_ENVELOPE_FIELDS = [
        'invocation_id',
        'session_id',
        'scope',
        'safety_policy',
        'execution_state',
        'waiting_state',
        'completed_at',
        'exit_status',
    ];

    private array $data;

    public function __construct(array $data = [])
    {
        parent::__construct();
        $this->data = self::normalize($data);
    }

    public static function accepted(array $data = []): self
    {
        $data['handoff_status'] = self::STATUS_ACCEPTED;

        return new self($data);
    }

    public static function failure(string $message, array $data = []): self
    {
        $diagnostics = Arr::hasKey($data, 'diagnostics') && Arr::is($data['diagnostics'])
            ? $data['diagnostics']
            : [];
        $diagnostics['message'] = $message;

        $data['diagnostics'] = $diagnostics;
        $data['handoff_status'] = self::STATUS_FAILURE;
        $data['execution_state'] = self::stringOrDefault($data, 'execution_state', 'failed');
        $data['exit_status'] = self::integerOrDefault($data, 'exit_status', 1);

        return new self($data);
    }

    public static function deterministicFixture(array $overrides = []): self
    {
        $fixture = [
            'conversation_profile' => [
                'id' => 'wise-fixture-profile',
                'mode' => 'route-classification',
            ],
            'context_refs' => [
                [
                    'type' => 'route',
                    'ref' => 'wise.fixture.route-classification',
                ],
            ],
            'current_intent' => 'wise.route.classify',
            'unresolved_questions' => [],
            'confidence' => 1.0,
            'declared_capabilities' => [],
            'provenance' => [
                'source' => 'wise:synthetiq-context-handoff',
                'fixture' => 'route-classification',
            ],
            'handoff_status' => self::STATUS_ACCEPTED,
            'diagnostics' => [],
            'invocation_id' => 'wise-fixture-invocation',
            'session_id' => 'wise-fixture-session',
            'scope' => 'user',
            'safety_policy' => 'side_effect_free',
            'execution_state' => 'completed',
            'waiting_state' => 'none',
            'completed_at' => null,
            'exit_status' => 0,
        ];

        return new self(Arr::merge($fixture, $overrides));
    }

    public static function handoffFieldNames(): array
    {
        return self::HANDOFF_FIELDS;
    }

    public static function wiseEnvelopeFieldNames(): array
    {
        return self::WISE_ENVELOPE_FIELDS;
    }

    public function status(): string
    {
        return (string)$this->data['handoff_status'];
    }

    public function outputId(): string
    {
        return (string)$this->data['output_id'];
    }

    public function isAccepted(): bool
    {
        return $this->status() === self::STATUS_ACCEPTED;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    private static function normalize(array $data): array
    {
        $status = self::statusOrDefault($data, self::STATUS_ACCEPTED);
        $normalized = [
            'conversation_profile' => self::arrayOrDefault($data, 'conversation_profile'),
            'context_refs' => self::arrayValuesOrDefault($data, 'context_refs'),
            'current_intent' => self::nullableString($data, 'current_intent'),
            'unresolved_questions' => self::stringListOrDefault($data, 'unresolved_questions'),
            'confidence' => self::confidenceOrDefault($data, 'confidence'),
            'declared_capabilities' => self::stringListOrDefault($data, 'declared_capabilities'),
            'provenance' => self::arrayOrDefault($data, 'provenance'),
            'handoff_status' => $status,
            'diagnostics' => self::arrayOrDefault($data, 'diagnostics'),
            'output_id' => self::nullableString($data, 'output_id'),
            'invocation_id' => self::nullableString($data, 'invocation_id'),
            'session_id' => self::nullableString($data, 'session_id'),
            'scope' => self::stringOrDefault($data, 'scope', 'global'),
            'safety_policy' => self::stringOrDefault($data, 'safety_policy', 'side_effect_free'),
            'execution_state' => self::stringOrDefault($data, 'execution_state', self::executionStateForStatus($status)),
            'waiting_state' => self::stringOrDefault($data, 'waiting_state', 'none'),
            'completed_at' => self::nullableString($data, 'completed_at'),
            'exit_status' => self::integerOrDefault($data, 'exit_status', self::exitStatusForStatus($status)),
        ];

        if (Str::isEmpty((string)$normalized['output_id'])) {
            $normalized['output_id'] = self::buildOutputId($normalized);
        }

        return $normalized;
    }

    private static function statusOrDefault(array $data, string $default): string
    {
        $status = self::stringOrDefault($data, 'handoff_status', $default);
        $allowed = [
            self::STATUS_ACCEPTED,
            self::STATUS_REJECTED,
            self::STATUS_CLARIFICATION,
            self::STATUS_FAILURE,
        ];

        if (!Arr::has($allowed, $status, true)) {
            return $default;
        }

        return $status;
    }

    private static function executionStateForStatus(string $status): string
    {
        if ($status === self::STATUS_FAILURE || $status === self::STATUS_REJECTED) {
            return 'failed';
        }

        if ($status === self::STATUS_CLARIFICATION) {
            return 'waiting';
        }

        return 'completed';
    }

    private static function exitStatusForStatus(string $status): int
    {
        return $status === self::STATUS_ACCEPTED ? 0 : 1;
    }

    private static function arrayOrDefault(array $data, string $field): array
    {
        if (!Arr::hasKey($data, $field) || Val::isNull($data[$field])) {
            return [];
        }

        if (Arr::is($data[$field])) {
            return $data[$field];
        }

        return [$data[$field]];
    }

    private static function arrayValuesOrDefault(array $data, string $field): array
    {
        return Arr::make(self::arrayOrDefault($data, $field))->values()->val();
    }

    private static function stringListOrDefault(array $data, string $field): array
    {
        return Arr::make(self::arrayOrDefault($data, $field))
            ->filter(fn($value) => Val::is($value) && !Str::isEmpty((string)$value))
            ->map(fn($value) => Str::make((string)$value)->trim()->val())
            ->values()
            ->unique()
            ->val();
    }

    private static function confidenceOrDefault(array $data, string $field): ?float
    {
        if (!Arr::hasKey($data, $field) || Val::isNull($data[$field])) {
            return null;
        }

        $confidence = Num::make((float)$data[$field])->max(0.0);

        return (float)Num::make($confidence)->min(1.0);
    }

    private static function nullableString(array $data, string $field): ?string
    {
        if (!Arr::hasKey($data, $field) || Val::isNull($data[$field])) {
            return null;
        }

        $value = Str::make((string)$data[$field])->trim()->val();

        return Str::isEmpty($value) ? null : $value;
    }

    private static function stringOrDefault(array $data, string $field, string $default): string
    {
        $value = self::nullableString($data, $field);

        return Val::isNull($value) ? $default : $value;
    }

    private static function integerOrDefault(array $data, string $field, int $default): int
    {
        if (!Arr::hasKey($data, $field) || Val::isNull($data[$field])) {
            return $default;
        }

        return (int)$data[$field];
    }

    private static function buildOutputId(array $data): string
    {
        $seed = $data;
        $seed['output_id'] = null;

        return 'wise-out-' . Str::sub(sha1(json_encode($seed, JSON_UNESCAPED_SLASHES)), 0, 16);
    }
}
