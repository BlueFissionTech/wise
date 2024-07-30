<?php

namespace BlueFission\Wise\Cli\Components;

interface IDrawable
{
    public function getX(): int;
    public function getY(): int;
    public function getWidth(): int;
    public function getHeight(): int;
    public function getContent(): string;
    public function draw(): array;
    public function setParent(IDrawable $parent = null): void;
    public function getParent(): ?IDrawable;
    public function addChild(IDrawable $child): void;
    public function removeChild(IDrawable $child): void;
    public function update(): void;
    public function getZIndex(): int;
    public function setZIndex(int $zIndex): void;
}
