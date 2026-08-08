<?php

namespace BlueFission\Wise\Nav;

final class WiseSynthetiqSamples
{
    public static function dialogue(): array
    {
        return [
            'wise.command.discovery' => [
                ['wise.command.discovery.response'],
                [
                    'what commands can I use?',
                    'how do I list resources?',
                    'show available resources',
                    'list all resources',
                    'list all commands',
                    'help me use wise',
                ],
                ['wise', 'command', 'commands', 'resource', 'resources', 'help'],
            ],
            'wise.command.discovery.response' => [
                [],
                [
                    'Use `list all resources`, `list all commands`, or `help` to inspect available Wise commands.',
                ],
                ['wise', 'command', 'resources', 'help'],
            ],
            'wise.weather.question' => [
                ['wise.weather.response'],
                [
                    'what is the weather?',
                    'what is the weather today?',
                    'how is the weather?',
                    'what is the weather in a city?',
                    'show the weather for a location',
                ],
                ['weather', 'forecast', 'temperature', 'location'],
            ],
            'wise.weather.response' => [
                [],
                [
                    'Use `get the weather in <location>` to ask Wise for weather through the weather resource.',
                ],
                ['weather', 'forecast', 'location'],
            ],
            'wise.todo.discovery' => [
                ['wise.todo.discovery.response'],
                [
                    'show my lists',
                    'show all lists',
                    'get lists',
                    'list todos',
                    'show todo lists',
                ],
                ['todo', 'todos', 'list', 'lists', 'task', 'tasks'],
            ],
            'wise.todo.discovery.response' => [
                [],
                [
                    'Use `list todo` to inspect todo lists, or `add <item> to todo` when a todo resource is configured.',
                ],
                ['todo', 'todos', 'list', 'task'],
            ],
        ];
    }

    public static function intentBoosts(): array
    {
        return [
            'wise.command.discovery' => [
                'priority' => 25,
                'train_statements' => true,
                'keywords' => [
                    'wise command',
                    'wise commands',
                    'list all resources',
                    'list all commands',
                    'available resources',
                    'help',
                ],
            ],
            'wise.weather.question' => [
                'priority' => 25,
                'train_statements' => true,
                'keywords' => [
                    'weather',
                    'forecast',
                    'weather today',
                    'weather in',
                ],
            ],
            'wise.todo.discovery' => [
                'priority' => 24,
                'train_statements' => true,
                'keywords' => [
                    'todo',
                    'todos',
                    'show my lists',
                    'show all lists',
                    'get lists',
                    'list todos',
                ],
            ],
        ];
    }
}
