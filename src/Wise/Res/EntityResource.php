<?php
namespace BlueFission\Wise\Res;

use BlueFission\BlueCore\Command\BaseResource;
use BlueFission\Automata\Language\EntityExtractor;

class EntityResource extends BaseResource
{
    private $_extractor;

    protected $_name = 'entity';
    protected $_actions = ['do', 'list', 'get', 'find', 'help'];

    public function __construct()
    {
        $this->_extractor = new EntityExtractor();
        parent::__construct();
    }

    protected function execute($args)
    {
        $statement = implode(' ', $args);

        $extractor = $this->_extractor;
        $methods = [
            'hex',
            'email',
            'web',
            'phone',
            'date',
            'time',
            'name',
            'object',
            'number',
            'adverb',
            'mentions',
            'tags',
            'values',
            'operation'
        ];

        $entities = [];
        $found = [];

        foreach ($methods as $method) {
             $found = $extractor->$method($statement);

             if (!empty($found)) {
                $entities[$method] = $found;
             }
        }

        $this->_entries = $entities;

        $this->store();

        $this->_response = "Collected ".count($entities)." classes of entity.".PHP_EOL;
    }
}