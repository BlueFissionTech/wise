<?php

namespace BlueFission\Wise\Cli\Components\Traits;

trait CanMove {

    public function setX($x) {
        if ( $this->_x->val() != $x) {
            $this->_needsRedraw = true;
        }

        $this->_x->val($x);

        foreach ($this->_children as $child) {
            if (method_exists($child, 'setX'))  {
                $child->setX($child->getX() + $x);
            }
        }
    }

    public function setY($y) {
        if ( $this->_y->val() != $y) {
            $this->_needsRedraw = true;
        }

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