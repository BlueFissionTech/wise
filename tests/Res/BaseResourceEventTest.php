<?php

namespace {
    if (!function_exists('store')) {
        function store(string $name, mixed $value = null): mixed
        {
            static $data = [];

            if (func_num_args() === 1) {
                return $data[$name] ?? null;
            }

            $data[$name] = $value;
            return $value;
        }
    }
}

namespace BlueFission\Tests\Res {
    use BlueFission\Behavioral\Behaviors\Behavior;
    use BlueFission\Behavioral\Behaviors\Meta;
    use BlueFission\Wise\Res\BaseResource;
    use PHPUnit\Framework\TestCase;

    final class BaseResourceEventTest extends TestCase
    {
        public function testEmitsResourceOutputEnvelopeWithWaitingState(): void
        {
            $resource = new class() extends BaseResource {
                protected $_name = 'dummy';

                protected function list($args)
                {
                    $this->setOutputMeta([
                        'semantic_metadata' => [
                            'kind' => 'unit-fixture',
                            'confidence' => 0.88,
                        ],
                        'provenance' => [
                            'source' => 'test',
                        ],
                    ]);
                    $this->setExpectedOptions(['yes', 'no'], [
                        'prompt_state' => 'suspended',
                    ]);
                    $this->_response = 'Hello world [yes/no]';
                }
            };

            $outputs = [];
            $waiting = [];

            $resource->when('wise.resource.output', function ($behavior, $meta) use (&$outputs) {
                if ($meta instanceof Meta) {
                    $outputs[] = $meta->data;
                }
            });
            $resource->when('wise.resource.waiting', function ($behavior, $meta) use (&$waiting) {
                if ($meta instanceof Meta) {
                    $waiting[] = $meta->data;
                }
            });

            $resource->handle(new Behavior('list'), []);

            $this->assertCount(1, $outputs);
            $this->assertCount(1, $waiting);

            $output = $outputs[0];
            $this->assertMatchesRegularExpression('/^wise-out-[a-f0-9]{16}$/', $output['output_id']);
            $this->assertSame('dummy', $output['resource']);
            $this->assertSame('dummy', $output['resource_name']);
            $this->assertSame('list', $output['action']);
            $this->assertSame('waiting', $output['status']);
            $this->assertTrue($output['waiting']);
            $this->assertFalse($output['completed']);
            $this->assertFalse($output['repeated']);
            $this->assertSame('Hello world [yes/no]', $output['output']);
            $this->assertSame(['yes', 'no'], $waiting[0]['options']);
            $this->assertSame($output['output_id'], $waiting[0]['output_id']);
            $this->assertSame('suspended', $waiting[0]['prompt_state']);
            $this->assertSame('unit-fixture', $output['semantic_metadata']['kind']);
            $this->assertSame('test', $output['provenance']['source']);
            $this->assertNotFalse(strtotime($output['timestamp']));
        }

        public function testRepeatedOutputUsesSameOutputIdAndRefreshSignal(): void
        {
            $resource = new class() extends BaseResource {
                protected $_name = 'repeatable';

                protected function list($args)
                {
                    $this->_response = 'Same output.';
                }
            };

            $outputs = [];
            $refreshes = [];

            $resource->when('wise.resource.output', function ($behavior, $meta) use (&$outputs) {
                if ($meta instanceof Meta) {
                    $outputs[] = $meta->data;
                }
            });
            $resource->when('wise.resource.output.refresh', function ($behavior, $meta) use (&$refreshes) {
                if ($meta instanceof Meta) {
                    $refreshes[] = $meta->data;
                }
            });

            $resource->handle(new Behavior('list'), []);
            $resource->handle(new Behavior('list'), []);

            $this->assertCount(1, $outputs);
            $this->assertCount(1, $refreshes);
            $this->assertSame($outputs[0]['output_id'], $refreshes[0]['output_id']);
            $this->assertFalse($outputs[0]['repeated']);
            $this->assertTrue($refreshes[0]['repeated']);
            $this->assertSame('completed', $outputs[0]['status']);
            $this->assertTrue($outputs[0]['completed']);
        }

        public function testImmediateSuccessAndInvalidActionExposeCompletedStatus(): void
        {
            $resource = new class() extends BaseResource {
                protected $_name = 'quick';

                protected function list($args)
                {
                    $this->_response = 'Ready.';
                }
            };

            $outputs = [];
            $resource->when('wise.resource.output', function ($behavior, $meta) use (&$outputs) {
                if ($meta instanceof Meta) {
                    $outputs[] = $meta->data;
                }
            });

            $resource->handle(new Behavior('list'), []);
            $resource->handle(new Behavior('unknown'), []);

            $this->assertCount(2, $outputs);
            $this->assertSame('completed', $outputs[0]['status']);
            $this->assertFalse($outputs[0]['waiting']);
            $this->assertTrue($outputs[0]['completed']);
            $this->assertSame('error', $outputs[1]['status']);
            $this->assertSame('invalid_action', $outputs[1]['error']);
            $this->assertSame('unknown', $outputs[1]['action']);
            $this->assertFalse($outputs[1]['waiting']);
            $this->assertTrue($outputs[1]['completed']);
        }
    }
}
