<?php

namespace BlueFission\Wise\Cli\Components\Traits;

trait Collides {

	public function collidesWith($component): bool
	{
		$left = $this->getX();
		$right = $this->getX() + $this->getWidth();
		$top = $this->getY();
		$bottom = $this->getY() + $this->getHeight();

		$compLeft = $component->getX();
		$compRight = $component->getX() + $component->getWidth();
		$compTop = $component->getY();
		$compBottom = $component->getY() + $component->getHeight();

		if ($left < $compRight && $right > $compLeft && $top < $compBottom && $bottom > $compTop) {
			return true;
		}

		return false;
	}
}