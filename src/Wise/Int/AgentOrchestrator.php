<?php

namespace BlueFission\Wise\Int;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Integration\AgentIntegrationContract;
use BlueFission\Automata\LLM\Agent\AgentSession;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\Orchestrator as AutomataOrchestrator;
use BlueFission\Automata\LLM\Agent\State\AgentState;
use BlueFission\DevElation as Dev;
use BlueFission\Func;
use BlueFission\Obj;
use BlueFission\Str;
use Composer\InstalledVersions;

class AgentOrchestrator extends Obj implements IOrchestrator
{
    private $factory;
    private bool $enabled;

    public function __construct(?callable $factory = null, bool $enabled = true)
    {
        parent::__construct();
        $this->factory = $factory ?? static fn (array $config): AutomataOrchestrator => new AutomataOrchestrator($config);
        $this->enabled = $enabled;
    }

    public function available(): bool
    {
        return $this->enabled
            && class_exists(AutomataOrchestrator::class)
            && class_exists(AgentSession::class)
            && class_exists(AgentState::class)
            && Func::isCallable($this->factory);
    }

    public function orchestrate(OrchestrationRequest $request): OrchestrationOutcome
    {
        $envelope = new OrchestrationEnvelope($request, $this->providerIdentity());

        if (!$this->available()) {
            return new OrchestrationOutcome($envelope->outcome([
                'status' => 'unavailable',
                'metadata' => ['reason' => 'orchestration_unavailable'],
            ], [
                'status' => 'unsupported',
                'code' => 'provider_unavailable',
            ]));
        }

        $persona = $request->persona()->toArray();
        $session = new AgentSession($request->sessionId(), [
            'persona' => $persona,
            'context' => $request->context(),
        ]);
        foreach ($request->capabilities() as $capability) {
            $name = Str::make((string)$capability)->trim()->lower()->val();
            if (Str::isNotEmpty($name)) {
                $session->allow($name);
            }
        }

        $state = new AgentState($request->state());
        $state->write(AgentState::RULES, 'persona', $persona);
        $state->write(AgentState::OBSERVATIONS, 'task', $request->task());

        $input = Arr::make($request->context())->merge([
            'task' => $request->task(),
            'persona' => $persona,
            'session' => [
                'id' => $session->id(),
                'capabilities' => $request->capabilities(),
                'context' => $session->context(),
            ],
            'state' => $state->snapshot(),
            'envelope' => $envelope->request(),
        ])->toArray();
        $config = Arr::make($request->config())->merge([
            'pattern' => $request->pattern() ?: OrchestrationConfig::SEQUENTIAL,
            'workers' => $request->workers(),
        ])->toArray();

        Dev::do('wise.int.orchestration.before', [$request, $input, $config]);

        try {
            $orchestrator = ($this->factory)($config);
            if (!($orchestrator instanceof AutomataOrchestrator)) {
                return new OrchestrationOutcome($envelope->outcome([
                    'status' => 'failed',
                    'metadata' => ['reason' => 'invalid_orchestrator'],
                ], [
                    'status' => 'failed',
                    'code' => 'invalid_provider',
                ]));
            }
            $data = $orchestrator->run($input)->toArray();
        } catch (\Throwable $exception) {
            $outcome = new OrchestrationOutcome($envelope->outcome([
                'status' => 'failed',
                'metadata' => [
                    'reason' => 'orchestration_failed',
                    'exception' => $exception::class,
                ],
            ], [
                'status' => 'failed',
                'code' => 'provider_exception',
            ], [[
                'code' => 'provider_exception',
                'exception_type' => $exception::class,
            ]]));
            Dev::do('wise.int.orchestration.failed', [$outcome]);
            return $outcome;
        }

        $hostResult = Arr::make($data)->merge([
            'persona' => $persona,
            'session_id' => $session->id(),
            'state' => $state->snapshot(),
        ])->toArray();
        $outcome = new OrchestrationOutcome($envelope->outcome($hostResult, [
            'status' => $data['status'] ?? null,
            'code' => $data['code'] ?? ($data['reason_code'] ?? null),
            'evidence' => Arr::is($data['evidence'] ?? null) ? $data['evidence'] : [],
            'metadata' => Arr::is($data['metadata'] ?? null) ? $data['metadata'] : [],
        ], Arr::is($data['diagnostics'] ?? null) ? $data['diagnostics'] : []));
        Dev::do('wise.int.orchestration.after', [$outcome]);

        return $outcome;
    }

    private function providerIdentity(): array
    {
        $version = null;
        if (class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('bluefission/automata')) {
            $version = InstalledVersions::getPrettyVersion('bluefission/automata');
        }

        $contract = [
            'name' => 'wise.agent.integration',
            'version' => null,
            'features' => [],
        ];
        if (class_exists(AgentIntegrationContract::class)) {
            $contract['version'] = AgentIntegrationContract::VERSION;
            $contract['features'] = [
                AgentIntegrationContract::FEATURE_ORCHESTRATION,
                AgentIntegrationContract::FEATURE_SESSION,
            ];
        }

        return [
            'name' => 'automata',
            'source' => 'bluefission/automata',
            'version' => $version,
            'contract' => $contract,
        ];
    }
}
