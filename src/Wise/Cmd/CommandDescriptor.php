<?php

namespace BlueFission\Wise\Cmd;

use BlueFission\Arr;
use BlueFission\Flag;
use BlueFission\Obj;
use BlueFission\Str;

final class CommandDescriptor extends Obj
{
    private Str $identifier;
    private Str $route;
    private ?Str $resource;
    private ?Str $action;
    private Str $summary;
    private Arr $argumentShape;
    private Flag $confirmationRequired;
    private Arr $requiredCapabilities;
    private Flag $available;
    private ?Str $unavailableReason;

    public function __construct(
        string $identifier,
        string $route,
        ?string $resource,
        ?string $action,
        string $summary,
        array $argumentShape = [],
        bool $confirmationRequired = false,
        array $requiredCapabilities = [],
        bool $available = true,
        ?string $unavailableReason = null
    ) {
        parent::__construct();
        $this->identifier = Str::make($identifier)->trim()->lower();
        $this->route = Str::make($route)->trim()->lower();
        $this->resource = Str::isNotEmpty((string)$resource)
            ? Str::make((string)$resource)->trim()->lower()
            : null;
        $this->action = Str::isNotEmpty((string)$action)
            ? Str::make((string)$action)->trim()->lower()
            : null;
        $this->summary = Str::make($summary)->trim();
        $this->argumentShape = Arr::make($argumentShape);
        $this->confirmationRequired = Flag::make($confirmationRequired);
        $this->requiredCapabilities = Arr::make($requiredCapabilities)
            ->map(fn ($capability) => Str::make((string)$capability)->trim()->lower()->val())
            ->filter(fn ($capability) => Str::isNotEmpty((string)$capability))
            ->unique()
            ->sort();
        $this->available = Flag::make($available);
        $this->unavailableReason = Str::isNotEmpty((string)$unavailableReason)
            ? Str::make((string)$unavailableReason)->trim()->lower()
            : null;
    }

    public static function resource(string $command, array $definition = []): self
    {
        $parts = Str::make($command)->trim()->split();
        $action = (string)$parts->shift();
        $resource = (string)$parts->shift();

        return new self(
            CommandPolicy::resource($resource, $action),
            CommandPolicy::RESOURCE,
            $resource,
            $action,
            (string)($definition['summary'] ?? Str::make($action . ' ' . $resource)->capitalize()->val()),
            $definition['argument_shape'] ?? [
                'type' => 'object',
                'additional_properties' => true,
            ],
            (bool)($definition['confirmation_required'] ?? false),
            $definition['required_capabilities'] ?? []
        );
    }

    public static function native(string $command, array $definition = []): self
    {
        $command = Str::make($command)->trim()->lower()->val();

        return new self(
            CommandPolicy::native($command),
            CommandPolicy::NATIVE,
            null,
            $command,
            (string)($definition['summary'] ?? Str::make('Run ' . $command)->capitalize()->val()),
            $definition['argument_shape'] ?? [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            (bool)($definition['confirmation_required'] ?? false),
            $definition['required_capabilities'] ?? []
        );
    }

    public static function script(string $extension, array $definition = []): self
    {
        $extension = Str::make($extension)->trim()->lower()->val();

        return new self(
            CommandPolicy::script($extension),
            CommandPolicy::SCRIPT,
            null,
            'run',
            (string)($definition['summary'] ?? Str::make('Run .' . $extension . ' script')->capitalize()->val()),
            $definition['argument_shape'] ?? [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'required' => true],
                    'arguments' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'standard_input' => ['type' => 'string'],
                ],
            ],
            (bool)($definition['confirmation_required'] ?? false),
            $definition['required_capabilities'] ?? []
        );
    }

    public static function unavailable(string $identifier, string $reason = 'not_discovered'): self
    {
        $parts = Str::make($identifier)->trim()->lower()->split(':');
        $route = (string)$parts->shift();
        $target = (string)$parts->shift();
        $resource = null;
        $action = $target;
        if (Str::match(CommandPolicy::RESOURCE, $route)) {
            $targetParts = Str::make($target)->split('.');
            $resource = (string)$targetParts->shift();
            $action = (string)$targetParts->shift();
        }

        return new self(
            $identifier,
            $route,
            $resource,
            $action,
            'Command is unavailable',
            available: false,
            unavailableReason: $reason
        );
    }

    public function identifier(): string
    {
        return $this->identifier->val();
    }

    public function available(): bool
    {
        return $this->available->isTruthy();
    }

    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier(),
            'route' => $this->route->val(),
            'resource' => $this->resource?->val(),
            'action' => $this->action?->val(),
            'summary' => $this->summary->val(),
            'argument_shape' => $this->argumentShape->toArray(),
            'confirmation_required' => $this->confirmationRequired->isTruthy(),
            'required_capabilities' => $this->requiredCapabilities->toArray(),
            'available' => $this->available(),
            'unavailable_reason' => $this->unavailableReason?->val(),
        ];
    }
}
