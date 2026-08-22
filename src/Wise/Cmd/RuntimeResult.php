<?php

namespace BlueFission\Wise\Cmd;

use BlueFission\Arr;
use BlueFission\Obj;

final class RuntimeResult extends Obj
{
    private CommandResult $result;
    private Arr $frames;

    public function __construct(CommandResult $result, array $frames = [])
    {
        parent::__construct();
        $this->result = $result;
        $this->frames = Arr::make($frames);
    }

    public static function fromCommandResult(CommandResult $result): self
    {
        $frames = Arr::make([
            new OutputFrame(OutputFrame::STATUS, $result->status(), [
                'exit_code' => $result->exitCode(),
            ]),
        ]);

        if ($result->output() !== null) {
            $frames->push(new OutputFrame(OutputFrame::OUTPUT, $result->output()));
        }
        if (Arr::isNotEmpty($result->diagnostics())) {
            $frames->push(new OutputFrame(OutputFrame::DIAGNOSTIC, $result->diagnostics()));
        }
        if ($result->confirmationRequired()) {
            $frames->push(new OutputFrame(OutputFrame::PROMPT, $result->output(), [
                'continuation_token' => $result->continuationToken(),
            ]));
        }

        return new self($result, $frames->toArray());
    }

    public function result(): CommandResult
    {
        return $this->result;
    }

    public function frames(): array
    {
        return $this->frames->toArray();
    }

    public function toArray(): array
    {
        return [
            'result' => $this->result->toArray(),
            'frames' => $this->frames
                ->map(fn (OutputFrame $frame) => $frame->toArray())
                ->toArray(),
        ];
    }
}
