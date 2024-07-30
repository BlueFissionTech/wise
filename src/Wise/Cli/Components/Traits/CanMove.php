<?php

namespace BlueFission\Wise\Cli\Components\Traits;

trait CanMove {

    public function setX($x) {
        $this->_x->val($x);

        foreach ($this->_children as $child) {
            if (method_exists($child, 'setX'))  {
                $child->setX($child->getX() + $x);
            }
        }
    }

    public function setY($y) {
        $this->_y->val($y);

        foreach ($this->_children as $child) {
            if (method_exists($child, 'setY'))  {
               $child->setY($child->getY() + $y);
            }
        }
    }

    public function setPosition($x, $y) {
        $this->setX($x);
        $this->setY($y);

        foreach ($this->_children as $child) {
            if (method_exists($child, 'setPosition'))  {
                $child->setPosition($child->getX() + $x, $child->getY() + $y);
            }
        }
    }
}