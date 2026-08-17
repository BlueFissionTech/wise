<?php

namespace BlueFission\Wise\Int;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\AgentSession;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\Orchestrator;
use BlueFission\Automata\LLM\Agent\State\AgentState;
use BlueFission\DevElation as Dev;
use BlueFission\Func;
use BlueFission\Obj;
use BlueFission\Str;

class AutomataOrchestrator extends Obj implements IOrchestrator
{
    private $factory;
    private bool $enabled;

    public function __construct(?callable $factory = null, bool $enabled = true)
    {
        parent::__construct();
        $this->factory = $factory ?? static fn (array $config): Orchestrator => new Orchestrator($config);
        $this->enabled = $enabled;
    }

    public function available(): bool
    {
        return $this->enabled
            && class_exists(Orchestrator::class)
            && class_exists(AgentSession::class)
            && class_exists(AgentState::class)
            && Func::isCallable($this->factory);
    }

    public function orchestrate(OrchestrationRequest $request): OrchestrationOutcome
    {
        if (!$this->available()) {
            return OrchestrationOutcome::unavailable();
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
        ])->toArray();
        $config = Arr::make($request->config())->merge([
            'pattern' => $request->pattern() ?: OrchestrationConfig::SEQUENTIAL,
            'workers' => $request->workers(),
        ])->toArray();

        Dev::do('wise.int.orchestration.before', [$request, $input, $config]);

        try {
            $orchestrator = ($this->factory)($config);
            if (!($orchestrator instanceof Orchestrator)) {
                return OrchestrationOutcome::failure('invalid_automata_orchestrator');
            }
            $data = $orchestrator->run($input)->toArray();
        } catch (\Throwable $exception) {
            $outcome = OrchestrationOutcome::failure('automata_orchestration_failed', [
                'exception' => $exception::class,
            ]);
            Dev::do('wise.int.orchestration.failed', [$outcome]);
            return $outcome;
        }

        $outcome = new OrchestrationOutcome(Arr::make($data)->merge([
            'persona' => $persona,
            'session_id' => $session->id(),
            'state' => $state->snapshot(),
        ])->toArray());
        Dev::do('wise.int.orchestration.after', [$outcome]);

        return $outcome;
    }
}
