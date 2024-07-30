<?php

namespace BlueFission\Wise\Cli\Components\Traits;

trait CollidesChildren {

    public function arrangeChildren(): void
    {
        foreach ($this->_children as $key => $child) {
            for ($i = 0; $i < $key; $i++) {
                $c = $this->_children[$i];
               
                if ($child->collidesWith()) {
                    $newPos = $this->calculateNextAvailablePosition($child);
                    $c->setX($newPos[0]);
                    $c->setY($newPos[1]);
                }
            }
            
        }
    }

    public function calculateNextAvailablePosition($component): array
    {
        $x = $component->getX();
        $y = $component->getY();

        foreach ($this->_children as $key => $c) {
            if ($component->collidesWith($c)) {
                $x = $c->getX() + $c->getWidth() + 1;
                $y = $c->getY();
            }
        }

        return [$x, $y];
    }
}