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
        public function testEmitsUniqueOutputAndRefreshSignals(): void
        {
            $resource = new class() extends BaseResource {
                protected $_name = 'dummy';

                protected function list($args)
                {
                    $this->setExpectedOptions(['yes', 'no']);
                    $this->_response = 'Hello world [yes/no]';
                }
            };

            $outputs = [];
            $refreshes = [];
            $waiting = [];

            $resource->when('wise.resource.output', function ($behavior, $meta) use (&$outputs) {
                if ($meta instanceof Meta) {
                    $outputs[] = $meta->data['output'] ?? null;
                }
            });
            $resource->when('wise.resource.output.refresh', function ($behavior, $meta) use (&$refreshes) {
                if ($meta instanceof Meta) {
                    $refreshes[] = $meta->data['output'] ?? null;
                }
            });
            $resource->when('wise.resource.waiting', function ($behavior, $meta) use (&$waiting) {
                if ($meta instanceof Meta) {
                    $waiting[] = $meta->data['options'] ?? [];
                }
            });

            $resource->handle(new Behavior('list'), []);
            $resource->handle(new Behavior('list'), []);

            $this->assertCount(1, $outputs);
            $this->assertCount(1, $refreshes);
            $this->assertCount(1, $waiting);
        }
    }
}
